<?php

namespace App\Cms\Updates;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fetches the release archive to local disk and proves it is the file the
 * manifest described.
 *
 * Streamed to a file rather than held in memory: a full release runs to tens of
 * megabytes, and reading that into a string would exhaust the memory limit on
 * the shared hosting this CMS is most often installed on.
 *
 * The checksum is the point of this class. Everything inside the archive
 * becomes PHP that runs on this server, so "the download finished" is not the
 * same question as "is this the file the seller published".
 */
class ReleaseDownloader
{
    /**
     * @return string absolute path to the verified archive
     *
     * @throws UpdateException
     */
    public function download(ReleaseManifest $manifest, ?string $directory = null): string
    {
        $url = $this->assertUrl($manifest);

        if (config('updates.require_checksum', true) && blank($manifest->sha256)) {
            throw new UpdateException(
                'This release does not publish a SHA-256 checksum, so there is no way to tell whether the '
                .'download arrived intact or was tampered with. The update was not applied.'
            );
        }

        $directory ??= storage_path('app/private/'.trim((string) config('updates.workspace', 'updates'), '/'));

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new UpdateException('The update folder could not be created. Check that storage/app is writable.');
        }

        $path = $directory.DIRECTORY_SEPARATOR.'release-'.$manifest->version.'-'.Str::random(8).'.zip';

        $this->stream($url, $path);
        $this->assertSize($path);
        $this->assertChecksum($manifest, $path);

        return $path;
    }

    /** @throws UpdateException */
    private function assertUrl(ReleaseManifest $manifest): string
    {
        $url = $manifest->downloadUrl;

        if (blank($url)) {
            throw new UpdateException('This release does not say where to download it from.');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true) || blank(parse_url($url, PHP_URL_HOST))) {
            throw new UpdateException('The download address for this release is not a valid web address.');
        }

        // Plain HTTP means anyone between here and the server can replace the
        // archive with their own, and it would then run as PHP on this site.
        if ($scheme !== 'https' && config('updates.require_https', true)) {
            throw new UpdateException(
                'This release is offered over an insecure http:// address. Because the download becomes program '
                .'code on your server, only https:// is accepted.'
            );
        }

        return $url;
    }

    /** @throws UpdateException */
    private function stream(string $url, string $path): void
    {
        try {
            $response = Http::timeout((int) config('updates.http_timeout', 900))
                ->withUserAgent(config('cms.name', 'CMS').'/'.cms_version())
                ->withOptions(['sink' => $path])
                ->get($url);
        } catch (\Throwable $e) {
            @unlink($path);

            throw new UpdateException('The release could not be downloaded. Check your connection and try again.');
        }

        if ($response->failed()) {
            @unlink($path);

            throw new UpdateException("The download failed: the server answered with an error ({$response->status()}).");
        }

        if (! is_file($path) || filesize($path) === 0) {
            @unlink($path);

            throw new UpdateException('The download produced an empty file.');
        }
    }

    /** @throws UpdateException */
    private function assertSize(string $path): void
    {
        $max = (int) config('updates.max_download_bytes', 300 * 1024 * 1024);
        $size = (int) filesize($path);

        if ($max > 0 && $size > $max) {
            @unlink($path);

            throw new UpdateException(
                'The downloaded release is larger than this site allows ('
                .number_format($size / 1048576, 1).' MB). The update was not applied.'
            );
        }
    }

    /** @throws UpdateException */
    private function assertChecksum(ReleaseManifest $manifest, string $path): void
    {
        if (blank($manifest->sha256)) {
            return;
        }

        $actual = hash_file('sha256', $path);

        // hash_equals rather than ===: comparison time should not depend on
        // how much of the hash matched.
        if (! is_string($actual) || ! hash_equals($manifest->sha256, $actual)) {
            @unlink($path);

            throw new UpdateException(
                'The downloaded file does not match the checksum published for this release, so it was discarded. '
                .'The download may have been corrupted, or the file may have been replaced. Nothing on your site was changed.'
            );
        }
    }
}
