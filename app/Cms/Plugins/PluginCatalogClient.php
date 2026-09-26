<?php

namespace App\Cms\Plugins;

use App\Cms\Marketplace\CatalogClient;
use App\Models\Plugin;

/**
 * Reads the plugin directory: the same catalogue format as the theme
 * directory, from its own address (marketplace.plugins_catalogue_url).
 *
 * Installing a plugin is deploying code, so the directory is off wherever
 * plugin uploads are - a site that only takes plugins copied onto the server
 * by hand does not take them from the internet either.
 */
class PluginCatalogClient extends CatalogClient
{
    public function enabled(): bool
    {
        return parent::enabled() && (bool) config('cms.plugins.allow_upload', true);
    }

    protected function label(): string
    {
        return 'plugin directory';
    }

    /** Paid plugins are listed, with a link to where they are bought. */
    protected function includePaid(): bool
    {
        return true;
    }

    protected function urlConfigKey(): string
    {
        return 'marketplace.plugins_catalogue_url';
    }

    protected function cacheKey(): string
    {
        return 'cms.marketplace.plugins.catalog';
    }

    protected function checkedKey(): string
    {
        return 'cms.marketplace.plugins.checked_at';
    }

    /**
     * Newer versions of plugins installed from the directory. Only plugins
     * that came from it are looked up, so a site that never used it never
     * contacts it.
     *
     * @return array<string, \App\Cms\Marketplace\MarketplaceItem> keyed by installed plugin slug
     */
    public function availableUpdates(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $plugins = Plugin::where('source', Plugin::SOURCE_MARKETPLACE)->whereNotNull('source_slug')->get();

        if ($plugins->isEmpty() || ! ($catalog = $this->cached())) {
            return [];
        }

        $updates = [];

        foreach ($plugins as $plugin) {
            $item = $catalog->find($plugin->source_slug);

            if ($item && $item->isFree() && $item->isNewerThan((string) $plugin->version)) {
                $updates[$plugin->slug] = $item;
            }
        }

        return $updates;
    }
}
