<?php

namespace App\Cms\Transfer;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The private scratch directory imports and exports work in.
 *
 * Everything here is either half-finished or somebody else's data, and both
 * are reasons for it to live under storage/app/private rather than anywhere a
 * web server will serve. An uploaded bundle in particular is a file a browser
 * handed over: served back from a public path it would be a stored XSS with
 * extra steps.
 */
class TransferWorkspace
{
    /** Absolute path to a sub-folder of the workspace, created if missing. */
    public static function path(string $folder = ''): string
    {
        $root = rtrim((string) config('transfer.workspace', storage_path('app/private/transfer')), '/'.DIRECTORY_SEPARATOR);

        $path = $folder === '' ? $root : $root.DIRECTORY_SEPARATOR.trim($folder, '/');

        File::ensureDirectoryExists($path);

        return $path;
    }

    /** A fresh, empty directory for one run to work in. */
    public static function scratch(string $prefix): string
    {
        $path = self::path('work').DIRECTORY_SEPARATOR.$prefix.'-'.Str::random(10);

        File::ensureDirectoryExists($path);

        return $path;
    }

    public static function file(string $folder, string $name): string
    {
        return self::path($folder).DIRECTORY_SEPARATOR.$name;
    }

    /**
     * Deletes uploads and scratch directories older than the configured age.
     *
     * Called before each new upload rather than on a schedule, because a
     * self-hosted site may well have no scheduler running at all - and an
     * abandoned 200 MB import should not sit on somebody's disk forever
     * because they closed the tab.
     */
    public static function sweep(): void
    {
        $hours = (int) config('transfer.keep_uploads_hours', 24);
        $cutoff = now()->subHours(max(1, $hours))->getTimestamp();

        foreach (['uploads', 'work', 'downloads', 'exports'] as $folder) {
            $path = self::path($folder);

            foreach (File::directories($path) as $directory) {
                if (@filemtime($directory) < $cutoff) {
                    File::deleteDirectory($directory);
                }
            }

            foreach (File::files($path) as $file) {
                if ($file->getMTime() < $cutoff) {
                    @unlink($file->getPathname());
                }
            }
        }
    }
}
