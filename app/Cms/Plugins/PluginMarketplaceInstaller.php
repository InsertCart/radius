<?php

namespace App\Cms\Plugins;

use App\Cms\Marketplace\MarketplaceException;
use App\Cms\Marketplace\MarketplaceItem;
use App\Cms\Support\DownloadRefused;
use App\Cms\Support\VerifiedDownload;
use App\Models\Plugin;
use Illuminate\Support\Facades\Log;

/**
 * Installs, or updates, a free plugin listed in the plugin directory.
 *
 * Adds nothing to what a plugin may contain: the archive is downloaded, proved
 * to be the file the catalogue published (https and SHA-256, both required),
 * and handed to PluginInstaller - the same code a hand upload goes through.
 *
 * Paid plugins are listed with a price and a purchase link. They are bought on
 * the publisher's site and uploaded by hand; this class refuses them.
 */
class PluginMarketplaceInstaller
{
    public function __construct(
        private VerifiedDownload $downloader,
        private PluginInstaller $installer,
        private PluginManager $plugins,
    ) {}

    /**
     * Whether installing this item would replace a plugin already here, and
     * whether that is allowed. Only a plugin that itself came from the
     * directory under the same listing may be replaced: one uploaded by hand
     * may be a different plugin that happens to share the name, or carry the
     * owner's own changes.
     *
     * @return array{installed: ?Plugin, is_update: bool, blocked: ?string}
     */
    public function status(MarketplaceItem $item): array
    {
        $installed = Plugin::where('slug', $item->slug)->first();
        $onDisk = is_dir($this->plugins->path($item->slug));

        if (! $installed && ! $onDisk) {
            return ['installed' => null, 'is_update' => false, 'blocked' => null];
        }

        if ($installed?->isFromMarketplace() && $installed->source_slug === $item->slug) {
            return ['installed' => $installed, 'is_update' => $item->isNewerThan((string) $installed->version), 'blocked' => null];
        }

        return [
            'installed' => $installed,
            'is_update' => false,
            'blocked' => "A plugin using the folder name [{$item->slug}] is already on this site and was not installed from the "
                .'plugin directory, so it will not be replaced. Uninstall it first if you want the directory version.',
        ];
    }

    /**
     * @return array{slug: string, name: string, version: string, updated: bool}
     *
     * @throws MarketplaceException
     * @throws PluginInstallException
     */
    public function install(MarketplaceItem $item): array
    {
        if (! $item->isFree()) {
            throw new MarketplaceException("{$item->name} is a paid plugin. Buy it from its publisher, then upload the ZIP on the Plugins screen.");
        }

        if (! $item->isCompatible()) {
            throw new MarketplaceException("{$item->name} needs version {$item->requires} of the CMS or newer. Update the CMS first.");
        }

        $status = $this->status($item);

        if ($status['blocked']) {
            throw new MarketplaceException($status['blocked']);
        }

        $replacing = $status['installed'] !== null;

        if ($replacing && ! $status['is_update']) {
            throw new MarketplaceException("{$item->name} is already installed and up to date.");
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $archive = $this->download($item);

        try {
            $result = $this->installer->installFromArchive($archive, overwrite: $replacing, expectedSlug: $item->slug);
        } finally {
            @unlink($archive);
        }

        Plugin::where('slug', $result['slug'])->update([
            'source' => Plugin::SOURCE_MARKETPLACE,
            'source_slug' => $item->slug,
        ]);

        if (version_compare($result['version'], $item->version, '!=')) {
            Log::warning("[marketplace] Plugin {$item->slug} is listed as {$item->version} but its plugin.json says {$result['version']}.");
        }

        return $result;
    }

    /** @throws MarketplaceException */
    private function download(MarketplaceItem $item): string
    {
        try {
            return $this->downloader->fetch(
                $item->downloadUrl,
                $item->sha256,
                storage_path('app/private/marketplace'),
                'plugin-'.$item->slug,
                [
                    // Not configurable here, unlike themes: a plugin is PHP,
                    // and an unverified plugin is an unverified deployment.
                    'require_https' => true,
                    'require_checksum' => true,
                    'max_bytes' => (int) config('marketplace.plugins_max_download_bytes', 20 * 1024 * 1024),
                    'timeout' => (int) config('marketplace.http_timeout', 120),
                    'allowed_hosts' => (array) config('marketplace.allowed_download_hosts', []),
                    'block_private_hosts' => true,
                ],
            );
        } catch (DownloadRefused $e) {
            throw new MarketplaceException(match ($e->reason) {
                DownloadRefused::MISSING_URL => "The directory does not say where to download {$item->name} from.",
                DownloadRefused::INSECURE_URL, DownloadRefused::INVALID_URL => "{$item->name} is not offered over a valid https:// address, so it was not downloaded.",
                DownloadRefused::BLOCKED_HOST => "{$item->name} would be downloaded from an address this site does not allow. Nothing was installed.",
                DownloadRefused::MISSING_CHECKSUM => "The directory publishes no SHA-256 checksum for {$item->name}, so the download cannot be verified. Nothing was installed.",
                DownloadRefused::CHECKSUM_MISMATCH => "The download does not match the checksum published for {$item->name}, so it was discarded. Nothing on your site was changed.",
                DownloadRefused::TOO_LARGE => "{$item->name} is larger than this site allows. Nothing was installed.",
                DownloadRefused::WORKSPACE => 'The download folder could not be created. Check that storage/app is writable.',
                DownloadRefused::HTTP_ERROR => "The download failed: the server answered with an error ({$e->context['status']}).",
                default => "{$item->name} could not be downloaded. Check your connection and try again.",
            });
        }
    }
}
