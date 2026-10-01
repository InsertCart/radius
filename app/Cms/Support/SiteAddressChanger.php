<?php

namespace App\Cms\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves a site from one address to another, in the database.
 *
 * The case it exists for: a site built on staging.example.com, its database
 * copied to www.example.com, and every post, layout and menu still linking
 * back to staging. The Import & export screen does this on the way in; this
 * is for when the database itself was moved.
 *
 * Only the places that hold content are touched. Sessions, caches, jobs, logs
 * and payment records are history, not addresses anybody follows, and
 * encrypted settings cannot be searched without being decrypted - which is
 * one more copy of a secret than is needed.
 */
class SiteAddressChanger
{
    /**
     * Where addresses live. Label => [table, columns, extra where].
     *
     * @var array<string, array{0: string, 1: string[], 2?: array<string, mixed>}>
     */
    private const TARGETS = [
        'Posts' => ['posts', ['content', 'excerpt', 'featured_image', 'og_image', 'canonical_url', 'schema_data']],
        'Pages' => ['pages', ['content', 'featured_image', 'og_image', 'canonical_url', 'schema_data']],
        'Products' => ['products', ['description', 'short_description', 'featured_image', 'og_image', 'canonical_url', 'schema_data']],
        'Product variants' => ['product_variants', ['image']],
        'Categories' => ['categories', ['description', 'image', 'og_image', 'canonical_url', 'schema_data']],
        'Tags' => ['tags', ['description']],
        'Comments' => ['comments', ['body']],
        'Builder layouts' => ['layouts', ['data', 'draft_data', 'compiled_css']],
        'Layout history' => ['layout_revisions', ['data']],
        'Saved sections' => ['layout_presets', ['data']],
        'Menus' => ['menu_items', ['url']],
        'SEO' => ['seo_meta', ['og_image', 'canonical_url', 'schema_data']],
        'Redirects' => ['seo_redirects', ['destination']],
        'Settings' => ['settings', ['value'], ['is_encrypted' => false]],
        'Theme options' => ['themes', ['options']],
    ];

    /**
     * Counts what would change, without changing it.
     *
     * @return array<string, array{rows: int, links: int}> only the places with a match
     */
    public function preview(string $from, string $to): array
    {
        return $this->walk(new SiteUrlRewriter($from, $to), write: false);
    }

    /** @return array<string, array{rows: int, links: int}> */
    public function apply(string $from, string $to): array
    {
        @set_time_limit(0);

        $results = $this->walk(new SiteUrlRewriter($from, $to), write: true);

        // Settings, theme options and rendered layouts are all cached, and a
        // cache still holding the old address would make the change look like
        // it had not worked.
        try {
            Artisan::call('cache:clear');
            settings()->flush();
            themes()->flush();
        } catch (\Throwable $e) {
            report($e);
        }

        return $results;
    }

    private function walk(SiteUrlRewriter $rewriter, bool $write): array
    {
        $results = [];

        foreach (self::TARGETS as $label => $target) {
            [$table, $columns] = $target;
            $where = $target[2] ?? [];

            if (! Schema::hasTable($table)) {
                continue;
            }

            // A plugin or an older install may lack a column; skip it rather
            // than fail the whole move.
            $columns = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));

            if ($columns === []) {
                continue;
            }

            $rows = 0;
            $links = 0;

            DB::table($table)
                ->select(array_merge(['id'], $columns))
                ->where($where)
                ->orderBy('id')
                ->chunkById(200, function ($records) use ($table, $columns, $rewriter, $write, &$rows, &$links) {
                    foreach ($records as $record) {
                        $changes = [];

                        foreach ($columns as $column) {
                            $value = $record->{$column};

                            if (! is_string($value) || $value === '') {
                                continue;
                            }

                            $found = 0;
                            $new = $rewriter->replaceStored($value, $found);

                            if ($found > 0 && $new !== $value) {
                                $changes[$column] = $new;
                                $links += $found;
                            }
                        }

                        if ($changes === []) {
                            continue;
                        }

                        $rows++;

                        if ($write) {
                            DB::table($table)->where('id', $record->id)->update($changes);
                        }
                    }
                });

            if ($rows > 0) {
                $results[$label] = ['rows' => $rows, 'links' => $links];
            }
        }

        return $results;
    }
}
