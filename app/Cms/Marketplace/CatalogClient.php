<?php

namespace App\Cms\Marketplace;

use App\Cms\Support\JsonDocument;
use App\Models\Theme;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads the theme directory from the publisher's catalogue file.
 *
 * Shaped like UpdateChecker, and for the same reasons: the answer is cached,
 * and a failure is never fatal - a directory server that is down should cost an
 * admin page nothing but the directory itself.
 *
 * What leaves the site is one GET for the catalogue, carrying the product name
 * and version as the user agent. No site address, no email, and no list of what
 * is installed. A site that never uses the marketplace never makes the request
 * at all: updates are only looked up for themes that were installed from it.
 */
class CatalogClient
{
    private const CACHE_KEY = 'cms.marketplace.catalog';
    private const CHECKED_KEY = 'cms.marketplace.checked_at';

    public function enabled(): bool
    {
        return (bool) config('marketplace.enabled', true) && filled($this->url());
    }

    public function url(): ?string
    {
        $url = trim((string) config('marketplace.catalogue_url', ''));

        return $url !== '' ? $url : null;
    }

    /**
     * The cached catalogue, refreshed at most once per interval. Never throws:
     * null means "no directory to show right now", and lastError() says why.
     */
    public function cached(): ?Catalog
    {
        if (! $this->enabled()) {
            return null;
        }

        $payload = Cache::remember(self::CACHE_KEY, $this->ttl(), function () {
            try {
                $catalog = $this->fetch();
                Cache::put(self::CHECKED_KEY, now()->toIso8601String(), now()->addYear());

                return ['data' => $catalog->raw, 'error' => null];
            } catch (\Throwable $e) {
                Log::warning('[marketplace] Could not load the theme directory: '.$e->getMessage());

                // Cached as a failure so an unreachable directory is not
                // retried on every page load. Only messages written for site
                // owners are kept for display.
                return [
                    'data' => null,
                    'error' => $e instanceof MarketplaceException
                        ? $e->getMessage()
                        : 'The theme directory could not be loaded.',
                ];
            }
        });

        if (! is_array($payload) || ! is_array($payload['data'] ?? null)) {
            return null;
        }

        try {
            return Catalog::fromArray($payload['data']);
        } catch (MarketplaceException $e) {
            return null;
        }
    }

    /** Why cached() came back empty, if it did. */
    public function lastError(): ?string
    {
        $payload = Cache::get(self::CACHE_KEY);

        return is_array($payload) ? ($payload['error'] ?? null) : null;
    }

    /**
     * Fetch the catalogue now, ignoring the cache. Throws, because it backs the
     * Refresh button, where the admin is entitled to know what went wrong.
     *
     * @throws MarketplaceException
     */
    public function fetch(): Catalog
    {
        $url = $this->url();

        if (! $url) {
            throw new MarketplaceException('No theme directory address has been configured. Set CMS_MARKETPLACE_URL in your .env file.');
        }

        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https' && config('marketplace.require_https', true)) {
            throw new MarketplaceException('The theme directory address must start with https://.');
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withUserAgent(config('cms.name', 'CMS').'/'.cms_version())
                ->get($url);
        } catch (\Throwable $e) {
            throw new MarketplaceException('The theme directory could not be reached. Check your connection and try again.');
        }

        if ($response->failed()) {
            throw new MarketplaceException("The theme directory answered with an error ({$response->status()}).");
        }

        $data = JsonDocument::decode($response->body());

        if (! is_array($data)) {
            throw new MarketplaceException('The theme directory address did not return a valid catalogue. Check that it points at a JSON file.');
        }

        return Catalog::fromArray($data);
    }

    /** Fetch, cache and return. Backs the Refresh button. */
    public function refresh(): Catalog
    {
        $catalog = $this->fetch();

        Cache::put(self::CACHE_KEY, ['data' => $catalog->raw, 'error' => null], $this->ttl());
        Cache::put(self::CHECKED_KEY, now()->toIso8601String(), now()->addYear());

        return $catalog;
    }

    public function lastCheckedAt(): ?string
    {
        return Cache::get(self::CHECKED_KEY);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Newer versions available for installed marketplace themes.
     *
     * Asks the database first and only reads the catalogue when there is a
     * marketplace theme to check, so this is safe to call from the sidebar on
     * every page without making a site that never used the marketplace
     * contact anybody.
     *
     * @return array<string, MarketplaceItem> keyed by installed theme slug
     */
    public function availableUpdates(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $themes = Theme::where('source', Theme::SOURCE_MARKETPLACE)->whereNotNull('source_slug')->get();

        if ($themes->isEmpty()) {
            return [];
        }

        $catalog = $this->cached();

        if (! $catalog) {
            return [];
        }

        $updates = [];

        foreach ($themes as $theme) {
            $item = $catalog->find($theme->source_slug);

            if ($item && $item->isNewerThan((string) $theme->version)) {
                $updates[$theme->slug] = $item;
            }
        }

        return $updates;
    }

    private function ttl(): \DateTimeInterface
    {
        return now()->addHours(max(1, (int) config('marketplace.cache_hours', 12)));
    }
}
