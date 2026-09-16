<?php

namespace App\Cms\Marketplace;

use App\Cms\Support\DownloadRefused;
use App\Cms\Support\VerifiedDownload;
use App\Cms\Themes\ThemeInstaller;
use App\Cms\Themes\ThemeManager;
use App\Models\Theme;
use Illuminate\Support\Facades\Log;

/**
 * Installs, or updates, a theme listed in the marketplace.
 *
 * This class adds nothing to what a theme is allowed to contain. It downloads
 * the archive, proves it is the file the catalogue published, and then hands it
 * to ThemeInstaller::installFromArchive() - the same method a hand-uploaded ZIP
 * goes through - so the extraction rules and the template scan are identical.
 *
 * It runs inside the request. A theme is a few megabytes of templates and
 * assets with no migrations and no vendor code, so none of the two-step
 * apply-then-finalise care a core update needs applies here.
 */
class MarketplaceInstaller
{
    public function __construct(
        private VerifiedDownload $downloader,
        private ThemeInstaller $installer,
        private ThemeManager $themes,
    ) {}

    /**
     * What stands between this server and the install, in the same shape the
     * updater uses: [passed, fatal, label, detail].
     *
     * @return array<int, array{passed: bool, fatal: bool, label: string, detail: string}>
     */
    public function preflight(MarketplaceItem $item): array
    {
        $checks = [];

        $checks[] = $this->check(
            'ZIP support',
            class_exists(\ZipArchive::class),
            'PHP\'s zip extension is not installed, so theme archives cannot be opened. Ask your host to enable it.'
        );

        $themesPath = $this->themes->path();
        $checks[] = $this->check(
            'Themes folder is writable',
            $this->writable($themesPath),
            'The themes folder cannot be written to. Give the web server write access to it.'
        );

        $assetsPath = public_path(config('cms.themes.asset_url', 'themes'));
        $checks[] = $this->check(
            'Public theme assets folder is writable',
            $this->writable($assetsPath),
            'The public themes folder cannot be written to, so the theme\'s stylesheets could not be published.'
        );

        $needed = 3 * ($item->size ?: 10 * 1024 * 1024);
        $free = @disk_free_space(storage_path());
        $checks[] = $this->check(
            'Enough free disk space',
            ! is_float($free) || $free >= $needed,
            'There is not enough free disk space to download and unpack this theme.'
        );

        $limit = (int) ini_get('max_execution_time');
        $checks[] = $this->check(
            'Enough time to finish',
            $limit === 0 || $limit >= 60,
            "PHP stops requests after {$limit} seconds. A slow connection may not finish the download in time.",
            fatal: false,
        );

        return $checks;
    }

