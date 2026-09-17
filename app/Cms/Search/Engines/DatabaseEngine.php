<?php

namespace App\Cms\Search\Engines;

use App\Cms\Search\Contracts\Engine;
use App\Cms\Search\SearchManager;
use App\Cms\Search\SearchResults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Searches the tables directly with LIKE.
 *
 * Nothing to build and never out of date, which makes it the right default
 * for most sites. The cost is that a match inside the body of an article
 * needs a scan of that column, so on a very large site, or with live results
 * on a busy one, the index engine is the better choice.
 */
class DatabaseEngine implements Engine
{
    public function __construct(private SearchManager $manager) {}

    public function constrain(Builder|Relation $query, string $type, string $term, bool $rank = false): Builder|Relation
    {
        // A model's own search scope wins. The bundled models have had one
        // since before this engine existed, and code outside the CMS may
        // depend on exactly what it matches.
        if ($query->getModel()->hasNamedScope('search')) {
            return $query->search($term);
        }

        $like = $this->like($term);
        $columns = array_keys($this->columns($type));

        return $query->where(function (Builder $q) use ($columns, $like) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', $like);
            }
        });
    }

    public function search(string $type, string $term, int $limit, int $offset = 0): SearchResults
    {
        $class = $this->manager->modelFor($type);
        $query = $this->constrain($class::searchableQuery(), $type, $term);

        $total = (clone $query)->count();

        if ($total === 0) {
            return SearchResults::empty();
        }

        // Rank by the heaviest column that matched: a title hit before a body hit.
        $like = $this->like($term);
        $table = $query->getModel()->getTable();
        $cases = [];
        $bindings = [];
        $rank = 0;

        foreach (array_keys($this->columns($type)) as $column) {
            $cases[] = 'WHEN '.$query->getGrammar()->wrap($table.'.'.$column).' LIKE ? THEN '.$rank++;
            $bindings[] = $like;
        }

        $models = $query
            ->orderByRaw('CASE '.implode(' ', $cases).' ELSE '.$rank.' END', $bindings)
            ->orderByDesc($query->getModel()->getQualifiedKeyName())
            ->skip($offset)
            ->take($limit)
            ->get();

        return new SearchResults($total, $models->map(
            fn (Model $model) => $this->manager->resultFor($type, $model)
        )->all());
    }

    public function ready(string $type): bool
    {
        return true;
    }

    public function update(string $type, Model $model): void {}

    public function delete(string $type, int|string $id): void {}

    public function rebuild(string $type): int
    {
        return 0;
    }

    public function status(string $type): array
    {
        return ['ready' => true, 'documents' => null, 'built_at' => null, 'updated_at' => null, 'bytes' => null];
    }

    /** @return array<string, int> heaviest first */
    private function columns(string $type): array
    {
        $columns = $this->manager->modelFor($type)::searchableColumns();
        arsort($columns);

        return $columns;
    }

    private function like(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
    }
}
