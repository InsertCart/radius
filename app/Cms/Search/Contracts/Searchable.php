<?php

namespace App\Cms\Search\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * A model the site search can find.
 *
 * Implement this, use the IsSearchable trait for the field defaults, and
 * register the model in config/search.php (or with search()->register()).
 */
interface Searchable
{
    /**
     * Rows the public is allowed to find.
     *
     * $includeScheduled asks for rows that are published but dated in the
     * future as well. The index stores those ahead of time, together with
     * the moment they become visible, so a scheduled post turns up in search
     * on time without anything having to be saved.
     */
    public static function searchableQuery(bool $includeScheduled = false): Builder;

    /**
     * Database columns the database engine matches with LIKE, mapped to a
     * weight. Higher weights rank a match in that column first.
     *
     * @return array<string, int>
     */
    public static function searchableColumns(): array;

    /**
     * Text the index engine reads, as field => [text, weight]. May include
     * text that is not a column: tag names, a variant's SKU.
     *
     * @return array<string, array{0: ?string, 1: int}>
     */
    public function searchableFields(): array;

    /**
     * How this row looks in live results and on the /search page.
     *
     * Keys: title, url, excerpt, image (nullable), meta (a short line such as
     * a price or a date, nullable), visible_from (a Unix timestamp or null).
     *
     * @return array{title: string, url: string, excerpt: ?string, image: ?string, meta: ?string, visible_from: ?int}
     */
    public function toSearchResult(): array;
}
