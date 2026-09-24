<?php

namespace App\Cms\Transfer;

/**
 * What to put in an export, and in what shape.
 *
 * Filters apply to every type that understands them: a date range narrows
 * posts by publication and products by creation, and a type with no date to
 * speak of - a tag - ignores it rather than disappearing. That is deliberate.
 * Somebody exporting "this year's posts" still needs the categories those
 * posts belong to, or the bundle imports into a site where nothing is filed.
 */
class ExportOptions
{
    /**
     * @param  string[]  $types  Resource keys to include.
     * @param  array<string, int[]>  $ids  Per-type allowlist of primary keys; empty means "everything that matches".
     */
    public function __construct(
        public array $types = [],
        public ?string $status = null,
        public ?string $from = null,
        public ?string $to = null,
        public array $ids = [],
        public bool $includeMediaFiles = true,
        public bool $includeLayouts = true,
        public string $format = 'zip',
    ) {}

    public static function fromArray(array $input): self
    {
        return new self(
            types: array_values(array_filter((array) ($input['types'] ?? []))),
            status: filled($input['status'] ?? null) && $input['status'] !== 'any' ? (string) $input['status'] : null,
            from: filled($input['from'] ?? null) ? (string) $input['from'] : null,
            to: filled($input['to'] ?? null) ? (string) $input['to'] : null,
            ids: (array) ($input['ids'] ?? []),
            includeMediaFiles: (bool) ($input['include_media_files'] ?? true),
            includeLayouts: (bool) ($input['include_layouts'] ?? true),
            format: (string) ($input['format'] ?? 'zip'),
        );
    }

    /** @return int[] */
    public function idsFor(string $type): array
    {
        return array_values(array_filter(array_map('intval', (array) ($this->ids[$type] ?? []))));
    }

    /** A JSON-only or CSV export has nowhere to put a file, so media is records alone. */
    public function bundlesFiles(): bool
    {
        return $this->format === 'zip' && $this->includeMediaFiles;
    }
}
