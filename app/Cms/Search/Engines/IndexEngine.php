<?php

namespace App\Cms\Search\Engines;

use App\Cms\Search\Contracts\Engine;
use App\Cms\Search\SearchManager;
use App\Cms\Search\SearchResults;
use App\Cms\Search\Tokenizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;

/**
 * Answers searches from a word index kept in files under storage/.
 *
 * Plain PHP and JSON, so it runs on any host this CMS runs on - no search
 * server to install. A query reads a few small files and never touches a
 * table, which is the point: live results on a busy site stop being a query
 * per keystroke against the posts table.
 *
 * Layout, one folder per content type:
 *
 *     manifest.json        document count, total length, build times
 *     terms/<shard>.json   word => {id: weight}, sharded by the word's first
 *                          two characters so a lookup reads one small file
 *     docs/<n>.json        id => what a result displays, 256 ids per file
 *     forward/<n>.json     id => the words it was indexed under, so an update
 *                          can take out exactly the postings it put in
 *     future.json          id => timestamp, for rows scheduled to appear later
 *
 * Every write goes to a temporary file that is then renamed over the old one,
 * so a reader never sees half a file. Writers take a lock per type. A rebuild
 * is assembled in a separate folder and swapped in whole.
 *
 * Ranking is BM25, applied per field so a word in a title outweighs the same
 * word in the body by the field's weight. It suits sites up to some tens of
 * thousands of documents; beyond that, register an engine backed by a search
 * server.
 */
class IndexEngine implements Engine
{
    private const FORMAT = 1;

    private const DOCS_PER_FILE = 256;

    private const K1 = 1.2;

    private const B = 0.75;

    /** Weight kept for a completion of the last word, relative to an exact match. */
    private const PREFIX_PENALTY = 0.6;

    /** Postings held in memory during a rebuild before they are written out. */
    private const FLUSH_AT = 150000;

    /** @var array<string, array<string, mixed>> per-request read cache */
    private array $cache = [];

    public function __construct(
        private SearchManager $manager,
        private Filesystem $files,
    ) {}

    // Reading ----------------------------------------------------------------

    public function constrain(Builder|Relation $query, string $type, string $term, bool $rank = false): Builder|Relation
    {
        $ids = array_keys($this->score($type, $term));

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        $ids = array_map('intval', $ids);
        $key = $query->getModel()->getQualifiedKeyName();

        $query->whereIntegerInRaw($key, $ids);

        if ($rank) {
            // Integers only, so inlining them is safe, and it keeps a query
            // with a thousand matches clear of the driver's placeholder limit.
            $cases = [];

            foreach ($ids as $position => $id) {
                $cases[] = "WHEN {$id} THEN {$position}";
            }

            $query->orderByRaw('CASE '.$query->getGrammar()->wrap($key).' '.implode(' ', $cases).' END');
        }

        return $query;
    }

    public function search(string $type, string $term, int $limit, int $offset = 0): SearchResults
    {
        $scores = $this->score($type, $term);

        if ($scores === []) {
            return SearchResults::empty();
        }

        $items = [];

        foreach (array_slice(array_keys($scores), $offset, $limit) as $id) {
            $doc = $this->doc($type, (int) $id);

            if ($doc === null) {
                continue;
            }

            $items[] = [
                'id' => (int) $id,
                'type' => $type,
                'title' => (string) ($doc['t'] ?? ''),
                'url' => $this->absolute($doc['u'] ?? null) ?? '#',
                'excerpt' => $doc['e'] ?? null,
                'image' => $this->absolute($doc['i'] ?? null),
                'meta' => $doc['m'] ?? null,
            ];
        }

        return new SearchResults(count($scores), $items);
    }

