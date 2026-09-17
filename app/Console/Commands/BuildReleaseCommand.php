<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Packages the working tree into the ZIP that buyers download and the updater
 * installs.
 *
 * This exists because a checkout from version control is not a release and
 * cannot be made into one by zipping it. `vendor/` and `public/build` are both
 * ignored by git - correctly, they are build output - but they are also the two
 * things a buyer on shared hosting has no way to produce. An archive made
 * straight from the repository fails on its very first request, before Laravel
 * has booted far enough to show an error anybody could act on.
 *
 * The other half of the job is leaving things out. `storage/installed` is the
 * lock that says setup is finished; shipped to a buyer, their setup wizard
 * never opens and the product looks broken out of the box.
 */
class BuildReleaseCommand extends Command
{
    protected $signature = 'cms:release
        {--output= : Directory to write the ZIP into (default: storage/app/private/releases)}
        {--release= : Override the version; defaults to the one in config/cms.php}
        {--url= : Download address to write into the manifest}
        {--force : Overwrite an existing archive for this version}';

    protected $description = 'Package this installation into a distributable release ZIP and manifest';

    public function handle(): int
    {
        $version = (string) ($this->option('release') ?: cms_version());
        $root = base_path();

        $this->line("Packaging <info>".config('cms.name')."</info> version <info>{$version}</info>");
        $this->newLine();

        if (! $this->checkPrerequisites()) {
            return self::FAILURE;
        }

        $output = $this->option('output')
            ?: storage_path('app/private/releases');

        File::ensureDirectoryExists($output);

        $slug = \Illuminate\Support\Str::slug(config('cms.name', 'cms'));
        $zipPath = rtrim($output, '/\\').DIRECTORY_SEPARATOR."{$slug}-{$version}.zip";

        if (is_file($zipPath) && ! $this->option('force')) {
            $this->error("{$zipPath} already exists. Use --force to replace it.");

            return self::FAILURE;
        }

        @unlink($zipPath);

        $this->info('Collecting files...');
        $files = $this->collect($root);
        $this->line('  '.number_format(count($files)).' file(s)');

        if (! $this->bundledThemesAreUpdatable($files)) {
            return self::FAILURE;
        }

        $this->info('Writing the archive...');

        if (! $this->writeZip($zipPath, $root, $files, "{$slug}-{$version}")) {
            $this->error('The archive could not be created.');

            return self::FAILURE;
        }

        $size = (int) filesize($zipPath);
        $hash = hash_file('sha256', $zipPath);

        $this->writeManifest($output, $version, $hash, $size);
        $notesPath = $this->writeNotes($output, $version, $hash);

        $this->newLine();
        $this->info('Release built.');
        $this->line('  archive   '.$zipPath);
        $this->line('  size      '.number_format($size / 1048576, 1).' MB');
        $this->line('  sha256    '.$hash);
        $this->line('  manifest  '.rtrim($output, '/\\').DIRECTORY_SEPARATOR.'manifest.json');

        $this->publishingInstructions($version, $zipPath, $notesPath);

        return self::SUCCESS;
    }

