<?php

namespace App\Cms\Embeds;

/**
 * A pasted link, taken apart once.
 *
 * Providers are matched on the host and read the path and query, so every one
 * of them would otherwise start by calling parse_url again. Doing it here also
 * means the awkward parts - a "www." prefix, a mobile subdomain, a query string
 * with the interesting value buried in the middle - are dealt with in one place.
 */
final class EmbedSource
{
    private function __construct(
        public readonly string $url,
        public readonly string $host,
        public readonly string $path,
        public readonly array $query,
        public readonly string $fragment,
    ) {}

    /** Null when the link is not an absolute http(s) address. */
    public static function from(string $url): ?self
    {
        $url = trim($url);

        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || blank($parts['host'] ?? null)) {
            return null;
        }

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);

        return new self(
            url: $url,
            host: self::normaliseHost($parts['host']),
            path: '/'.trim((string) ($parts['path'] ?? ''), '/'),
            query: $query,
            fragment: (string) ($parts['fragment'] ?? ''),
        );
    }

    /**
     * The host without the prefixes that only pick a front end: www., m. and
     * the mobile/regional subdomains the share sheets hand out. A link copied
     * from a phone has to resolve to the same provider as one copied from a
     * desktop.
     */
    public static function normaliseHost(string $host): string
    {
        $host = strtolower(rtrim($host, '.'));

        return (string) preg_replace('/^(?:www|m|mobile)\./', '', $host);
    }

    public function query(string $key, ?string $pattern = null): ?string
    {
        $value = $this->query[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $pattern === null || preg_match($pattern, $value) ? $value : null;
    }

    /** Path segments, with the empty leading one dropped. */
    public function segments(): array
    {
        return $this->path === '/' ? [] : explode('/', ltrim($this->path, '/'));
    }

    public function segment(int $index, ?string $pattern = null): ?string
    {
        $value = $this->segments()[$index] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return $pattern === null || preg_match($pattern, $value) ? $value : null;
    }

    public function pathMatches(string $pattern): ?array
    {
        return preg_match($pattern, $this->path, $matches) ? $matches : null;
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }
}
