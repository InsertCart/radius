<?php

namespace App\Cms\Search\Concerns;

use Illuminate\Support\Str;

/**
 * Sensible defaults for Searchable models.
 *
 * searchableFields() reads the columns from searchableColumns(), so a model
 * whose indexed text is just its columns only has to declare those.
 */
trait IsSearchable
{
    public function searchableFields(): array
    {
        $fields = [];

        foreach (static::searchableColumns() as $column => $weight) {
            $fields[$column] = [(string) $this->getRawOriginal($column, $this->getAttribute($column)), $weight];
        }

        return $fields;
    }

    /** Plain text, trimmed to a readable length, for result excerpts. */
    protected function searchExcerpt(?string $html, int $length = 160): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $text === '' ? null : Str::limit($text, $length);
    }
}
