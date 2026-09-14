<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * Drops the cached artefacts that are derived from published content.
 *
 * The sitemap and the rendered menus are both expensive enough to cache for
 * hours, which would otherwise mean a newly published post is invisible to
 * search engines, and a renamed menu item keeps its old label, until the cache
 * happened to expire. Anything that can change either one flushes them on save.
 */
trait FlushesPublicCache
{
    /** Cache keys invalidated whenever this model changes. */
    protected static array $publicCacheKeys = [
        'cms.sitemap',
        'cms.menus.rendered',
        'cms.seo.redirects',
    ];

    public static function bootFlushesPublicCache(): void
    {
        $flush = fn () => static::flushPublicCache();

        static::saved($flush);
        static::deleted($flush);

        // Restoring a soft-deleted row puts it back in the sitemap.
        if (method_exists(static::class, 'restored')) {
            static::restored($flush);
        }
    }

    public static function flushPublicCache(): void
    {
        foreach (static::$publicCacheKeys as $key) {
            Cache::forget($key);
        }
    }
}