    /**
     * Matching ids mapped to their score, best first.
     *
     * Every word typed must match (AND). The last word also matches words it
     * begins, since it is usually still being typed. If nothing contains all
     * the words, documents containing any of them are returned instead - a
     * visitor who adds one word too many still sees something useful.
     *
     * @return array<int, float>
     */
    private function score(string $type, string $term): array
    {
        $words = Tokenizer::queryWords($term);
        $manifest = $this->manifest($type);

        if ($words === [] || ! $manifest || ($manifest['documents'] ?? 0) === 0) {
            return [];
        }

        $total = (int) $manifest['documents'];
        $last = count($words) - 1;
        $perWord = [];

        foreach ($words as $position => $word) {
            $shard = $this->shard($type, Tokenizer::shard($word));
            $matches = isset($shard[$word]) ? [$word => 1.0] : [];

            if ($position === $last) {
                $matches += $this->completions($shard, $word);
            }

            $scores = [];

            foreach ($matches as $match => $penalty) {
                $postings = $shard[$match];
                $frequency = count($postings);
                $idf = log(1 + ($total - $frequency + 0.5) / ($frequency + 0.5));

                foreach ($postings as $id => $weight) {
                    $score = $idf * $weight * $penalty;

                    if ($score > ($scores[$id] ?? 0)) {
                        $scores[$id] = $score;
                    }
                }
            }

            $perWord[] = $scores;
        }

        $combined = $this->intersect($perWord);

        if ($combined === [] && count($perWord) > 1) {
            $combined = $this->union($perWord);
        }

        foreach ($this->future($type) as $id => $visibleFrom) {
            if ($visibleFrom > now()->getTimestamp()) {
                unset($combined[$id]);
            }
        }

        arsort($combined);

        return array_slice($combined, 0, (int) config('search.max_results', 1000), true);
    }

    /** Indexed words that $prefix begins, most common first. */
    private function completions(array $shard, string $prefix): array
    {
        $found = [];

        foreach ($shard as $word => $postings) {
            $word = (string) $word;

            if ($word !== $prefix && str_starts_with($word, $prefix)) {
                $found[$word] = count($postings);
            }
        }

        arsort($found);

        return array_fill_keys(
            array_map('strval', array_keys(array_slice($found, 0, (int) config('search.index.max_prefix_expansions', 40), true))),
            self::PREFIX_PENALTY
        );
    }

    private function intersect(array $perWord): array
    {
        $combined = array_shift($perWord);

        foreach ($perWord as $scores) {
            $combined = array_intersect_key($combined, $scores);

            foreach ($combined as $id => $score) {
                $combined[$id] = $score + $scores[$id];
            }
        }

        return $combined;
    }

    private function union(array $perWord): array
    {
        $combined = [];

        foreach ($perWord as $scores) {
            foreach ($scores as $id => $score) {
                $combined[$id] = ($combined[$id] ?? 0) + $score;
            }
        }

        return $combined;
    }

    // State ------------------------------------------------------------------

    public function ready(string $type): bool
    {
        return ($this->manifest($type)['format'] ?? null) === self::FORMAT;
    }

    public function status(string $type): array
    {
        $manifest = $this->manifest($type);
        $bytes = null;

        if ($manifest) {
            $bytes = 0;

            foreach ($this->files->allFiles($this->dir($type)) as $file) {
                $bytes += $file->getSize();
            }
        }

        return [
            'ready' => $this->ready($type),
            'documents' => $manifest['documents'] ?? null,
            'built_at' => $manifest['built_at'] ?? null,
            'updated_at' => $manifest['updated_at'] ?? null,
            'bytes' => $bytes,
        ];
    }

    /** Deletes the index for $type. The engine then reports not ready until rebuilt. */
    public function clear(string $type): void
    {
        $this->locked($type, function () use ($type) {
            $this->files->deleteDirectory($this->dir($type));
        });
    }

    // Writing ----------------------------------------------------------------

