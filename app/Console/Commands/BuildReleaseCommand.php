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

        $this->info('Writing the archive...');

        if (! $this->writeZip($zipPath, $root, $files, "{$slug}-{$version}")) {
            $this->error('The archive could not be created.');

            return self::FAILURE;
        }

        $size = (int) filesize($zipPath);
        $hash = hash_file('sha256', $zipPath);

        $this->writeManifest($output, $version, $hash, $size);

        $this->newLine();
        $this->info('Release built.');
        $this->line('  archive   '.$zipPath);
        $this->line('  size      '.number_format($size / 1048576, 1).' MB');
        $this->line('  sha256    '.$hash);
        $this->line('  manifest  '.rtrim($output, '/\\').DIRECTORY_SEPARATOR.'manifest.json');

        $this->publishingInstructions($version, $hash, $zipPath);

        return self::SUCCESS;
    }

    /**
     * What to do with the two files that were just produced.
     *
     * The metadata block is the part worth printing. A GitHub release carries
     * no checksum of its own, so without it an update is refused - correctly,
     * but confusingly, and long after the release went out. Handing it over
     * ready to paste is the difference between that being a rule and being a
     * trap.
     */
    private function publishingInstructions(string $version, string $hash, string $zipPath): void
    {
        $this->newLine();
        $this->line('<comment>Paste this into the GitHub release notes</comment> (it is an HTML comment,');
        $this->line('so nobody reading the page will see it):');
        $this->newLine();
        $this->line("<info><!-- radius</info>");
        $this->line("<info>sha256: {$hash}</info>");
        $this->line('<info>tags: </info>            <comment># e.g. security, breaking - free text, shown as badges</comment>');
        $this->line('<info>requires_backup: true</info>');
        $this->line("<info>min_version: 1.0.0</info>  <comment># oldest version that can upgrade straight to this one</comment>");
        $this->line('<info>min_php: 8.2.0</info>');
        $this->line('<info>--></info>');
        $this->newLine();
        $this->line('<comment>Then publish it:</comment>');
        $this->newLine();
        $this->line("  gh release create v{$version} --title {$version} --notes-file notes.md {$zipPath}");
        $this->newLine();
        $this->line('Sites pointed at the releases API see it within a day, or immediately');
        $this->line('via System -> Updates -> Check now.');
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
