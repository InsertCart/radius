<?php

namespace App\Cms\Updates;

use Illuminate\Support\Arr;

/**
 * One release, as described by the JSON file the seller hosts.
 *
 * The parsing here is deliberately forgiving, because this file is hand-edited
 * years after the code reading it was written and shipped. The contract is:
 *
 *  - unknown keys are ignored, so new fields can be added at any time without
 *    breaking a single site already in the field;
 *  - missing keys fall back to a safe default rather than failing;
 *  - "tag" as a plain string is accepted alongside "tags" as an array;
 *  - the file may be a flat object, or wrap the release in "latest".
 *
 * The one thing that is *not* forgiving is `format`. If a future manifest
 * declares a structure this code predates, it stops and says so instead of
 * guessing - which is what makes it safe to change everything else later.
 */
class ReleaseManifest
{
    private function __construct(
        public readonly int $format,
        public readonly string $version,
        public readonly ?string $downloadUrl,
        public readonly ?string $sha256,
        public readonly ?int $size,
        public readonly array $tags,
        public readonly bool $requiresBackup,
        public readonly string $minVersion,
        public readonly ?string $minPhp,
        public readonly array $requiredExtensions,
        public readonly ?string $notes,
        public readonly ?string $changelogUrl,
        public readonly ?string $releasedAt,
        public readonly array $raw,
    ) {}

    /**
     * @throws UpdateException when the document is unusable.
     */
    public static function fromArray(array $data): self
    {
        // A GitHub release looks nothing like a hand-written manifest, so it is
        // translated into one before anything else reads it.
        if (self::looksLikeGitHub($data)) {
            $data = self::fromGitHub($data);
        }

        // Accept both the flat shape and a {"latest": {...}} wrapper, so the
        // simple hand-written file keeps working if the seller later grows
        // into a multi-release document.
        $release = is_array($data['latest'] ?? null) ? $data['latest'] : $data;

        // `format` may sit at either level; the outer one wins because it
        // describes the document rather than the release.
        $format = (int) ($data['format'] ?? $release['format'] ?? 1);
        $supported = (int) config('updates.supported_format', 1);

        if ($format > $supported) {
            throw new UpdateException(
                "This update information uses a newer format (version {$format}) than this release of the CMS "
                ."understands (version {$supported}). Update manually this once, and automatic updates will work again afterwards."
            );
        }

        $version = trim((string) ($release['version'] ?? ''));

        if ($version === '') {
            throw new UpdateException('The update information does not say which version it describes.');
        }

        return new self(
            format: $format,
            version: $version,
            downloadUrl: self::string($release, ['download', 'link', 'url', 'download_url']),
            sha256: self::normaliseHash(self::string($release, ['sha256', 'checksum', 'hash'])),
            size: isset($release['size']) && is_numeric($release['size']) ? (int) $release['size'] : null,
            tags: self::tags($release),
            // Defaults to true: if the seller forgets the field, the safe
            // reading of silence is "take a backup", not "skip it".
            requiresBackup: (bool) ($release['requires_backup'] ?? true),
            minVersion: self::string($release, ['min_version', 'requires']) ?: '0.0.0',
            minPhp: self::string($release, ['min_php', 'php']),
            requiredExtensions: array_values(array_filter(array_map(
                fn ($e) => strtolower(trim((string) $e)),
                Arr::wrap($release['requires_extensions'] ?? $release['extensions'] ?? [])
            ))),
            notes: self::string($release, ['notes', 'description', 'summary']),
            changelogUrl: self::string($release, ['changelog_url', 'changelog']),
            releasedAt: self::string($release, ['released_at', 'date', 'released']),
            raw: $release,
        );
    }

    /**
     * Whether this release is newer than what is running.
     *
     * version_compare understands that 1.10.0 follows 1.9.0, which plain string
     * or numeric comparison both get wrong - and getting it wrong means sites
     * silently stop seeing updates at exactly the point the version numbers
     * start growing.
     */
    public function isNewerThan(string $current): bool
    {
        return version_compare($this->version, $current, '>');
    }

    public function satisfiesMinimumVersion(string $current): bool
    {
        return version_compare($current, $this->minVersion, '>=');
    }

    public function satisfiesPhpVersion(): bool
    {
        return blank($this->minPhp) || version_compare(PHP_VERSION, $this->minPhp, '>=');
    }

    /** @return string[] extensions the server is missing */
    public function missingExtensions(): array
    {
        return array_values(array_filter(
            $this->requiredExtensions,
            fn (string $extension) => ! extension_loaded($extension)
        ));
    }

    /** Tags are free text for display; nothing branches on them. */
    public function hasTag(string $tag): bool
    {
        return in_array(strtolower($tag), $this->tags, true);
    }

    public function isSecurityRelease(): bool
    {
        foreach (['security', 'secure', 'critical', 'vulnerability'] as $tag) {
            if ($this->hasTag($tag)) {
                return true;
            }
        }

        return false;
    }

    public function humanSize(): ?string
    {
        if (! $this->size) {
            return null;
        }

        return number_format($this->size / 1048576, 1).' MB';
    }