    public function update(string $type, Model $model): void
    {
        $this->locked($type, function () use ($type, $model) {
            $manifest = $this->manifest($type);

            // Nothing to keep current until the index has been built once.
            if (! $manifest) {
                return;
            }

            $id = (int) $model->getKey();
            $dir = $this->dir($type);
            $file = $this->bucket($id);

            $docs = $this->read("{$dir}/docs/{$file}.json");
            $forward = $this->read("{$dir}/forward/{$file}.json");

            $oldWords = isset($forward[$id]) ? explode(' ', $forward[$id]) : [];
            $oldLength = (int) ($docs[$id]['l'] ?? 0);
            $existed = isset($docs[$id]);

            $documents = (int) $manifest['documents'] - ($existed ? 1 : 0);
            $length = (int) $manifest['length'] - $oldLength;
            $average = $documents > 0 ? $length / $documents : 0;

            [$weights, $docLength] = $this->weigh($model->searchableFields(), $average);
            $result = $this->manager->resultFor($type, $model);

            $this->rewriteShards($dir, $id, $oldWords, $weights);

            $docs[$id] = $this->compact($result, $docLength);
            $forward[$id] = implode(' ', array_keys($weights));

            $this->write("{$dir}/docs/{$file}.json", $docs);
            $this->write("{$dir}/forward/{$file}.json", $forward);

            $this->writeFuture($dir, $id, $result['visible_from'] ?? null);

            $manifest['documents'] = $documents + 1;
            $manifest['length'] = $length + $docLength;
            $manifest['updated_at'] = time();
            $this->write("{$dir}/manifest.json", $manifest);
        });
    }

    public function delete(string $type, int|string $id): void
    {
        $this->locked($type, function () use ($type, $id) {
            $manifest = $this->manifest($type);

            if (! $manifest) {
                return;
            }

            $id = (int) $id;
            $dir = $this->dir($type);
            $file = $this->bucket($id);

            $docs = $this->read("{$dir}/docs/{$file}.json");

            if (! isset($docs[$id])) {
                return;
            }

            $forward = $this->read("{$dir}/forward/{$file}.json");

            $this->rewriteShards($dir, $id, isset($forward[$id]) ? explode(' ', $forward[$id]) : [], []);

            $length = (int) ($docs[$id]['l'] ?? 0);
            unset($docs[$id], $forward[$id]);

            $this->write("{$dir}/docs/{$file}.json", $docs);
            $this->write("{$dir}/forward/{$file}.json", $forward);
            $this->writeFuture($dir, $id, null);

            $manifest['documents'] = max(0, (int) $manifest['documents'] - 1);
            $manifest['length'] = max(0, (int) $manifest['length'] - $length);
            $manifest['updated_at'] = time();
            $this->write("{$dir}/manifest.json", $manifest);
        });
    }

    public function rebuild(string $type): int
    {
        $class = $this->manager->modelFor($type);
        $dir = $this->dir($type);

        // The lock is held for the whole build. A post saved meanwhile waits
        // for the new index and is then applied to it, instead of being
        // written to the old one and lost in the swap.
        return $this->locked($type, function () use ($class, $type, $dir) {
            $building = $dir.'.building-'.bin2hex(random_bytes(4));
            $this->files->ensureDirectoryExists($building);

            $state = ['documents' => 0, 'length' => 0, 'postings' => 0];
            $pending = ['terms' => [], 'docs' => [], 'forward' => []];
            $future = [];

            try {
                $class::searchableQuery(true)->chunkById(
                    (int) config('search.index.chunk', 200),
                    function ($models) use ($type, $building, &$state, &$pending, &$future) {
                        foreach ($models as $model) {
                            $average = $state['documents'] > 0 ? $state['length'] / $state['documents'] : 0;
                            [$weights, $length] = $this->weigh($model->searchableFields(), $average);

                            $id = (int) $model->getKey();
                            $file = $this->bucket($id);
                            $result = $this->manager->resultFor($type, $model);

                            foreach ($weights as $word => $weight) {
                                $pending['terms'][Tokenizer::shard($word)][$word][$id] = $weight;
                            }

                            $pending['docs'][$file][$id] = $this->compact($result, $length);
                            $pending['forward'][$file][$id] = implode(' ', array_keys($weights));

                            if (($result['visible_from'] ?? null) > now()->getTimestamp()) {
                                $future[$id] = (int) $result['visible_from'];
                            }

                            $state['documents']++;
                            $state['length'] += $length;
                            $state['postings'] += count($weights);
                        }

                        if ($state['postings'] >= self::FLUSH_AT) {
                            $this->flushPending($building, $pending);
                            $state['postings'] = 0;
                        }
                    }
                );

                $this->flushPending($building, $pending);

                $this->write("{$building}/future.json", $future);
                $this->write("{$building}/manifest.json", [
                    'format' => self::FORMAT,
                    'type' => $type,
                    'documents' => $state['documents'],
                    'length' => $state['length'],
                    'built_at' => time(),
                    'updated_at' => time(),
                ]);

                $this->swap($building, $dir);
            } catch (\Throwable $e) {
                $this->files->deleteDirectory($building);

                throw $e;
            }

            return $state['documents'];
        });
    }

