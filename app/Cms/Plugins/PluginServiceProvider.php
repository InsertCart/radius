<?php

namespace App\Cms\Plugins;

use App\Models\Plugin;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * What every plugin's service provider extends.
 *
 * A plugin is an ordinary Laravel service provider - register() and boot()
 * work exactly as they do anywhere else - plus a few helpers that put its
 * routes behind the same middleware as the CMS's own, and three lifecycle
 * methods the Plugins screen calls:
 *
 *   activated()     after it is switched on and its migrations have run
 *   deactivated()   after it is switched off; its data is kept
 *   uninstalling()  before its migrations are rolled back and its folder
 *                   deleted - the place to remove anything else it created
 *
 * The lifecycle methods run on a provider that has not been registered in
 * that request, so they must not rely on anything set up in register() or
 * boot().
 */
abstract class PluginServiceProvider extends ServiceProvider
{
    private string $pluginSlug = '';

    private string $pluginPath = '';

    /** @internal Called by PluginManager before the provider is registered. */
    public function bindPlugin(string $slug, string $path): static
    {
        $this->pluginSlug = $slug;
        $this->pluginPath = $path;

        return $this;
    }

    public function activated(): void {}

    public function deactivated(): void {}

    public function uninstalling(): void {}

    // Helpers --------------------------------------------------------------

    protected function slug(): string
    {
        return $this->pluginSlug;
    }

    protected function pluginPath(string $sub = ''): string
    {
        return rtrim($this->pluginPath.DIRECTORY_SEPARATOR.ltrim($sub, '/\\'), DIRECTORY_SEPARATOR);
    }

    protected function plugin(): ?Plugin
    {
        return plugins()->find($this->pluginSlug);
    }

    protected function hooks(): Hooks
    {
        return $this->app->make(Hooks::class);
    }

    /** resources/views, under the plugin's slug: view('my-plugin::settings'). */
    protected function loadPluginViews(): void
    {
        if (is_dir($this->pluginPath('resources/views'))) {
            $this->loadViewsFrom($this->pluginPath('resources/views'), $this->pluginSlug);
        }
    }

    /** Public routes, on the same stack as the site's own pages. */
    protected function loadWebRoutes(string $file = 'routes/web.php'): void
    {
        if ($this->routesCached()) {
            return;
        }

        Route::middleware(['web', 'installed'])->group($this->pluginPath($file));
    }

    /**
     * Admin routes: under the admin prefix and route-name prefix, signed in,
     * staff, two-factor checked - and, unless told otherwise, administrators
     * only, because a plugin's settings change how the site behaves.
     */
    protected function loadAdminRoutes(string $file = 'routes/admin.php', bool $adminOnly = true): void
    {
        if ($this->routesCached()) {
            return;
        }

        $middleware = ['web', 'installed', 'auth', 'staff', '2fa'];

        if ($adminOnly) {
            $middleware[] = 'staff:admin';
        }

        Route::prefix(config('cms.admin_prefix', 'admin'))
            ->name('admin.')
            ->middleware($middleware)
            ->group($this->pluginPath($file));
    }

    private function routesCached(): bool
    {
        return $this->app instanceof \Illuminate\Contracts\Foundation\CachesRoutes
            && $this->app->routesAreCached();
    }
}
