<?php

namespace App\Cms\Search\Contracts;

use App\Cms\Search\SearchResults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * A way of answering a search.
 *
 * Every method takes the content type key from config/search.php ('post',
 * 'product', ...). An engine never decides whether a type is enabled; the
 * SearchManager does that before calling in.
 */
interface Engine
{
    /**
     * Narrows an Eloquent query to rows matching $term, for listing pages
     * that paginate, filter and sort on top of the search. When $rank is true
     * the engine may order by relevance first.
     */
    public function constrain(Builder|Relation $query, string $type, string $term, bool $rank = false): Builder|Relation;

    /** Display-ready matches, best first. */
    public function search(string $type, string $term, int $limit, int $offset = 0): SearchResults;

    /** Whether this engine can answer for $type right now (an index is built, a server is reachable). */
    public function ready(string $type): bool;

    /** Adds or refreshes one row. */
    public function update(string $type, Model $model): void;

    /** Drops one row. */
    public function delete(string $type, int|string $id): void;

    /** Rebuilds everything for $type from the database. Returns the number of rows indexed. */
    public function rebuild(string $type): int;

    /**
     * Facts for the admin status card.
     *
     * @return array{ready: bool, documents: ?int, built_at: ?int, updated_at: ?int, bytes: ?int}
     */
    public function status(string $type): array;
}