    public function preflightPasses(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['fatal'] && ! $check['passed']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether installing this item would replace a theme already on the site,
     * and whether that is allowed. Only a theme that itself came from the
     * marketplace under the same listing may be replaced; anything uploaded by
     * hand is left alone, because it may carry the site owner's own edits.
     *
     * @return array{installed: ?Theme, is_update: bool, blocked: ?string}
     */
    public function status(MarketplaceItem $item): array
    {
        $installed = Theme::where('slug', $item->slug)->first();
        $onDisk = is_dir($this->themes->path($item->slug));

        if (! $installed && ! $onDisk) {
            return ['installed' => null, 'is_update' => false, 'blocked' => null];
        }

        if ($installed?->isFromMarketplace() && $installed->source_slug === $item->slug) {
            return ['installed' => $installed, 'is_update' => $item->isNewerThan((string) $installed->version), 'blocked' => null];
        }

        return [
            'installed' => $installed,
            'is_update' => false,
            'blocked' => "A theme using the folder name [{$item->slug}] is already on this site and was not installed from the "
                .'theme directory, so it will not be replaced. Delete it first if you want the directory version.',
        ];
    }

    /**
     * @return array{slug: string, name: string, version: string, warnings: string[], updated: bool, was_active: bool}
     *
     * @throws MarketplaceException
     * @throws \App\Cms\Themes\ThemeInstallException
     */
    public function install(MarketplaceItem $item): array
    {
        if (! $item->isFree()) {
            throw new MarketplaceException('This theme is not free, and this release of the CMS can only install free themes.');
        }

        if (! $item->isCompatible()) {
            throw new MarketplaceException(
                "{$item->name} needs version {$item->requires} of the CMS or newer. Update the CMS first."
            );
        }

        $status = $this->status($item);

        if ($status['blocked']) {
            throw new MarketplaceException($status['blocked']);
        }

        $replacing = $status['installed'] !== null;

        if ($replacing && ! $status['is_update']) {
            throw new MarketplaceException("{$item->name} is already installed and up to date.");
        }

        $checks = $this->preflight($item);

        if (! $this->preflightPasses($checks)) {
            $failed = collect($checks)->first(fn ($c) => $c['fatal'] && ! $c['passed']);

            throw new MarketplaceException($failed['detail']);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $archive = $this->download($item);

        try {
            $result = $this->installer->installFromArchive($archive, $item->slug, overwrite: $replacing, expectedSlug: $item->slug);
        } finally {
            @unlink($archive);
        }

        $wasActive = (bool) $status['installed']?->is_active;

        Theme::where('slug', $result['slug'])->update([
            'source' => Theme::SOURCE_MARKETPLACE,
            'source_slug' => $item->slug,
            'installed_at' => now(),
        ]);

        // The checksum proves the archive is the one listed, not that the
        // listing's version number matches what is inside it. Left unmentioned,
        // a mismatch would show "update available" forever.
        if (version_compare($result['version'], $item->version, '!=')) {
            Log::warning("[marketplace] {$item->slug} is listed as {$item->version} but its theme.json says {$result['version']}.");

            $result['warnings'][] = "The directory lists this theme as version {$item->version}, but the theme itself says "
                ."{$result['version']}. Let the theme's publisher know.";
        }

        return $result + ['updated' => $replacing, 'was_active' => $wasActive];
    }

    /** @throws MarketplaceException */
    private function download(MarketplaceItem $item): string
    {
        try {
            return $this->downloader->fetch(
                $item->downloadUrl,
                $item->sha256,
                storage_path('app/private/marketplace'),
                'theme-'.$item->slug,
                [
                    'require_https' => (bool) config('marketplace.require_https', true),
                    'require_checksum' => (bool) config('marketplace.require_checksum', true),
                    'max_bytes' => (int) config('marketplace.max_download_bytes', 40 * 1024 * 1024),
                    'timeout' => (int) config('marketplace.http_timeout', 120),
                    'allowed_hosts' => (array) config('marketplace.allowed_download_hosts', []),
                    // The address comes from a document published elsewhere,
                    // so it may never point this server at its own network.
                    'block_private_hosts' => true,
                ],
            );
        } catch (DownloadRefused $e) {
            throw new MarketplaceException($this->message($e, $item));
        }
    }

    private function message(DownloadRefused $e, MarketplaceItem $item): string
    {
        return match ($e->reason) {
            DownloadRefused::MISSING_URL => "The directory does not say where to download {$item->name} from.",
            DownloadRefused::INVALID_URL => "The download address for {$item->name} is not a valid web address.",
            DownloadRefused::INSECURE_URL => "{$item->name} is offered over an insecure http:// address. A theme becomes code on "
                .'your server, so only https:// is accepted.',
            DownloadRefused::BLOCKED_HOST => "{$item->name} would be downloaded from an address this site does not allow "
                ."({$e->context['host']}). Nothing was installed.",
            DownloadRefused::MISSING_CHECKSUM => "The directory does not publish a SHA-256 checksum for {$item->name}, so there is "
                .'no way to tell whether the download was tampered with. Nothing was installed.',
            DownloadRefused::WORKSPACE => 'The download folder could not be created. Check that storage/app is writable.',
            DownloadRefused::HTTP_ERROR => "The download failed: the server answered with an error ({$e->context['status']}).",
            DownloadRefused::EMPTY_FILE => 'The download produced an empty file.',
            DownloadRefused::TOO_LARGE => "{$item->name} is larger than this site allows ("
                .number_format($e->context['bytes'] / 1048576, 1).' MB). Nothing was installed.',
            DownloadRefused::CHECKSUM_MISMATCH => "The downloaded file does not match the checksum published for {$item->name}, "
                .'so it was discarded. It may have been corrupted or replaced. Nothing on your site was changed.',
            default => "{$item->name} could not be downloaded. Check your connection and try again.",
        };
    }

    private function writable(string $path): bool
    {
        // A folder that does not exist yet is fine if it can be created.
        while (! file_exists($path) && dirname($path) !== $path) {
            $path = dirname($path);
        }

        return is_dir($path) && is_writable($path);
    }

    private function check(string $label, bool $passed, string $detail, bool $fatal = true): array
    {
        return ['passed' => $passed, 'fatal' => $fatal, 'label' => $label, 'detail' => $passed ? '' : $detail];
    }
}
