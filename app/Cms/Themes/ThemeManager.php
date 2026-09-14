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
        $source = $theme->path('assets');

        if (! is_dir($source)) {
            return;
        }

        $destination = public_path(config('cms.themes.asset_url').DIRECTORY_SEPARATOR.$theme->slug);

        File::ensureDirectoryExists($destination);
        File::copyDirectory($source, $destination);
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
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        try {
            return $this->tableExists = Schema::hasTable('themes');
        } catch (\Throwable $e) {
            return $this->tableExists = false;
        }
    }
}
