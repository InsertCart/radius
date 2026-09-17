<?php

namespace App\Cms\Search;

/**
 * One page of matches for one content type.
 *
 * Items are plain arrays in the shape Searchable::toSearchResult() returns,
 * plus 'id' and 'type'. They are arrays rather than models so the index
 * engine can answer without touching the database at all.
 */
final class SearchResults
{
    /** @param array<int, array<string, mixed>> $items */
    public function __construct(
        public readonly int $total,
        public readonly array $items,
    ) {}

    public static function empty(): self
    {
        return new self(0, []);
    }
}
