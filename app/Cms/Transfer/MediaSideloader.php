<?php

namespace App\Cms\Transfer;

use App\Cms\Media\MediaService;
use App\Cms\Media\UploadRejected;
use App\Cms\Support\DownloadRefused;
use App\Cms\Support\VerifiedDownload;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Puts a file that came from somewhere else into the media library.
 *
 * Two sources, one destination. A Radius bundle carries its files, so they are
 * copied out of the archive; a WordPress export only names them, so they are
 * fetched from the old site. Either way the bytes go through the ordinary
 * upload path - the same guard, the same re-encoding, the same thumbnails - so
 * an import cannot put anything on the disk that an upload could not.
 *
 * That matters more than it sounds. An import file is written by someone else,
 * and "it came from an export" is not a reason to trust a .php named .jpg.
 */
class MediaSideloader
{
    /** Remote addresses already fetched this run, keyed by URL. */
    private array $downloaded = [];

    public function __construct(
        private MediaService $media,
        private VerifiedDownload $downloads,
    ) {}

    /** An existing library record for a path an export recorded, if this site has one. */
    public function existingByPath(?string $path): ?Media
    {
        return filled($path) ? Media::where('path', $path)->first() : null;
    }

    /**
     * Stores a file already on this disk - unpacked from a bundle, or sitting
     * in an uploads folder somebody copied across.
     *
     * $preferredPath is the address the file had on the old site. Only its
     * folder is honoured, so a library exported from "2026/09" comes back in
     * "2026/09"; the filename is regenerated, because a name from an archive
     * has no business steering where bytes land.
     */
    public function fromFile(string $absolutePath, string $originalName, ?string $preferredPath = null, ?int $userId = null): ?Media
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $name = Str::limit(pathinfo($originalName, PATHINFO_FILENAME), 80, '').($extension ? '.'.$extension : '');

        // UploadedFile in test mode: the bytes are real, they just did not
        // arrive through a POST. Everything downstream treats it identically.
        $upload = new UploadedFile($absolutePath, $name, null, null, true);

        $folder = $preferredPath ? trim((string) pathinfo($preferredPath, PATHINFO_DIRNAME), './') : null;

        try {
            return $this->media->store($upload, $folder ?: null, $userId);
        } catch (UploadRejected $e) {
            throw new MediaRefused($e->getMessage(), previous: $e);
        }
    }

    /**
     * Fetches a remote file into the library.
     *
     * The address comes out of somebody else's export file, which makes this a
     * request this server is told to make by a stranger. VerifiedDownload is
     * reused rather than reimplemented precisely because it already refuses
     * private and link-local hosts, on the first address and on every redirect.
     *
     * @throws MediaRefused when the file cannot be fetched or is not storable.
     */
    public function fromUrl(string $url, ?int $userId = null): ?Media
    {
        if (isset($this->downloaded[$url])) {
            return Media::find($this->downloaded[$url]);
        }

        $config = config('transfer.download', []);

        $workspace = TransferWorkspace::path('downloads');
        File::ensureDirectoryExists($workspace);

        try {
            $archive = $this->downloads->fetch($url, null, $workspace, 'media', [
                'require_https' => false,
                'require_checksum' => false,
                'max_bytes' => (int) ($config['max_bytes'] ?? 25 * 1024 * 1024),
                'timeout' => (int) ($config['timeout'] ?? 30),
                'block_private_hosts' => (bool) ($config['block_private_hosts'] ?? true),
            ]);
        } catch (DownloadRefused $e) {
            throw new MediaRefused($e->getMessage(), previous: $e);
        }

        // VerifiedDownload names everything .zip because everything it was
        // written for is an archive. Give the file back the extension its
        // address claims, so the guard can compare name against contents.
        $name = $this->filenameFor($url);
        $target = $workspace.DIRECTORY_SEPARATOR.Str::random(8).'-'.$name;

        @rename($archive, $target) || @copy($archive, $target);
        @unlink($archive);

        try {
            $media = $this->fromFile($target, $name, $this->pathFromUrl($url), $userId);
        } finally {
            @unlink($target);
        }

        if ($media) {
            $this->downloaded[$url] = $media->id;
        }

        return $media;
    }

    /** A safe local filename for a remote address. */
    private function filenameFor(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $safe = Str::limit(Str::slug($base) ?: 'file', 60, '');

        // No extension in the address is not a reason to guess one: the guard
        // compares the name against the contents, and an honest ".bin" will be
        // refused rather than quietly stored as something it is not.
        return $extension ? $safe.'.'.preg_replace('/[^a-z0-9]/', '', $extension) : $safe.'.bin';
    }

    /**
     * The folder part of a WordPress upload address, so an import lands in the
     * same "2019/04" shape the old site used.
     */
    private function pathFromUrl(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (preg_match('#/uploads/((?:\d{4}/\d{2}/)?)#', $path, $matches) && $matches[1] !== '') {
            return trim($matches[1], '/').'/file';
        }

        return null;
    }
}
