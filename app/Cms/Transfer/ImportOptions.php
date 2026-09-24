<?php

namespace App\Cms\Transfer;

/**
 * How an import should behave when it meets something that already exists,
 * and what it is allowed to do on its own initiative.
 *
 * The default is the cautious one: existing records are left exactly as they
 * are. An import is usually run against a site that already has content, and
 * "it overwrote my home page" is a far worse morning than "it skipped three
 * pages and told me so".
 */
class ImportOptions
{
    public const SKIP_EXISTING = 'skip';

    public const UPDATE_EXISTING = 'update';

    /**
     * @param  string[]|null  $types  Resource keys to import; null means everything the bundle holds.
     * @param  string|null  $status  Forces every imported record to this status.
     * @param  int|null  $authorId  Who owns content whose author is not on this site.
     */
    public function __construct(
        public string $mode = self::SKIP_EXISTING,
        public ?array $types = null,
        public ?string $status = null,
        public ?int $authorId = null,
        public bool $createAuthors = false,
        public bool $importMedia = true,
        public bool $downloadMedia = false,
        public bool $includeLayouts = true,
        public bool $rewriteUrls = true,
    ) {}

    public static function fromArray(array $input): self
    {
        $types = $input['types'] ?? null;

        return new self(
            mode: ($input['mode'] ?? self::SKIP_EXISTING) === self::UPDATE_EXISTING
                ? self::UPDATE_EXISTING
                : self::SKIP_EXISTING,
            types: is_array($types) && $types !== [] ? array_values(array_filter($types)) : null,
            status: in_array($input['status'] ?? null, ['draft', 'published'], true) ? $input['status'] : null,
            authorId: filled($input['author_id'] ?? null) ? (int) $input['author_id'] : null,
            createAuthors: (bool) ($input['create_authors'] ?? false),
            importMedia: (bool) ($input['import_media'] ?? true),
            downloadMedia: (bool) ($input['download_media'] ?? false),
            includeLayouts: (bool) ($input['include_layouts'] ?? true),
            rewriteUrls: (bool) ($input['rewrite_urls'] ?? true),
        );
    }

    public function updatesExisting(): bool
    {
        return $this->mode === self::UPDATE_EXISTING;
    }

    public function wants(string $type): bool
    {
        return $this->types === null || in_array($type, $this->types, true);
    }

    /** The status a record should be saved with, or its own when nothing was forced. */
    public function statusFor(?string $original): ?string
    {
        return $this->status ?? $original;
    }
}
