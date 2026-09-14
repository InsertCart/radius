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