    /**
     * Word weights for one document, and its length in words.
     *
     * The weight of a word is BM25's term-frequency part computed per field
     * and scaled by the field's weight, so "shoes" once in a title (weight 5)
     * ranks well above "shoes" once in a description (weight 1). Document
     * length is normalised against the average at the time of indexing; the
     * average drifts slowly as content changes, and a rebuild resets it.
     *
     * @return array{0: array<string, float>, 1: int}
     */
    private function weigh(array $fields, float $average): array
    {
        $max = (int) config('search.index.max_field_length', 20000);
        $perField = [];
        $length = 0;

        foreach ($fields as $field) {
            [$text, $weight] = [$field[0] ?? '', (float) ($field[1] ?? 1)];

            if ($weight <= 0 || $text === null || $text === '') {
                continue;
            }

            $words = Tokenizer::words(mb_substr((string) $text, 0, $max));
            $length += count($words);
            $perField[] = [array_count_values($words), $weight];
        }

        $norm = $average > 0 ? (1 - self::B + self::B * $length / $average) : 1;
        $weights = [];

        foreach ($perField as [$counts, $weight]) {
            foreach ($counts as $word => $count) {
                $weights[(string) $word] = ($weights[(string) $word] ?? 0)
                    + $weight * ($count * (self::K1 + 1)) / ($count + self::K1 * $norm);
            }
        }

        return [array_map(fn ($w) => round($w, 4), $weights), $length];
    }

    /** Takes $id out of the postings for $oldWords and puts it into those for $weights. */
    private function rewriteShards(string $dir, int $id, array $oldWords, array $weights): void
    {
        $shards = [];

        foreach ($oldWords as $word) {
            if ($word !== '') {
                $shards[Tokenizer::shard($word)]['remove'][] = $word;
            }
        }

        foreach ($weights as $word => $weight) {
            $shards[Tokenizer::shard((string) $word)]['add'][(string) $word] = $weight;
        }

        foreach ($shards as $shard => $changes) {
            $path = "{$dir}/terms/{$shard}.json";
            $terms = $this->read($path);

            foreach ($changes['remove'] ?? [] as $word) {
                unset($terms[$word][$id]);

                if (empty($terms[$word])) {
                    unset($terms[$word]);
                }
            }

            foreach ($changes['add'] ?? [] as $word => $weight) {
                $terms[$word][$id] = $weight;
            }

            $this->write($path, $terms);
        }
    }

    private function flushPending(string $building, array &$pending): void
    {
        foreach ($pending['terms'] as $shard => $words) {
            $path = "{$building}/terms/{$shard}.json";
            $existing = $this->read($path);

            foreach ($words as $word => $postings) {
                $existing[$word] = ($existing[$word] ?? []) + $postings;
            }

            $this->write($path, $existing);
        }

        foreach (['docs', 'forward'] as $kind) {
            foreach ($pending[$kind] as $file => $rows) {
                $path = "{$building}/{$kind}/{$file}.json";
                $this->write($path, $this->read($path) + $rows);
            }
        }

        $pending = ['terms' => [], 'docs' => [], 'forward' => []];
    }

    private function swap(string $building, string $dir): void
    {
        $old = null;

        if (is_dir($dir)) {
            $old = $dir.'.old-'.bin2hex(random_bytes(4));

            // Deleting in place leaves a moment with no index, during which
            // searches fall back to the database. Better than failing.
            if (! $this->rename($dir, $old)) {
                $this->files->deleteDirectory($dir);
                $old = null;
            }
        }

        if (! $this->rename($building, $dir)) {
            if (! $this->files->copyDirectory($building, $dir)) {
                throw new \RuntimeException("Could not move the new search index into place at [{$dir}].");
            }

            $this->files->deleteDirectory($building);
        }

        if ($old) {
            $this->files->deleteDirectory($old);
        }

        $this->cache = [];
    }

