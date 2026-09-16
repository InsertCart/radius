<?php

namespace App\Cms\Updates;

use App\Cms\Support\JsonDocument;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks the seller's manifest whether anything newer exists.
 *
 * The answer is cached, because this runs off an ordinary admin page load and
 * nobody needs to hear about a release within the hour. A failed check is
 * never fatal: a site whose update server is down should carry on working and
 * simply not offer an update.
 */
class UpdateChecker
{
    private const CACHE_KEY = 'cms.updates.latest';

    public function enabled(): bool
    {
        return (bool) config('updates.enabled', true) && filled($this->url());
    }

    public function url(): ?string
    {
        $url = trim((string) config('updates.manifest_url', ''));

        return $url !== '' ? $url : null;
    }

    public function currentVersion(): string
    {
        return cms_version();
    }

    /**
     * The cached view of the manifest, refreshed at most once per interval.
     *
     * Returns null when updates are switched off, the server could not be
     * reached, or the document made no sense - all of which are "carry on
     * without an update", not errors to show anybody.
     */
    public function cached(): ?ReleaseManifest
    {
        if (! $this->enabled()) {
            return null;
        }

        $hours = max(1, (int) config('updates.check_interval_hours', 24));

        $payload = Cache::remember(self::CACHE_KEY, now()->addHours($hours), function () {
            try {
                return ['data' => $this->fetch()->raw, 'failed' => false];
            } catch (\Throwable $e) {
                Log::warning('[updates] Could not check for updates: '.$e->getMessage());

                // Cached as a failure so a dead update server is not retried on
                // every single admin page load.
                return ['data' => null, 'failed' => true];
            }
        });

        if (! is_array($payload) || blank($payload['data'] ?? null)) {
            return null;
        }

        try {
            return ReleaseManifest::fromArray($payload['data']);
        } catch (UpdateException $e) {
            return null;
        }
    }

    /**
     * Fetch the manifest right now, ignoring the cache.
     *
     * Unlike cached(), this throws - it backs the "Check for updates" button,
     * where the admin is entitled to know exactly why nothing happened.
     *
     * @throws UpdateException
     */
    public function fetch(): ReleaseManifest
    {
        $url = $this->url();

        if (! $url) {
            throw new UpdateException('No update address has been configured. Set CMS_UPDATE_URL in your .env file.');
        }

        try {
            $response = $this->http()->get($url);
        } catch (\Throwable $e) {
            throw new UpdateException('The update server could not be reached. Check the address and try again.');
        }

        if ($response->failed()) {
            throw new UpdateException("The update server answered with an error ({$response->status()}).");
        }

        // Forgives a byte-order mark or UTF-16, which hand-edited files often carry.
        $data = JsonDocument::decode($response->body());

        if (! is_array($data)) {
            throw new UpdateException(
                'The update address did not return valid update information. Check that it points at a JSON file.'
            );
        }

        return ReleaseManifest::fromArray($data);
    }

    /** Fetch, cache the result, and return it. Backs the manual check button. */
    public function refresh(): ReleaseManifest
    {
        $manifest = $this->fetch();

        Cache::put(
            self::CACHE_KEY,
            ['data' => $manifest->raw, 'failed' => false],
            now()->addHours(max(1, (int) config('updates.check_interval_hours', 24)))
        );

        Cache::put('cms.updates.checked_at', now()->toIso8601String(), now()->addYear());

        return $manifest;
    }

    public function lastCheckedAt(): ?string
    {
        return Cache::get('cms.updates.checked_at');
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** True when a newer version is waiting. Safe to call on any page load. */
    public function updateAvailable(): bool
    {
        $manifest = $this->cached();

        return $manifest !== null && $manifest->isNewerThan($this->currentVersion());
    }

    /**
     * Short timeout and no retry: this is a background courtesy check, and an
     * unreachable update server must never hold up an admin page.
     */
    private function http(): PendingRequest
    {
        return Http::timeout(15)->acceptJson()->withUserAgent(
            config('cms.name', 'CMS').'/'.cms_version()
        );
    }
}
