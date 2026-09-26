<?php

namespace App\Cms\Marketplace;

use Illuminate\Support\Str;

/**
 * One theme listed in the marketplace catalogue.
 *
 * Parsing follows the same contract as the update manifest, because the file
 * is hand-edited long after this code ships: unknown keys are ignored, missing
 * optional keys fall back to defaults, and the few fields that genuinely cannot
 * be guessed make the entry unusable rather than half-read.
 *
 * Every address in an entry is treated as untrusted. Links are kept only when
 * they are http(s), so a catalogue cannot slip a "javascript:" URL into the
 * admin panel, and images only when they are https, so a screenshot never
 * downgrades an https admin page to mixed content.
 */
class MarketplaceItem
{
    private function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $version,
        public readonly ?string $author,
        public readonly ?string $authorUrl,
        public readonly ?string $description,
        public readonly array $tags,
        public readonly array $supports,
        public readonly ?string $screenshot,
        public readonly array $screenshots,
        public readonly ?string $previewUrl,
        public readonly ?string $downloadUrl,
        public readonly ?string $sha256,
        public readonly ?int $size,
        public readonly ?string $requires,
        public readonly ?string $tested,
        public readonly ?string $license,
        public readonly ?string $updatedAt,
        public readonly float $price,
        public readonly bool $requiresLicense,
        public readonly ?string $purchaseUrl,
        public readonly ?string $currency,
        public readonly array $raw,
    ) {}

    /**
     * Returns null for an entry that cannot be used, so one bad listing never
     * takes the whole directory down with it.
     */
    public static function tryFromArray(mixed $data): ?self
    {
        if (! is_array($data)) {
            return null;
        }

        $slug = trim((string) ($data['slug'] ?? ''));

        // The slug becomes a folder name under themes/, so it must already be
        // exactly what Str::slug() would make of it - never rewritten here.
        if ($slug === '' || $slug !== Str::slug($slug)) {
            return null;
        }

        // Written as a JSON number, 1.10 arrives as the float 1.1 and would
        // compare as older than 1.9. Rather than install something whose
        // version is already wrong, the entry is skipped.
        if (! is_string($data['version'] ?? null) || trim($data['version']) === '') {
            return null;
        }

        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $screenshots = array_values(array_filter(array_map(
            fn ($url) => self::imageUrl($url),
            is_array($data['screenshots'] ?? null) ? $data['screenshots'] : []
        )));

        return new self(
            slug: $slug,
            name: $name,
            version: trim($data['version']),
            author: self::text($data['author'] ?? null),
            authorUrl: self::linkUrl($data['author_url'] ?? null),
            description: self::text($data['description'] ?? null),
            tags: self::words($data['tags'] ?? []),
            supports: self::words($data['supports'] ?? []),
            screenshot: self::imageUrl($data['screenshot'] ?? null) ?? ($screenshots[0] ?? null),
            screenshots: $screenshots,
            previewUrl: self::linkUrl($data['preview_url'] ?? null),
            downloadUrl: self::text($data['download'] ?? null),
            sha256: self::hash($data['sha256'] ?? null),
            size: isset($data['size']) && is_numeric($data['size']) ? max(0, (int) $data['size']) : null,
            requires: self::text($data['requires'] ?? null),
            tested: self::text($data['tested'] ?? null),
            license: self::text($data['license'] ?? null),
            updatedAt: self::text($data['updated_at'] ?? null),
            price: is_numeric($data['price'] ?? null) ? (float) $data['price'] : 0.0,
            requiresLicense: (bool) ($data['requires_license'] ?? false),
            // Where a paid item is bought. The CMS never takes the payment: the
            // buyer purchases on the publisher's site and uploads the ZIP.
            purchaseUrl: self::linkUrl($data['purchase_url'] ?? null),
            currency: is_string($data['currency'] ?? null) && preg_match('/^[A-Z]{3}$/', $data['currency']) ? $data['currency'] : null,
            raw: $data,
        );
    }

    /**
     * Only free themes can be installed by this release. The rule exists now so
     * that paid listings can be added to the catalogue later: installs that
     * predate them skip those entries, rather than offering a download that
     * would be refused.
     */
    public function isFree(): bool
    {
        return $this->price <= 0 && ! $this->requiresLicense;
    }

    /** "USD 29.00", or null for a free item. */
    public function priceLabel(): ?string
    {
        if ($this->price <= 0) {
            return null;
        }

        return ($this->currency ?? 'USD').' '.number_format($this->price, 2);
    }

    /** Whether this CMS is new enough for the theme. */
    public function isCompatible(?string $cmsVersion = null): bool
    {
        return blank($this->requires)
            || version_compare($cmsVersion ?? cms_version(), $this->requires, '>=');
    }

    /** version_compare knows 1.10.0 follows 1.9.0; string comparison does not. */
    public function isNewerThan(string $version): bool
    {
        return version_compare($this->version, $version, '>');
    }

    public function humanSize(): ?string
    {
        if (! $this->size) {
            return null;
        }

        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : max(1, (int) round($this->size / 1024)).' KB';
    }

    /** Case-insensitive match across the fields a person would search by. */
    public function matches(string $query): bool
    {
        $haystack = Str::lower(implode(' ', [
            $this->name, $this->slug, $this->description, $this->author, implode(' ', $this->tags),
        ]));

        foreach (preg_split('/\s+/', Str::lower(trim($query))) ?: [] as $word) {
            if ($word !== '' && ! str_contains($haystack, $word)) {
                return false;
            }
        }

        return true;
    }

    public function hasTag(string $tag): bool
    {
        return in_array(Str::lower($tag), $this->tags, true);
    }

    private static function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function linkUrl(mixed $value): ?string
    {
        $url = self::text($value);

        return $url !== null && preg_match('#^https?://[^\s/]+#i', $url) ? $url : null;
    }

    private static function imageUrl(mixed $value): ?string
    {
        $url = self::text($value);

        return $url !== null && preg_match('#^https://[^\s/]+#i', $url) ? $url : null;
    }

    private static function hash(mixed $value): ?string
    {
        $hash = strtolower((string) self::text($value));

        return preg_match('/^[a-f0-9]{64}$/', $hash) ? $hash : null;
    }

    /** @return string[] */
    private static function words(mixed $value): array
    {
        $words = is_array($value) ? $value : (is_string($value) ? explode(',', $value) : []);

        return array_values(array_unique(array_filter(array_map(
            fn ($word) => is_scalar($word) ? Str::lower(trim((string) $word)) : '',
            $words
        ))));
    }
}
