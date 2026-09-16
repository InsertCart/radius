<?php

namespace App\Cms\Updates;

use App\Cms\Support\DownloadRefused;
use App\Cms\Support\VerifiedDownload;

/**
 * Fetches the release archive to local disk and proves it is the file the
 * manifest described.
 *
 * The checks themselves live in VerifiedDownload, shared with the theme
 * marketplace. This class supplies the update settings and the wording: the
 * messages below are shown to site owners verbatim and are pinned by
 * ReleaseDownloaderTest, so change them deliberately or not at all.
 */
class ReleaseDownloader
{
    public function __construct(private VerifiedDownload $downloader) {}

    /**
     * @return string absolute path to the verified archive
     *
     * @throws UpdateException
     */
    public function download(ReleaseManifest $manifest, ?string $directory = null): string
    {
        $directory ??= storage_path('app/private/'.trim((string) config('updates.workspace', 'updates'), '/'));

        try {
            return $this->downloader->fetch(
                $manifest->downloadUrl,
                $manifest->sha256,
                $directory,
                'release-'.$manifest->version,
                [
                    'require_https' => (bool) config('updates.require_https', true),
                    'require_checksum' => (bool) config('updates.require_checksum', true),
                    'max_bytes' => (int) config('updates.max_download_bytes', 300 * 1024 * 1024),
                    'timeout' => (int) config('updates.http_timeout', 900),
                ],
            );
        } catch (DownloadRefused $e) {
            throw new UpdateException($this->message($e), previous: $e);
        }
    }

    private function message(DownloadRefused $e): string
    {
        return match ($e->reason) {
            DownloadRefused::MISSING_URL => 'This release does not say where to download it from.',
            DownloadRefused::INVALID_URL => 'The download address for this release is not a valid web address.',
            DownloadRefused::INSECURE_URL => 'This release is offered over an insecure http:// address. Because the download becomes program '
                .'code on your server, only https:// is accepted.',
            DownloadRefused::MISSING_CHECKSUM => 'This release does not publish a SHA-256 checksum, so there is no way to tell whether the '
                .'download arrived intact or was tampered with. The update was not applied.',
            DownloadRefused::WORKSPACE => 'The update folder could not be created. Check that storage/app is writable.',
            DownloadRefused::HTTP_ERROR => "The download failed: the server answered with an error ({$e->context['status']}).",
            DownloadRefused::EMPTY_FILE => 'The download produced an empty file.',
            DownloadRefused::TOO_LARGE => 'The downloaded release is larger than this site allows ('
                .number_format($e->context['bytes'] / 1048576, 1).' MB). The update was not applied.',
            DownloadRefused::CHECKSUM_MISMATCH => 'The downloaded file does not match the checksum published for this release, so it was discarded. '
                .'The download may have been corrupted, or the file may have been replaced. Nothing on your site was changed.',
            default => 'The release could not be downloaded. Check your connection and try again.',
        };
    }
}
