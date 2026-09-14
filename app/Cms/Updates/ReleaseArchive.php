<?php

namespace App\Cms\Updates;

use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Unpacks a release archive into a staging directory.
 *
 * The traversal defences are the same ones the theme installer uses, for the
 * same reason: an archive entry named "../../.env" would otherwise write
 * wherever it liked. What is deliberately *different* is the file policy. A
 * theme ZIP is filtered to static assets precisely so it cannot ship PHP; a
 * core release is almost entirely PHP, so the filter here is the path
 * allowlist in config/updates.php instead - an entry that does not belong to a
 * directory the CMS ships is dropped on the floor.
 *
 * Nothing is written anywhere near the live site: everything lands in a staging
 * directory that the applier later copies from, so a bad archive is discarded
 * without having touched anything.
 */
class ReleaseArchive
{
    /** @var string[] Extensions never written, whatever the archive claims. */
    private const NEVER = ['exe', 'bat', 'cmd', 'com', 'scr', 'msi', 'dll', 'so', 'dylib'];

    /**
     * Extract to $destination, keeping only paths the update is allowed to
     * write, and return the staging root.
     *
     * @return array{root: string, files: string[], skipped: int}
     *
     * @throws UpdateException
     */
    public function extract(string $archivePath, string $destination): array
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new UpdateException('The downloaded release is not a readable ZIP archive.');
        }

        File::ensureDirectoryExists($destination);
        $realDestination = realpath($destination);

        try {
            $maxEntries = (int) config('updates.max_archive_entries', 30000);

            if ($zip->numFiles > $maxEntries) {
                throw new UpdateException('The release archive contains more files than this site will unpack.');
            }

            // A release is usually wrapped in a single folder ("custom-cms-1.1.0/").
            // Work out that prefix once so every entry can be judged against the
            // path allowlist by its real, project-relative name.
            $prefix = $this->detectPrefix($zip);

            $written = [];
            $skipped = 0;
            $totalBytes = 0;
            $maxBytes = (int) config('updates.max_uncompressed_bytes', 600 * 1024 * 1024);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $raw = $stat['name'];

                // Separators are normalised before anything else looks at the
                // name. A release zipped on Windows - by PowerShell, .NET or
                // several GUI tools - stores backslashes, and a directory entry
                // then ends in "\" rather than "/". Testing the raw name for a
                // trailing "/" would miss those and try to fopen a directory.
                $name = str_replace('\\', '/', $raw);

                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }

                if ($this->isTraversal($raw)) {
                    throw new UpdateException("The release archive contains an illegal path: {$raw}");
                }

                $relative = $this->strip($name, $prefix);

                if ($relative === null || ! $this->isWritablePath($relative)) {
                    $skipped++;

                    continue;
                }

                $totalBytes += (int) $stat['size'];

                if ($maxBytes > 0 && $totalBytes > $maxBytes) {
                    throw new UpdateException('The release archive is too large once uncompressed.');
                }

                $target = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
                File::ensureDirectoryExists(dirname($target));

                // Re-checked once the directory exists: realpath only resolves
                // paths that are present, and this is what catches a symlinked
                // parent pointing outside the staging area.
                $realParent = realpath(dirname($target));

                if ($realParent === false || ! str_starts_with($realParent, $realDestination)) {
                    throw new UpdateException("The release archive contains an illegal path: {$raw}");
                }

                // Opened by the name the archive actually uses, not the
                // normalised one.
                $stream = $zip->getStream($raw);

                if (! $stream) {
                    continue;
                }

                // Copied stream to stream: vendor/ holds files far too large to
                // reasonably hold in memory all at once.
                $out = fopen($target, 'wb');

                if ($out) {
                    stream_copy_to_stream($stream, $out);
                    fclose($out);
                    $written[] = $relative;
                }

                fclose($stream);
            }

            if ($written === []) {
                throw new UpdateException(
                    'The release archive contained nothing this CMS recognises. It may not be a release of this product.'
                );
            }

            return ['root' => $destination, 'files' => $written, 'skipped' => $skipped];
        } finally {
            $zip->close();
        }
    }

    /**
     * Confirm the staged tree really is the release it claims to be.
     *
     * The manifest is just a JSON file on a web server; the archive is the
     * thing that will be installed. If the two disagree about the version,
     * something is wrong and guessing which to believe is not an option.
     *
     * @throws UpdateException
     */
    public function verify(string $root, ReleaseManifest $manifest): void
    {
        foreach (['app', 'config/cms.php', 'vendor/autoload.php'] as $required) {
            if (! file_exists($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $required))) {
                throw new UpdateException(
                    "The release archive is missing {$required}, so it is not a complete release of this product. "
                    .'Nothing on your site was changed.'
                );
            }
        }

        $version = $this->versionInArchive($root);

        if ($version === null) {
            throw new UpdateException('The release archive does not declare a version. Nothing on your site was changed.');
        }

        if ($version !== $manifest->version) {
            throw new UpdateException(
                "The release archive contains version {$version}, but the update information promised "
                ."{$manifest->version}. Nothing on your site was changed."
            );
        }
    }

    /** Reads cms.version out of the staged config without executing anything else. */
    public function versionInArchive(string $root): ?string
    {
        $path = $root.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'cms.php';

        if (! is_file($path)) {
            return null;
        }

        // Matched with a regex rather than by including the file: including it
        // would run code from an archive that has not been approved yet.
        if (preg_match("/'version'\s*=>\s*'([^']+)'/", (string) file_get_contents($path), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Whether a project-relative path belongs to something the CMS ships.
     *
     * This is the whole safety model for extraction: a release can only write
     * where config/updates.php says the CMS owns files. An entry for ".env" or
     * "storage/app/public/..." matches nothing and is silently dropped.
     */
    public function isWritablePath(string $relative): bool
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        if ($relative === '') {
            return false;
        }

        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        if (in_array($extension, self::NEVER, true)) {
            return false;
        }

        foreach (config('updates.paths.protected', []) as $protected) {
            if ($this->pathMatches($relative, $protected)) {
                return false;
            }
        }

        foreach (['replace', 'merge'] as $group) {
            foreach (config("updates.paths.{$group}", []) as $allowed) {
                if ($this->pathMatches($relative, $allowed)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** True when $relative is $candidate itself or sits underneath it. */
    private function pathMatches(string $relative, string $candidate): bool
    {
        $candidate = trim(str_replace('\\', '/', $candidate), '/');

        return $relative === $candidate || str_starts_with($relative, $candidate.'/');
    }

    /**
     * The common wrapper folder, if the archive has one.
     *
     * Returns '' when entries already sit at the root, so the same code handles
     * both a hand-made ZIP and one produced by a release pipeline.
     */
    private function detectPrefix(ZipArchive $zip): string
    {
        $markers = ['config/cms.php', 'vendor/autoload.php', 'artisan'];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', $zip->statIndex($i)['name']);

            foreach ($markers as $marker) {
                if ($name === $marker) {
                    return '';
                }

                if (str_ends_with($name, '/'.$marker)) {
                    return substr($name, 0, strlen($name) - strlen($marker));
                }
            }
        }

        return '';
    }

    /** Strip the wrapper folder; null when the entry is outside it. */
    private function strip(string $name, string $prefix): ?string
    {
        $name = str_replace('\\', '/', $name);

        if ($prefix === '') {
            return $name;
        }

        return str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : null;
    }

    /** Kept identical to the theme installer's check; it has the same job here. */
    private function isTraversal(string $name): bool
    {
        $normalised = str_replace('\\', '/', $name);

        return str_starts_with($normalised, '/')
            || str_contains($normalised, '../')
            || str_contains($normalised, '..\\')
            || preg_match('/^[a-zA-Z]:/', $normalised) === 1
            || str_contains($name, "\0");
    }
}
