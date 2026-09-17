<?php

namespace App\Cms\Themes;

use App\Models\Theme;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

/**
 * Finds, registers and activates front-end templates.
 *
 * Views are resolved through a "theme::" namespace pointing at the active
 * theme, with the default theme registered underneath as a fallback. A custom
 * template therefore only has to override the views it actually wants to
 * change - a partial theme still renders a complete site.
 */
class ThemeManager
{
    private const CACHE_KEY = 'cms.theme.active';

    private ?Theme $active = null;

    private ?bool $tableExists = null;

    public function path(string $sub = ''): string
    {
        return rtrim(config('cms.themes.path').DIRECTORY_SEPARATOR.ltrim($sub, '/\\'), DIRECTORY_SEPARATOR);
    }

    /** The theme the public site is currently rendered with. */
    public function active(): ?Theme
    {
        if ($this->active) {
            return $this->active;
        }

        if (! $this->hasTable()) {
            return null;
        }

        $slug = Cache::remember(
            self::CACHE_KEY,
            86400,
            fn () => Theme::where('is_active', true)->value('slug') ?? config('cms.themes.default')
        );

        $theme = Theme::where('slug', $slug)->first();

        // A theme folder can vanish (an FTP mishap, a failed upgrade). Rather
        // than fataling on every page, fall back to the bundled default.
        if (! $theme || ! $theme->existsOnDisk()) {
            $theme = Theme::where('slug', config('cms.themes.default'))->first();
        }

        return $this->active = $theme;
    }

    public function activeSlug(): string
    {
        return $this->active()?->slug ?? config('cms.themes.default');
    }

    /**
     * Points the "theme::" view namespace at the active theme, then the
     * default theme, then the application's own views.
     */
    public function registerViewNamespace(): void
    {
        $paths = [];

        $activeSlug = $this->activeSlug();
        $defaultSlug = config('cms.themes.default');

        if (is_dir($this->path($activeSlug.'/views'))) {
            $paths[] = $this->path($activeSlug.'/views');
        }

        if ($activeSlug !== $defaultSlug && is_dir($this->path($defaultSlug.'/views'))) {
            $paths[] = $this->path($defaultSlug.'/views');
        }

        $paths[] = resource_path('views/theme');

        View::addNamespace('theme', $paths);
    }

    public function activate(string $slug): void
    {
        $theme = Theme::where('slug', $slug)->firstOrFail();

        if (! $theme->existsOnDisk()) {
            throw new \RuntimeException("The theme [{$slug}] is missing its files on disk.");
        }

        Theme::where('is_active', true)->update(['is_active' => false]);
        $theme->update(['is_active' => true]);

        $this->publishAssets($theme);
        $this->flush();
    }

    /**
     * Copies a theme's assets/ folder into public/themes/<slug> so the browser
     * can reach them. Themes themselves live outside the web root.
     */
    public function publishAssets(Theme $theme): void
    {
        $destination = public_path(config('cms.themes.asset_url').DIRECTORY_SEPARATOR.$theme->slug);
        $source = $theme->path('assets');

        if (is_dir($source)) {
            File::ensureDirectoryExists($destination);
            File::copyDirectory($source, $destination);
        }

        $this->publishScreenshot($theme, $destination);
    }

    /**
     * Publish the preview image from the theme's root folder.
     *
     * A theme keeps one screenshot, beside theme.json - the place authors
     * coming from WordPress already put it. Only assets/ used to be published,
     * so a root screenshot was never web-reachable: a theme that followed the
     * documented structure showed "No preview image", and the bundled themes
     * ended up carrying a second, identical copy in assets/ to work around it.
     *
     * Themes that still keep it in assets/ are unaffected; that folder is
     * published above.
     */
    private function publishScreenshot(Theme $theme, string $destination): void
    {
        // The name is written by the theme's author. basename() and the
        // extension check stop "screenshot": "../../.env" from publishing a
        // server file into public/, where anyone could fetch it.
        $name = basename((string) ($theme->screenshot ?: 'screenshot.png'));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) {
            return;
        }

        $file = $theme->path($name);
        $root = realpath($theme->path());
        $real = realpath($file);

        if ($real === false || $root === false || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
            return;
        }

        File::ensureDirectoryExists($destination);
        File::copy($real, $destination.DIRECTORY_SEPARATOR.$name);
    }

    /** Reads theme.json for every folder in themes/. */
    public function discover(): array
    {
        $found = [];

        if (! is_dir($this->path())) {
            return $found;
        }

        foreach (File::directories($this->path()) as $directory) {
            $manifest = $directory.DIRECTORY_SEPARATOR.'theme.json';

            if (! is_file($manifest)) {
                continue;
            }

            $data = json_decode(File::get($manifest), true);

            if (! is_array($data)) {
                continue;
            }

            $data['slug'] = $data['slug'] ?? basename($directory);
            $found[$data['slug']] = $data;
        }

        return $found;
    }

    /**
     * Creates or refreshes a database row for every theme folder, and drops
     * rows whose folder has been deleted.
     */
    public function sync(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        $discovered = $this->discover();

        foreach ($discovered as $slug => $data) {
            $theme = Theme::firstOrNew(['slug' => $slug]);

            $theme->fill([
                'name' => $data['name'] ?? ucfirst($slug),
                'version' => $data['version'] ?? '1.0.0',
                'author' => $data['author'] ?? null,
                'author_url' => $data['author_url'] ?? null,
                'description' => $data['description'] ?? null,
                'screenshot' => $data['screenshot'] ?? 'screenshot.png',
                'meta' => $data,
            ]);

            if (! $theme->exists) {
                $theme->is_active = $slug === config('cms.themes.default')
                    && ! Theme::where('is_active', true)->exists();
            }

            $theme->save();
            $this->publishAssets($theme);
        }

        // Remove rows for folders that are gone, but never the active one -
        // the fallback in active() handles that case more gracefully.
        Theme::whereNotIn('slug', array_keys($discovered))
            ->where('is_active', false)
            ->delete();

        $this->flush();

        return count($discovered);
    }

    public function delete(string $slug): void
    {
        if ($slug === config('cms.themes.default')) {
            throw new \RuntimeException('The default theme cannot be deleted.');
        }

        if ($slug === $this->activeSlug()) {
            throw new \RuntimeException('Deactivate this theme before deleting it.');
        }

        File::deleteDirectory($this->path($slug));
        File::deleteDirectory(public_path(config('cms.themes.asset_url').DIRECTORY_SEPARATOR.$slug));

        Theme::where('slug', $slug)->delete();
        $this->flush();
    }

    /**
     * Resolves a view name inside the active theme, falling back to the given
     * view when the theme does not define it.
     */
    public function view(string $name, ?string $fallback = null): string
    {
        $namespaced = 'theme::'.$name;

        if (View::exists($namespaced)) {
            return $namespaced;
        }

        return $fallback ?? $namespaced;
    }

    public function flush(): void
    {
        $this->active = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function hasTable(): bool
    {
        // Only a positive answer is remembered. "No such table" is a
        // statement about this moment, not a fact: during installation these
        // managers are resolved before the migrations run, and caching that
        // first "no" made every later call in the same request agree with it -
        // so sync() quietly seeded nothing and a fresh site came up with no
        // modules at all. A table that does exist cannot stop existing mid-
        // request, so the true case is still worth keeping.
        if ($this->tableExists === true) {
            return true;
        }

        try {
            return $this->tableExists = Schema::hasTable('themes');
        } catch (\Throwable $e) {
            // No connection yet, most likely. Not remembered, for the same reason.
            return false;
        }
    }
}