    // GitHub releases ------------------------------------------------------

    /**
     * A response from api.github.com/repos/<owner>/<repo>/releases/latest.
     *
     * Matched on two fields together: `tag_name` alone is plausible in a
     * hand-written file, but `assets` beside it is not.
     */
    private static function looksLikeGitHub(array $data): bool
    {
        return isset($data['tag_name']) && isset($data['assets']) && is_array($data['assets']);
    }

    /**
     * Translate a GitHub release into the manifest shape.
     *
     * GitHub supplies the version, the download and its size for free, which is
     * most of what is needed. What it has no concept of is everything specific
     * to updating a CMS - a checksum, whether a backup is required, the minimum
     * version that can upgrade directly - so those are read from a small block
     * in the release notes:
     *
     *     <!-- radius
     *     sha256: 9f2c...
     *     tags: security, breaking
     *     min_version: 1.0.0
     *     -->
     *
     * An HTML comment because GitHub renders release notes as Markdown: the
     * block is invisible to anyone reading the page, and unambiguous to parse.
     * `php artisan cms:release` prints it ready to paste.
     */
    private static function fromGitHub(array $data): array
    {
        $body = (string) ($data['body'] ?? '');
        $meta = self::metadataBlock($body);

        // Tags are conventionally written v1.2.3; the version itself is not.
        $version = ltrim(trim((string) ($data['tag_name'] ?? '')), 'vV');

        $asset = self::releaseAsset($data['assets'] ?? []);

        return array_merge([
            'format' => 1,
            'version' => $version,
            'download' => $asset['browser_download_url'] ?? null,
            'size' => $asset['size'] ?? null,
            'released_at' => $data['published_at'] ?? null,
            'changelog_url' => $data['html_url'] ?? null,
            // The notes are Markdown written for people. The metadata block is
            // stripped out, and only the opening paragraph is kept, because the
            // admin screen shows a summary and links to the rest.
            'notes' => self::summarise($body),
        ], $meta);
    }

    /**
     * The downloadable release, which is not GitHub's own source archive.
     *
     * GitHub attaches "Source code (zip)" to every release automatically. That
     * archive has no vendor/ and no built assets, so installing it would leave
     * a site that cannot boot - it must never be picked by accident. Only
     * uploaded assets appear in this list, so choosing the first .zip is safe,
     * but the name is checked anyway.
     *
     * @param  array<int, array<string, mixed>>  $assets
     * @return array<string, mixed>
     */
    private static function releaseAsset(array $assets): array
    {
        foreach ($assets as $asset) {
            $name = strtolower((string) ($asset['name'] ?? ''));

            if (str_ends_with($name, '.zip') && ! str_contains($name, 'source')) {
                return $asset;
            }
        }

        return [];
    }

    /** @return array<string, mixed> */
    private static function metadataBlock(string $body): array
    {
        if (! preg_match('/<!--\s*(?:radius|cms|update)\s*(.*?)-->/is', $body, $matches)) {
            return [];
        }

        $meta = [];

        foreach (preg_split('/\R/', $matches[1]) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);

            if ($key === '' || $value === '') {
                continue;
            }

            $meta[$key] = match ($key) {
                'requires_backup' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'tags', 'requires_extensions' => array_values(array_filter(array_map('trim', explode(',', $value)))),
                'size' => (int) $value,
                default => $value,
            };
        }

        return $meta;
    }

    /** The first real paragraph of the release notes, with the metadata gone. */
    private static function summarise(string $body): ?string
    {
        $body = preg_replace('/<!--.*?-->/s', '', $body);
        $body = trim((string) $body);

        if ($body === '') {
            return null;
        }

        $paragraph = preg_split('/\R{2,}/', $body)[0] ?? $body;

        // Markdown heading markers and list bullets read badly out of context.
        $paragraph = trim(preg_replace('/^[#>\-*\s]+/m', '', $paragraph));

        return \Illuminate\Support\Str::limit(str_replace("\n", ' ', $paragraph), 300);
    }

    /** First non-empty value among several accepted key spellings. */
    private static function string(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) || is_numeric($value)) {
                $value = trim((string) $value);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /** @return string[] lower-cased, from either "tags" (array) or "tag" (string). */
    private static function tags(array $release): array
    {
        $tags = $release['tags'] ?? $release['tag'] ?? [];

        // "security, breaking" is a natural thing to write in a hand-edited
        // file, so split on commas as well as accepting a real array.
        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($tag) => strtolower(trim((string) $tag)),
            Arr::wrap($tags)
        ))));
    }

    /** Accepts "sha256:abc…" as well as a bare hash. */
    private static function normaliseHash(?string $hash): ?string
    {
        if (blank($hash)) {
            return null;
        }

        $hash = strtolower(trim($hash));

        if (str_contains($hash, ':')) {
            $hash = substr($hash, strrpos($hash, ':') + 1);
        }

        return preg_match('/^[a-f0-9]{64}$/', $hash) === 1 ? $hash : null;
    }
}