    /**
     * Renames a folder, retrying briefly. On Windows a folder that was just
     * written is often held open for a moment by the search indexer or an
     * antivirus scanner, and the first attempt is refused.
     */
    private function rename(string $from, string $to): bool
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            if (@rename($from, $to)) {
                return true;
            }

            clearstatcache();
            usleep(50000 * $attempt);
        }

        return false;
    }

    private function writeFuture(string $dir, int $id, ?int $visibleFrom): void
    {
        $future = $this->read("{$dir}/future.json");
        $had = isset($future[$id]);

        if ($visibleFrom !== null && $visibleFrom > now()->getTimestamp()) {
            $future[$id] = $visibleFrom;
        } elseif ($had) {
            unset($future[$id]);
        } else {
            return;
        }

        $this->write("{$dir}/future.json", $future);
    }

    /** The subset of a result stored on disk, with site URLs made relative. */
    private function compact(array $result, int $length): array
    {
        return array_filter([
            't' => $result['title'] ?? '',
            'u' => $this->relative($result['url'] ?? null),
            'e' => $result['excerpt'] ?? null,
            'i' => $this->relative($result['image'] ?? null),
            'm' => $result['meta'] ?? null,
            'l' => $length,
        ], fn ($value) => $value !== null && $value !== '');
    }

    // Files ------------------------------------------------------------------

    private function manifest(string $type): ?array
    {
        return $this->cache[$type]['manifest'] ??= ($this->read($this->dir($type).'/manifest.json') ?: null);
    }

    private function shard(string $type, string $shard): array
    {
        return $this->cache[$type]['terms'][$shard] ??= $this->read($this->dir($type)."/terms/{$shard}.json");
    }

    private function doc(string $type, int $id): ?array
    {
        $file = $this->bucket($id);

        return ($this->cache[$type]['docs'][$file] ??= $this->read($this->dir($type)."/docs/{$file}.json"))[$id] ?? null;
    }

    private function future(string $type): array
    {
        return $this->cache[$type]['future'] ??= $this->read($this->dir($type).'/future.json');
    }

    private function dir(string $type): string
    {
        // Type keys come from config, but they become folder names, so they
        // are held to a strict alphabet regardless.
        if (! preg_match('/^[a-z0-9_-]+$/', $type)) {
            throw new \InvalidArgumentException("Invalid search type [{$type}].");
        }

        return rtrim((string) config('search.index.path'), '/\\').'/'.$type;
    }

    private function bucket(int $id): int
    {
        return intdiv($id, self::DOCS_PER_FILE);
    }

    private function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) @file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    private function write(string $path, array $data): void
    {
        if ($data === []) {
            if (is_file($path)) {
                @unlink($path);
            }

            return;
        }

        $this->files->ensureDirectoryExists(dirname($path));

        // Objects throughout: a posting list for ids [0] must not turn into
        // a JSON array and come back as a list.
        $json = json_encode($data, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        file_put_contents($temporary, $json, LOCK_EX);

        // rename() replaces the target atomically on POSIX. On Windows it can
        // fail while another request has the file open; fall back to writing
        // in place rather than losing the update.
        if (! @rename($temporary, $path)) {
            file_put_contents($path, $json, LOCK_EX);
            @unlink($temporary);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function locked(string $type, callable $callback): mixed
    {
        $base = rtrim((string) config('search.index.path'), '/\\');
        $this->files->ensureDirectoryExists($base);

        $handle = fopen($this->dir($type).'.lock', 'c');

        try {
            flock($handle, LOCK_EX);
            unset($this->cache[$type]);

            return $callback();
        } finally {
            unset($this->cache[$type]);
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function relative(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $root = rtrim(url('/'), '/');

        if ($url === $root) {
            return '/';
        }

        return str_starts_with($url, $root.'/') ? substr($url, strlen($root)) : $url;
    }

    private function absolute(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? url($url) : $url;
    }
}
