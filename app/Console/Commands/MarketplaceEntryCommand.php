<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Prints the theme directory entry for a theme ZIP, ready to paste into the
 * catalogue file.
 *
 * The checksum is the reason this exists. Computed by hand it is the step most
 * likely to go wrong, and wrong in the worst way: a stale or mistyped hash does
 * not look broken in the catalogue, it just makes every site refuse the
 * install. The slug and version come from the theme's own theme.json for the
 * same reason - a listing that disagrees with the archive is refused, or offers
 * an update forever.
 *
 * It does not decide whether a theme is safe to list. Install the ZIP through
 * Appearance -> Themes on a test site first: that runs the same scan every
 * buyer's site will.
 */
class MarketplaceEntryCommand extends Command
{
    protected $signature = 'cms:marketplace-entry
        {zip : Path to the theme ZIP}
        {--url= : Folder URL the ZIP and screenshot will be served from, e.g. https://www.example.com/marketplace/my-theme}';

    protected $description = 'Print the theme directory catalogue entry for a theme ZIP';

    public function handle(): int
    {
        $path = (string) $this->argument('zip');

        if (! is_file($path)) {
            $this->error("No file at {$path}");

            return self::FAILURE;
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            $this->error('That file is not a readable ZIP archive.');

            return self::FAILURE;
        }

        [$manifest, $prefix, $hasViews, $dropped] = $this->inspect($zip);
        $zip->close();

        if ($manifest === null) {
            $this->error('No readable theme.json was found at the root of the archive, or inside a single top-level folder.');

            return self::FAILURE;
        }

        foreach (['name', 'version'] as $field) {
            if (blank($manifest[$field] ?? null)) {
                $this->error("theme.json is missing \"{$field}\". The theme cannot be installed without it.");

                return self::FAILURE;
            }
        }

        if (! is_string($manifest['version'])) {
            $this->error('The version in theme.json is a number. Write it as a string ("1.10", not 1.10), or versions will compare wrongly.');

            return self::FAILURE;
        }

        // Exactly how ThemeInstaller names the folder, so the listing matches.
        $slug = Str::slug($manifest['slug'] ?? $manifest['name']);

        if ($slug === config('cms.themes.default')) {
            $this->error("The slug [{$slug}] belongs to the bundled default theme, and sites will refuse it.");

            return self::FAILURE;
        }

        $base = rtrim((string) $this->option('url'), '/');
        $version = $manifest['version'];
        $filename = basename($path);

        $entry = array_filter([
            'slug' => $slug,
            'name' => $manifest['name'],
            'version' => $version,
            'author' => $manifest['author'] ?? null,
            'author_url' => $manifest['author_url'] ?? null,
            'description' => $manifest['description'] ?? null,
            'tags' => [],
            'supports' => $manifest['supports'] ?? null,
            'screenshot' => $base !== '' ? $base.'/screenshot.png' : 'https://…/screenshot.png',
            'preview_url' => '',
            'download' => $base !== '' ? $base.'/'.$filename : 'https://…/'.$filename,
            'sha256' => hash_file('sha256', $path),
            'size' => filesize($path),
            'requires' => cms_version(),
            'tested' => cms_version(),
            'license' => $manifest['license'] ?? null,
            'updated_at' => now()->toDateString(),
        ], fn ($value) => $value !== null);

        $this->line(json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->newLine();

        if (! $hasViews) {
            $this->warn('The archive has no views/ folder. Sites will refuse to install it.');
        }

        if ($dropped !== []) {
            $this->warn(count($dropped).' file(s) are not on the allowed list and will be dropped on install, e.g. '
                .implode(', ', array_slice($dropped, 0, 3)));
        }

        if ($base === '') {
            $this->comment('Pass --url to fill in the download and screenshot addresses.');
        }

        $this->comment('Fill in "tags", "preview_url" and "requires", then add the entry to the "items" list.');
        $this->comment('Before listing it, install this ZIP through Appearance -> Themes on a test site: that runs the same scan every site will.');

        return self::SUCCESS;
    }

    /** @return array{0: ?array, 1: string, 2: bool, 3: string[]} */
    private function inspect(ZipArchive $zip): array
    {
        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = str_replace('\\', '/', (string) $zip->getNameIndex($i));
        }

        $prefix = null;

        foreach ($names as $name) {
            if ($name === 'theme.json' || preg_match('#^[^/]+/theme\.json$#', $name)) {
                // Prefer a root-level theme.json over one in a folder.
                if ($prefix === null || $name === 'theme.json') {
                    $prefix = substr($name, 0, -strlen('theme.json'));
                }
            }
        }

        if ($prefix === null) {
            return [null, '', false, []];
        }

        $manifest = json_decode((string) $zip->getFromName($prefix.'theme.json'), true);
        $hasViews = false;
        $dropped = [];
        $allowed = config('cms.themes.allowed_extensions', []);

        foreach ($names as $name) {
            if (str_ends_with($name, '/') || ! str_starts_with($name, $prefix)) {
                continue;
            }

            if (str_starts_with(substr($name, strlen($prefix)), 'views/')) {
                $hasViews = true;
            }

            $basename = strtolower(basename($name));
            $ok = ! str_starts_with($basename, '.') && collect($allowed)->contains(function ($extension) use ($basename) {
                return $extension === 'blade.php'
                    ? str_ends_with($basename, '.blade.php')
                    : str_ends_with($basename, '.'.$extension);
            });

            if (! $ok) {
                $dropped[] = $name;
            }
        }

        return [is_array($manifest) ? $manifest : null, $prefix, $hasViews, $dropped];
    }
}
