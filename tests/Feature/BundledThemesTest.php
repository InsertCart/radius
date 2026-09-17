<?php

namespace Tests\Feature;

use App\Cms\Themes\ThemeInstaller;
use App\Cms\Themes\ThemeManager;
use App\Models\Theme;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

/**
 * The themes that ship with the CMS must pass the CMS's own theme installer.
 *
 * Storefront once stopped passing without anybody noticing: a builder change
 * stored a closure in $safe and called $safe(...), which the template scan
 * rightly refuses, and the theme could no longer be uploaded anywhere. These
 * tests make that a failing build instead of a surprise on somebody's server.
 */
class BundledThemesTest extends TestCase
{
    private string $scratch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratch = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bundled-themes-'.uniqid();
        File::ensureDirectoryExists($this->scratch);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->scratch);

        parent::tearDown();
    }

    public function test_every_uploadable_bundled_theme_packages_and_passes_the_installer(): void
    {
        $slugs = collect(File::directories(base_path('themes')))
            ->map(fn (string $dir) => basename($dir))
            ->filter(fn (string $slug) => is_file(base_path("themes/{$slug}/theme.json")))
            ->reject(fn (string $slug) => $slug === config('cms.themes.default'))
            ->values();

        $this->assertNotEmpty($slugs, 'No uploadable bundled themes were found.');

        foreach ($slugs as $slug) {
            $this->artisan('cms:theme-package', ['slug' => $slug, '--output' => $this->scratch])
                ->assertExitCode(0);
        }
    }

    public function test_the_default_themes_templates_pass_the_scan(): void
    {
        // The default theme's slug is reserved, so it can never be uploaded -
        // but its templates are still read by the same scan when a theme is
        // copied from it, and must stay clean.
        $zip = $this->zipFolder(base_path('themes/default'), 'default-copy', ['slug' => 'default-copy']);

        $result = app(ThemeInstaller::class)->inspectArchive($zip, 'default-copy.zip');

        $this->assertSame('default-copy', $result['slug']);
    }

    public function test_a_theme_zipped_with_backslash_paths_is_accepted(): void
    {
        // Windows PowerShell's Compress-Archive writes paths like this. On
        // Linux they used to become single files, and the upload was refused
        // with "No theme.json was found".
        $zip = $this->scratch.DIRECTORY_SEPARATOR.'windows.zip';
        $archive = new ZipArchive;
        $archive->open($zip, ZipArchive::CREATE);
        $archive->addFromString('mytheme'.chr(92).'theme.json', json_encode(['name' => 'Windows', 'slug' => 'windows-zip', 'version' => '1.0.0']));
        $archive->addFromString('mytheme'.chr(92).'views'.chr(92).'layout.blade.php', '<html>@yield(\'content\')</html>');
        $archive->close();

        $result = app(ThemeInstaller::class)->inspectArchive($zip, 'windows.zip');

        $this->assertSame('windows-zip', $result['slug']);
        $this->assertSame(2, $result['files']);
    }

    public function test_the_root_screenshot_is_published_and_a_hostile_name_is_not(): void
    {
        $themes = $this->scratch.DIRECTORY_SEPARATOR.'themes';
        $public = $this->scratch.DIRECTORY_SEPARATOR.'public';
        File::ensureDirectoryExists($themes.DIRECTORY_SEPARATOR.'shot');
        File::ensureDirectoryExists($public);
        File::put($themes.DIRECTORY_SEPARATOR.'shot'.DIRECTORY_SEPARATOR.'screenshot.png', 'png-bytes');
        File::put($this->scratch.DIRECTORY_SEPARATOR.'secret.env', 'DB_PASSWORD=hunter2');

        config(['cms.themes.path' => $themes]);
        $this->app->usePublicPath($public);

        $manager = app(ThemeManager::class);

        $manager->publishAssets(new Theme(['slug' => 'shot', 'screenshot' => 'screenshot.png']));
        $this->assertFileExists($public.'/themes/shot/screenshot.png');

        $manager->publishAssets(new Theme(['slug' => 'shot', 'screenshot' => '../../secret.env']));
        $this->assertFileDoesNotExist($public.'/themes/shot/secret.env');
        $this->assertSame(['screenshot.png'], array_map('basename', File::files($public.'/themes/shot')));
    }

    /** A folder zipped with forward slashes, with theme.json values overridden. */
    private function zipFolder(string $source, string $prefix, array $manifestOverrides = []): string
    {
        $zip = $this->scratch.DIRECTORY_SEPARATOR.$prefix.'.zip';
        $archive = new ZipArchive;
        $archive->open($zip, ZipArchive::CREATE);

        foreach (File::allFiles($source, true) as $file) {
            $relative = strtr($file->getRelativePathname(), DIRECTORY_SEPARATOR, '/');

            if ($relative === 'theme.json') {
                $manifest = array_merge(json_decode(File::get($file->getPathname()), true) ?: [], $manifestOverrides);
                $archive->addFromString($prefix.'/theme.json', json_encode($manifest));

                continue;
            }

            $archive->addFile($file->getPathname(), $prefix.'/'.$relative);
        }

        $archive->close();

        return $zip;
    }
}