    /**
     * Write the release notes GitHub will publish, with the metadata already
     * filled in.
     *
     * Printing the block to the terminal and expecting someone to build a file
     * around it was asking them to do the assembly by hand every time - and the
     * one part that must not be mistyped is a 64-character hash. Producing the
     * finished file means the only thing left to write is the part a person is
     * actually needed for.
     */
    private function writeNotes(string $directory, string $version, string $hash): string
    {
        $path = rtrim($directory, "/".DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."notes-{$version}.md";

        // Notes already written by hand are kept - re-running the build to fix
        // a packaging problem must not throw away what somebody wrote. But the
        // archive has just been rebuilt, so its checksum has changed, and a
        // stale one is worse than none: the notes would look complete and
        // every site would refuse the update. So the text is preserved and the
        // hash is refreshed.
        if (is_file($path)) {
            $existing = (string) File::get($path);
            $updated = preg_replace('/^sha256:.*$/m', 'sha256: '.$hash, $existing, 1, $replaced);

            if ($replaced) {
                File::put($path, $updated);
                $this->line('  <comment>notes kept, checksum refreshed</comment>');
            } else {
                $this->warn('  '.basename($path).' has no sha256 line - the update will be refused. Add: sha256: '.$hash);
            }

            return $path;
        }

        $lines = [
            'Describe what changed in this release.',
            '',
            'The first paragraph is what site owners see in their admin panel, so put the',
            'headline there. Everything below it appears on the GitHub release page.',
            '',
            '<!-- radius',
            'sha256: '.$hash,
            'tags:',
            'requires_backup: true',
            'min_version: 1.0.0',
            'min_php: 8.2.0',
            '-->',
            '',
        ];

        File::put($path, implode(chr(10), $lines));

        return $path;
    }

    /**
     * What to do with the files that were just produced.
     *
     * The checksum is the part that matters. A GitHub release carries no
     * checksum of its own, so without it every site refuses the update -
     * correctly, but confusingly, and long after the release went out.
     */
    private function publishingInstructions(string $version, string $zipPath, string $notesPath): void
    {
        $this->newLine();
        $this->line('  notes     '.$notesPath);
        $this->newLine();
        $this->line('<comment>Two steps left:</comment>');
        $this->newLine();
        $this->line('  1. Open the notes file above and write what changed. The checksum and');
        $this->line('     the settings the updater needs are already in it - leave the');
        $this->line('     <!-- radius --> block alone, and set <info>tags:</info> if this is a');
        $this->line('     security or breaking release.');
        $this->newLine();
        $this->line('  2. Publish it:');
        $this->newLine();
        $this->line("     <info>gh release create v{$version} --title {$version} --notes-file \"{$notesPath}\" \"{$zipPath}\"</info>");
        $this->newLine();
        $this->line('Sites see it within a day, or immediately via System -> Updates -> Check now.');
    }

    /**
     * Refuse to ship a theme the updater could never update.
     *
     * The updater only writes paths listed in config/updates.php. A theme that
     * is packaged but not listed installs fine and then quietly stays at that
     * version forever - which is exactly how storefront missed instant search
     * and the new cursors on every site that updated. It looks like a caching
     * problem from the outside, so it is worth stopping the build over.
     *
     * @param  string[]  $files
     */
    private function bundledThemesAreUpdatable(array $files): bool
    {
        $archive = app(\App\Cms\Updates\ReleaseArchive::class);
        $stranded = [];

        foreach ($files as $file) {
            if (preg_match('#^themes/([^/]+)/theme\.json$#', $file, $m)
                && ! $archive->isWritablePath($file)) {
                $stranded[] = $m[1];
            }
        }

        if ($stranded === []) {
            return true;
        }

        $this->newLine();
        $this->error('This release ships themes that updates would never reach: '.implode(', ', $stranded));
        $this->line('  Add each one to both paths.merge and paths.track_edits in config/updates.php:');

        foreach ($stranded as $slug) {
            $this->line("    'themes/{$slug}',");
        }

        return false;
    }

    /**
     * Refuse to build something that cannot possibly work.
     *
     * Every one of these produces a release that fails on the buyer's first
     * page load, and none of them is obvious from looking at the ZIP - which is
     * exactly why it is worth failing loudly here instead.
     */
    private function checkPrerequisites(): bool
    {
        $ok = true;

        foreach ((array) config('updates.package.required', []) as $relative) {
            $exists = file_exists(base_path($relative));

            $this->line(sprintf('  %s %s', $exists ? '<info>ok  </info>' : '<error>MISSING</error>', $relative));

            if (! $exists) {
                $ok = false;
            }
        }

        if (! $ok) {
            $this->newLine();
            $this->error('This working tree is not ready to package.');
            $this->line('  vendor/            run: composer install --no-dev --optimize-autoloader');
            $this->line('  public/build/      run: npm install && npm run build');

            return false;
        }

        // Dev dependencies in a sold product are dead weight and extra attack
        // surface, but they are not fatal, so this only warns.
        if (is_dir(base_path('vendor/phpunit'))) {
            $this->newLine();
            $this->warn('vendor/ contains development packages (phpunit was found).');
            $this->line('  For a smaller, tidier release: composer install --no-dev --optimize-autoloader');
            $this->newLine();
        }

        return true;
    }

    /**
     * Every project-relative path that belongs in the archive.
     *
     * @return string[]
     */
    private function collect(string $root): array
    {
        $exclude = array_map(
            fn ($p) => trim(str_replace('\\', '/', $p), '/'),
            (array) config('updates.package.exclude', [])
        );

        $empty = array_map(
            fn ($p) => trim(str_replace('\\', '/', $p), '/'),
            (array) config('updates.package.empty', [])
        );

        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));

            if ($this->matches($relative, $exclude)) {
                continue;
            }

            if ($item->isDir()) {
                continue;
            }

            // Inside a scaffolding folder only the dotfiles travel: the folder
            // must exist on the buyer's server, but its contents are this
            // site's uploads, logs and caches.
            if ($this->inside($relative, $empty) && ! $this->isScaffolding($relative)) {
                continue;
            }

            $files[] = $relative;
        }

        sort($files);

        return $files;
    }

    /** A path is excluded if it is the entry itself or sits underneath it. */
    private function matches(string $relative, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($relative === $pattern || str_starts_with($relative, $pattern.'/')) {
                return true;
            }
        }

        return false;
    }

    private function inside(string $relative, array $directories): bool
    {
        foreach ($directories as $directory) {
            if (str_starts_with($relative, $directory.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * .gitignore keeps an otherwise-empty folder in the archive; .htaccess is
     * the Apache rule that stops uploads and paid downloads being served
     * directly, so it has to travel with the folder it protects.
     */
    private function isScaffolding(string $relative): bool
    {
        return in_array(basename($relative), ['.gitignore', '.htaccess'], true);
    }

    /** @param string[] $files */
    private function writeZip(string $zipPath, string $root, array $files, string $prefix): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $relative) {
            // Forward slashes, always. A Windows-built archive that stores
            // backslashes is unreadable by most extractors on Linux, which is
            // where it is going to be unpacked.
            $zip->addFile($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative), $prefix.'/'.$relative);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $zip->close();
    }

    private function writeManifest(string $directory, string $version, string $hash, int $size): void
    {
        $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'manifest.json';

        $manifest = [
            'format' => (int) config('updates.supported_format', 1),
            'version' => $version,
            'released_at' => now()->toDateString(),
            'tags' => [],
            'requires_backup' => true,
            'min_version' => '1.0.0',
            'min_php' => '8.2.0',
            'requires_extensions' => ['gd', 'zip', 'fileinfo'],
            'download' => $this->option('url') ?: 'https://example.com/releases/'.basename($this->zipName($version)),
            'sha256' => $hash,
            'size' => $size,
            'notes' => '',
            'changelog_url' => '',
        ];

        // JSON_UNESCAPED_SLASHES keeps the URL readable for hand-editing, and
        // the file is written without a byte-order mark on purpose.
        File::put($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    private function zipName(string $version): string
    {
        return \Illuminate\Support\Str::slug(config('cms.name', 'cms'))."-{$version}.zip";
    }
}
