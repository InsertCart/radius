<?php

namespace App\Cms\Plugins;

use App\Models\Plugin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Installs a plugin ZIP.
 *
 * A plugin is PHP, so unlike a theme there is no template scan to hide behind:
 * installing one is deploying code, which is why the upload is admin-only and
 * can be switched off altogether. What this class does guarantee is that the
 * archive lands where it says it will and nowhere else:
 *
 *   1. Entries that would escape the target directory are refused (zip slip).
 *   2. Only plugin file types are extracted. Server configuration (.htaccess,
 *      .user.ini), archives and binaries are dropped.
 *   3. Entry count and uncompressed size are capped against zip bombs.
 *   4. plugin.json is validated - slug, namespace, provider, CMS version -
 *      before anything moves into plugins/.
 *
 * A freshly installed plugin is switched off. Nothing in it runs until an
 * administrator turns it on.
 */
class PluginInstaller
{
    private const MAX_ENTRIES = 5000;

    private const MAX_UNCOMPRESSED_BYTES = 100 * 1024 * 1024;

    private const ALLOWED_EXTENSIONS = [
        'php', 'json', 'css', 'js', 'mjs', 'map', 'svg', 'png', 'jpg', 'jpeg',
        'gif', 'webp', 'avif', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'otf',
        'md', 'txt', 'csv', 'xml', 'stub', 'mp4', 'webm',
    ];

    public function __construct(private PluginManager $plugins) {}

    /**
     * @return array{slug: string, name: string, version: string, updated: bool}
     *
     * @throws PluginInstallException
     */
    public function installFromUpload(UploadedFile $file, bool $overwrite = false): array
    {
        return $this->installFromArchive($file->getRealPath(), $overwrite);
    }

    /**
     * @return array{slug: string, name: string, version: string, updated: bool}
     *
     * @throws PluginInstallException
     */
    /**
     * @param  string|null  $expectedSlug  refuse the archive unless it installs to this folder. The
     *                                     plugin directory passes the slug it listed, so an archive
     *                                     cannot overwrite some other installed plugin.
     */
    public function installFromArchive(string $archivePath, bool $overwrite = false, ?string $expectedSlug = null): array
    {
        $workspace = storage_path('app/plugin-install/'.Str::random(16));
        File::ensureDirectoryExists($workspace);

        try {
            $this->extract($archivePath, $workspace);

            $root = $this->locateRoot($workspace);
            $manifest = $this->plugins->readManifest($root);
            $slug = $manifest['slug'];

            if ($expectedSlug !== null && $slug !== $expectedSlug) {
                throw new PluginInstallException(
                    "This archive installs a plugin called [{$slug}], but it was listed as [{$expectedSlug}]. It was not installed."
                );
            }

            if ($reason = $this->plugins->incompatibility($manifest)) {
                throw new PluginInstallException($reason);
            }

            if (! is_file($root.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, substr($manifest['provider'], strlen($manifest['namespace']) + 1)).'.php')) {
                throw new PluginInstallException("The archive has no src/ file for its provider class {$manifest['provider']}.");
            }

            $destination = $this->plugins->path($slug);
            $updated = is_dir($destination);

            if ($updated && ! $overwrite) {
                throw new PluginInstallException(
                    "A plugin called [{$slug}] is already installed. Tick \"replace existing\" to update it."
                );
            }

            if ($updated) {
                File::deleteDirectory($destination);
            }

            $this->plugins->ensureFolders();
            File::moveDirectory($root, $destination);

            $this->plugins->sync();

            // An update of a plugin that is running brings its schema along
            // with its code, so the new version never meets an old table.
            $plugin = Plugin::where('slug', $slug)->first();

            if ($updated && $plugin?->enabled) {
                $this->plugins->migrate($plugin);
                $this->plugins->publishAssets($plugin);
            }

            return [
                'slug' => $slug,
                'name' => $manifest['name'],
                'version' => (string) $manifest['version'],
                'updated' => $updated,
            ];
        } finally {
            File::deleteDirectory($workspace);
        }
    }

    private function extract(string $archivePath, string $destination): void
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new PluginInstallException('The uploaded file is not a readable ZIP archive.');
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new PluginInstallException('This archive contains too many files to be a plugin.');
            }

            $realDestination = realpath($destination);
            $totalBytes = 0;
            $extracted = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $raw = $stat['name'];

                // Windows tools store backslashes; see ThemeInstaller::extract().
                $name = str_replace('\\', '/', $raw);

                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }

                if ($this->isTraversal($raw)) {
                    throw new PluginInstallException("The archive contains an illegal path: {$raw}");
                }

                if (! $this->isAllowedFile($name)) {
                    continue;
                }

                $totalBytes += $stat['size'];

                if ($totalBytes > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new PluginInstallException('The archive is too large once uncompressed.');
                }

                $target = $destination.DIRECTORY_SEPARATOR.$name;
                File::ensureDirectoryExists(dirname($target));

                $realParent = realpath(dirname($target));

                if ($realParent === false || ! str_starts_with($realParent, $realDestination)) {
                    throw new PluginInstallException("The archive contains an illegal path: {$raw}");
                }

                $contents = $zip->getFromIndex($i);

                if ($contents === false) {
                    continue;
                }

                file_put_contents($target, $contents);
                $extracted++;
            }

            if ($extracted === 0) {
                throw new PluginInstallException('The archive contained no plugin files.');
            }
        } finally {
            $zip->close();
        }
    }

    private function isTraversal(string $name): bool
    {
        $normalised = str_replace('\\', '/', $name);

        return str_starts_with($normalised, '/')
            || str_contains($normalised, '../')
            || preg_match('/^[a-zA-Z]:/', $normalised) === 1
            || str_contains($name, "\0");
    }

    private function isAllowedFile(string $name): bool
    {
        $basename = strtolower(basename($name));

        // Dotfiles cover .htaccess, .user.ini and editor noise alike.
        if (str_starts_with($basename, '.') || str_contains(strtolower($name), '__macosx/')) {
            return false;
        }

        return in_array(pathinfo($basename, PATHINFO_EXTENSION), self::ALLOWED_EXTENSIONS, true);
    }

    /** The archive root, or the single folder it was wrapped in. */
    private function locateRoot(string $workspace): string
    {
        if (is_file($workspace.DIRECTORY_SEPARATOR.'plugin.json')) {
            return $workspace;
        }

        foreach (File::directories($workspace) as $directory) {
            if (is_file($directory.DIRECTORY_SEPARATOR.'plugin.json')) {
                return $directory;
            }
        }

        throw new PluginInstallException('No plugin.json was found in the archive. Every plugin needs one at its root.');
    }
}
