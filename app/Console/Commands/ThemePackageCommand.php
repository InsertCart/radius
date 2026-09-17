<?php

namespace App\Console\Commands;

use App\Cms\Themes\ThemeInstaller;
use App\Cms\Themes\ThemeInstallException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Packages a theme folder into a ZIP that is proven to install.
 *
 * Zipping a theme by hand goes wrong in ways that stay invisible until somebody
 * uploads it. Windows PowerShell's Compress-Archive stores backslashes, which a
 * Linux server reads as one long filename. Editor and OS clutter comes along
 * for the ride. And a template that trips the installer's safety scan looks
 * fine right up until a buyer's site refuses it.
 *
 * So this builds the archive in the layout the installer expects, then runs the
 * installer's own checks against it - the same code every site runs on upload -
 * and throws the archive away if any of them fail.
 */
class ThemePackageCommand extends Command
{
    protected $signature = 'cms:theme-package
        {slug : Folder name under themes/, e.g. storefront}
        {--output= : Directory to write the ZIP into (default: storage/app/private/theme-packages)}';

    protected $description = 'Package a theme into an installable ZIP, checked by the theme installer';

    /** Never worth shipping, whatever the theme folder contains. */
    private const JUNK = ['.git', '.github', '.idea', '.vscode', 'node_modules', '.DS_Store', 'Thumbs.db', 'desktop.ini'];

    public function handle(ThemeInstaller $installer): int
    {
        $slug = (string) $this->argument('slug');
        $source = base_path('themes'.DIRECTORY_SEPARATOR.$slug);

        if (! is_file($source.DIRECTORY_SEPARATOR.'theme.json')) {
            $this->error("No theme at themes/{$slug} - there is no theme.json in that folder.");

            return self::FAILURE;
        }

        if ($slug === config('cms.themes.default')) {
            $this->error('The default theme cannot be packaged for upload.');
            $this->line('  It ships with every install and is the fallback every other theme builds on, so an');
            $this->line('  uploaded copy would be refused. CMS updates keep it current instead.');

            return self::FAILURE;
        }

        $manifest = json_decode((string) File::get($source.DIRECTORY_SEPARATOR.'theme.json'), true) ?: [];
        $version = (string) ($manifest['version'] ?? '0.0.0');

        $output = $this->option('output') ?: storage_path('app/private/theme-packages');
        File::ensureDirectoryExists($output);
        $zipPath = rtrim($output, '/'.DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."{$slug}-{$version}.zip";
        @unlink($zipPath);

        $count = $this->writeZip($source, $zipPath, $slug);

        $this->line("Packaged <info>{$slug}</info> {$version}: {$count} file(s)");
        $this->newLine();

        try {
            $result = $installer->inspectArchive($zipPath, basename($zipPath));
        } catch (ThemeInstallException $e) {
            @unlink($zipPath);
            $this->error('The theme installer would refuse this theme, so no ZIP was kept:');
            $this->line('  '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('  <info>ok</info>   passes the theme installer ('.$result['files'].' file(s) accepted)');

        if ($result['files'] < $count) {
            $this->warn('  '.($count - $result['files']).' file(s) are not an allowed type and will be dropped on install.');
        }

        foreach ($result['warnings'] as $warning) {
            $this->line("  <comment>note</comment> {$warning}");
        }

        $this->checkScreenshot($source, (string) ($manifest['screenshot'] ?? 'screenshot.png'));

        $this->newLine();
        $this->info('Theme ZIP ready.');
        $this->line('  '.$zipPath);
        $this->line('  '.number_format(filesize($zipPath) / 1024).' KB, sha256 '.hash_file('sha256', $zipPath));
        $this->newLine();
        $this->line('Next: install it on a test site (Appearance -> Themes -> Upload). To list it in the');
        $this->line('theme directory: php artisan cms:marketplace-entry "'.$zipPath.'" --url=<folder URL>');

        return self::SUCCESS;
    }

    /** Forward slashes and one wrapping folder: the layout every installer reads. */
    private function writeZip(string $source, string $zipPath, string $slug): int
    {
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $count = 0;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            $relative = strtr(substr($file->getPathname(), strlen($source) + 1), DIRECTORY_SEPARATOR, '/');

            foreach (explode('/', $relative) as $segment) {
                if (in_array($segment, self::JUNK, true)) {
                    continue 2;
                }
            }

            $zip->addFile($file->getPathname(), $slug.'/'.$relative);
            $count++;
        }

        $zip->close();

        return $count;
    }

    /**
     * The preview shown in the admin and the theme directory sits in a 16:9
     * box, so anything else is cropped. Advice only: a theme with an imperfect
     * screenshot still works.
     */
    private function checkScreenshot(string $source, string $name): void
    {
        $name = basename($name);
        $path = $source.DIRECTORY_SEPARATOR.$name;

        if (! is_file($path)) {
            $this->warn("  no {$name} beside theme.json - the admin will show \"No preview image\".");

            return;
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($extension === 'svg') {
            $this->line('  <comment>note</comment> the screenshot is an SVG. A real 1200x675 PNG of the theme sells it far better.');

            return;
        }

        $info = @getimagesize($path);

        if (! $info) {
            $this->warn("  {$name} is not a readable image.");

            return;
        }

        [$width, $height] = $info;
        $expected = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'][$extension] ?? null;

        if ($expected !== null && $info['mime'] !== $expected) {
            $this->warn("  {$name} is really {$info['mime']}. Save it in the format its name says.");
        }

        if (abs($width / max(1, $height) - 16 / 9) > 0.02) {
            $this->warn("  {$name} is {$width}x{$height}; previews are shown at 16:9, so it will be cropped. Use 1200x675.");
        } elseif ($width < 1200) {
            $this->warn("  {$name} is {$width}x{$height}; use at least 1200x675 so it stays sharp.");
        } else {
            $this->line("  <info>ok</info>   screenshot {$width}x{$height}");
        }

        if (filesize($path) > 500 * 1024) {
            $this->warn('  the screenshot is '.number_format(filesize($path) / 1024).' KB; keep it under 500 KB.');
        }
    }
}
