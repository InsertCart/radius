<?php

namespace App\Cms\Plugins;

use App\Models\Plugin;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Finds, loads, switches on and removes plugins.
 *
 * A plugin is a folder in plugins/ holding a plugin.json and a service
 * provider:
 *
 *     plugins/my-plugin/
 *     ├── plugin.json
 *     ├── src/                       RadiusPlugins\MyPlugin\ is autoloaded from here
 *     │   └── MyPluginServiceProvider.php
 *     ├── routes/  resources/views/  database/migrations/  assets/   (all optional)
 *
 * Enabled plugins are registered as service providers while the CMS itself
 * registers, so their routes, middleware and views are in place before the
 * first request is handled.
 *
 * Unlike modules, which are compiled into the CMS and only switched on or off,
 * plugins come and go: an uninstall runs the plugin's own clean-up, rolls back
 * its migrations and deletes its folder.
 */
class PluginManager
{
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** @var array<string, Plugin>|null */
    private ?array $rows = null;

    /** @var string[]|null */
    private ?array $enabledSlugs = null;

    /** @var string[] Plugins whose providers were registered in this request. */
    private array $loaded = [];

    /** @var array<string, string> Namespaces handed to the autoloader, prefix => src path. */
    private array $autoload = [];

    private bool $autoloaderRegistered = false;

    private ?bool $tableExists = null;

    public function path(string $sub = ''): string
    {
        return rtrim(config('cms.plugins.path').DIRECTORY_SEPARATOR.ltrim($sub, '/\\'), DIRECTORY_SEPARATOR);
    }

    public function safeMode(): bool
    {
        return (bool) config('cms.plugins.safe_mode', false);
    }

    public function uploadsAllowed(): bool
    {
        return (bool) config('cms.plugins.allow_upload', true);
    }

    // Reading ---------------------------------------------------------------

    /** @return array<string, Plugin> Every known plugin, keyed by slug. */
    public function all(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        if (! $this->hasTable()) {
            return [];
        }

        return $this->rows = Plugin::orderBy('name')->get()->keyBy('slug')->all();
    }

    public function find(string $slug): ?Plugin
    {
        return $this->all()[$slug] ?? null;
    }

    /** True when the plugin is switched on and actually running in this request. */
    public function enabled(string $slug): bool
    {
        return in_array($slug, $this->loaded, true);
    }

    /**
     * Read with the query builder, once per request, rather than through the
     * cache or Eloquent: this runs while providers are still registering, and
     * neither the cache (a deferred service) nor Eloquent's connection (set
     * when the database provider boots) exists yet. One indexed query.
     *
     * @return string[]
     */
    public function enabledSlugs(): array
    {
        if ($this->enabledSlugs !== null) {
            return $this->enabledSlugs;
        }

        if (! $this->hasTable()) {
            return [];
        }

        return $this->enabledSlugs = DB::table('plugins')->where('enabled', true)->orderBy('slug')->pluck('slug')->all();
    }

    /**
     * Reads plugin.json in every folder of plugins/.
     *
     * @return array{valid: array<string, array>, invalid: array<string, string>}
     */
    public function discover(): array
    {
        $valid = [];
        $invalid = [];

        if (! is_dir($this->path())) {
            return compact('valid', 'invalid');
        }

        foreach (File::directories($this->path()) as $directory) {
            $folder = basename($directory);

            if (! is_file($directory.DIRECTORY_SEPARATOR.'plugin.json')) {
                continue;
            }

            try {
                $manifest = $this->readManifest($directory);

                if ($manifest['slug'] !== $folder) {
                    throw new PluginInstallException("its folder is named [{$folder}] but plugin.json says [{$manifest['slug']}]. They must match.");
                }

                $valid[$folder] = $manifest;
            } catch (PluginInstallException $e) {
                $invalid[$folder] = $e->getMessage();
            }
        }

        return compact('valid', 'invalid');
    }

    /**
     * Validates a plugin.json and fills in its defaults.
     *
     * @throws PluginInstallException
     */
    public function readManifest(string $directory): array
    {
        $manifest = json_decode((string) @file_get_contents($directory.DIRECTORY_SEPARATOR.'plugin.json'), true);

        if (! is_array($manifest)) {
            throw new PluginInstallException('plugin.json is not valid JSON.');
        }

        foreach (['name', 'slug', 'version', 'namespace', 'provider'] as $required) {
            if (blank($manifest[$required] ?? null) || ! is_string($manifest[$required])) {
                throw new PluginInstallException("plugin.json is missing the required \"{$required}\" field.");
            }
        }

        if (! preg_match(self::SLUG_PATTERN, $manifest['slug']) || strlen($manifest['slug']) > 60) {
            throw new PluginInstallException('The plugin slug may only contain lowercase letters, numbers and single hyphens.');
        }

        // Every plugin lives under one namespace of its own. A plugin able to
        // declare App\... could replace a class of the CMS the first time the
        // autoloader went looking for it.
        $root = trim((string) config('cms.plugins.namespace', 'RadiusPlugins'), '\\');
        $namespace = trim($manifest['namespace'], '\\');

        if (! preg_match('/^'.preg_quote($root, '/').'(\\\\[A-Z][A-Za-z0-9_]*)+$/', $namespace)) {
            throw new PluginInstallException("The plugin namespace must sit under {$root}\\, for example {$root}\\MyPlugin.");
        }

        $provider = trim($manifest['provider'], '\\');

        if (! str_starts_with($provider, $namespace.'\\')) {
            throw new PluginInstallException('The plugin provider must be a class inside the plugin\'s own namespace.');
        }

        $manifest['namespace'] = $namespace;
        $manifest['provider'] = $provider;
        $manifest['requires'] = (string) ($manifest['requires'] ?? '0');

        return $manifest;
    }

    /** Null when the plugin can run on this CMS version, otherwise the reason it cannot. */
    public function incompatibility(array $manifest): ?string
    {
        $requires = $manifest['requires'] ?? '0';

        if ($requires !== '0' && version_compare(cms_version(), $requires, '<')) {
            return "{$manifest['name']} needs version {$requires} of the CMS or later; this site runs ".cms_version().'.';
        }

        return null;
    }

    // Loading ---------------------------------------------------------------

    /**
     * Registers the service provider of every enabled plugin. Called once,
     * while the CMS registers its own services.
     *
     * A plugin that cannot be loaded - folder gone, provider class missing,
     * an exception while registering - is reported and skipped. The site
     * carries on without it rather than failing on every page.
     */
    public function registerEnabled(Application $app): void
    {
        if ($this->safeMode()) {
            return;
        }

        foreach ($this->enabledSlugs() as $slug) {
            try {
                $provider = $this->provider($app, $slug);
                $app->register($provider);
                $this->loaded[] = $slug;
            } catch (\Throwable $e) {
                report(new \RuntimeException("Plugin [{$slug}] could not be loaded: {$e->getMessage()}", 0, $e));
            }
        }
    }

    /**
     * A plugin's provider, constructed but not registered.
     *
     * @throws PluginInstallException
     */
    private function provider(Application $app, string $slug): PluginServiceProvider
    {
        $directory = $this->path($slug);

        if (! is_file($directory.DIRECTORY_SEPARATOR.'plugin.json')) {
            throw new PluginInstallException("The files for this plugin are missing from {$directory}.");
        }

        $manifest = $this->readManifest($directory);

        if ($reason = $this->incompatibility($manifest)) {
            throw new PluginInstallException($reason);
        }

        $this->autoload($manifest['namespace'], $directory.DIRECTORY_SEPARATOR.'src');

        $class = $manifest['provider'];

        if (! class_exists($class)) {
            throw new PluginInstallException("Its provider class {$class} was not found in src/.");
        }

        if (! is_subclass_of($class, PluginServiceProvider::class)) {
            throw new PluginInstallException('Its provider must extend '.PluginServiceProvider::class.'.');
        }

        return (new $class($app))->bindPlugin($slug, $directory);
    }

    /** PSR-4 for plugins: RadiusPlugins\MyPlugin\Foo\Bar -> plugins/my-plugin/src/Foo/Bar.php */
    private function autoload(string $namespace, string $source): void
    {
        $this->autoload[$namespace.'\\'] = $source;

        if ($this->autoloaderRegistered) {
            return;
        }

        spl_autoload_register(function (string $class) {
            foreach ($this->autoload as $prefix => $source) {
                if (! str_starts_with($class, $prefix)) {
                    continue;
                }

                $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
                $file = $source.DIRECTORY_SEPARATOR.$relative.'.php';

                if (is_file($file)) {
                    require_once $file;
                }

                return;
            }
        });

        $this->autoloaderRegistered = true;
    }

    // Changing --------------------------------------------------------------

    /**
     * Creates or refreshes a row for every valid plugin folder. A new plugin
     * arrives switched off. Rows whose folder is gone are removed unless the
     * plugin is on - a folder missing mid-deploy should not silently turn a
     * plugin off for good.
     *
     * @return array<string, string> folders that were skipped, and why
     */
    public function sync(): array
    {
        $this->ensureFolders();

        if (! $this->hasTable()) {
            return [];
        }

        ['valid' => $valid, 'invalid' => $invalid] = $this->discover();

        foreach ($valid as $slug => $manifest) {
            $plugin = Plugin::firstOrNew(['slug' => $slug]);

            $plugin->fill([
                'name' => $manifest['name'],
                'version' => (string) $manifest['version'],
                'description' => $manifest['description'] ?? null,
                'author' => $manifest['author'] ?? null,
                'author_url' => $manifest['author_url'] ?? null,
                'meta' => $manifest,
            ]);

            if (! $plugin->exists) {
                $plugin->enabled = false;
                $plugin->installed_at = now();
            }

            $plugin->save();
            $this->publishAssets($plugin);
        }

        Plugin::whereNotIn('slug', array_keys($valid))->where('enabled', false)->delete();

        $this->flush();

        return $invalid;
    }

    /**
     * Switches a plugin on: runs its migrations, publishes its assets, then
     * calls its activated() hook.
     *
     * @throws PluginInstallException
     */
    public function enable(string $slug): void
    {
        // A folder copied in by hand has no row until something syncs;
        // switching it on from the command line should not need a visit to
        // the Plugins screen first.
        if (! Plugin::where('slug', $slug)->exists()) {
            $this->sync();
        }

        $plugin = $this->findOrFail($slug);
        $provider = $this->provider(app(), $slug);

        $this->migrate($plugin);
        $this->publishAssets($plugin);

        $provider->activated();

        $plugin->update(['enabled' => true]);
        $this->flushAll();
    }

    /** @throws PluginInstallException */
    public function disable(string $slug): void
    {
        $plugin = $this->findOrFail($slug);

        $plugin->update(['enabled' => false]);
        $this->flushAll();

        // The plugin is already off by the time its hook runs, so a hook that
        // throws cannot leave it half on.
        try {
            $this->provider(app(), $slug)->deactivated();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Removes a plugin entirely: its clean-up hook, its tables, its published
     * assets, its folder and its row. Only a plugin that is switched off can
     * be uninstalled, so nothing is torn out from under a running request.
     *
     * @throws PluginInstallException
     */
    public function uninstall(string $slug): void
    {
        $plugin = $this->findOrFail($slug);

        if ($plugin->enabled) {
            throw new PluginInstallException("Switch {$plugin->name} off before uninstalling it.");
        }

        if ($plugin->existsOnDisk()) {
            try {
                $this->provider(app(), $slug)->uninstalling();
            } catch (\Throwable $e) {
                // A plugin whose code no longer loads must still be removable.
                report($e);
            }

            $migrations = $plugin->path('database/migrations');

            if (is_dir($migrations)) {
                Artisan::call('migrate:reset', ['--path' => $migrations, '--realpath' => true, '--force' => true]);
            }
        }

        File::deleteDirectory($plugin->path());
        File::deleteDirectory($this->publicPath($slug));

        $plugin->delete();
        $this->flushAll();
    }

    /** Replaces the plugin's own settings. */
    public function saveConfig(string $slug, array $config): void
    {
        $this->findOrFail($slug)->update(['config' => $config]);
        $this->rows = null;
    }

    /** Runs the plugin's migrations, if it has any. Also used after an upgrade. */
    public function migrate(Plugin $plugin): void
    {
        $migrations = $plugin->path('database/migrations');

        if (is_dir($migrations)) {
            Artisan::call('migrate', ['--path' => $migrations, '--realpath' => true, '--force' => true]);
        }
    }

    /**
     * Copies the plugin's assets/ folder into public/plugins/<slug>, the same
     * way theme assets are published: the plugin itself lives outside the web
     * root, and only this copy is reachable.
     */
    public function publishAssets(Plugin $plugin): void
    {
        $source = $plugin->path('assets');

        if (! is_dir($source)) {
            return;
        }

        $this->ensureFolders();

        $destination = $this->publicPath($plugin->slug);

        File::deleteDirectory($destination);
        File::ensureDirectoryExists($destination);

        // Assets are served straight off the web server, so nothing that it
        // might execute is copied there, whatever the plugin ships.
        foreach (File::allFiles($source) as $file) {
            if (preg_match('/\.(php\d?|phtml|phar|cgi|pl|py|sh|htaccess)$/i', $file->getFilename()) || str_starts_with($file->getFilename(), '.')) {
                continue;
            }

            $target = $destination.DIRECTORY_SEPARATOR.$file->getRelativePathname();
            File::ensureDirectoryExists(dirname($target));
            File::copy($file->getPathname(), $target);
        }
    }

    /**
     * Creates plugins/ and public/plugins/, each with its .htaccess guard.
     *
     * A fresh download ships both. A site that updated from a release before
     * plugins existed does not: the updater never writes either path, by
     * design. So the guards are written here, the first time they are needed.
     * An existing .htaccess is left alone - the owner may have edited it.
     */
    public function ensureFolders(): void
    {
        $guards = [
            $this->path() => <<<'HTACCESS'
                # Plugin source code. Loaded by the CMS, never served.
                <IfModule mod_authz_core.c>
                    Require all denied
                </IfModule>
                <IfModule !mod_authz_core.c>
                    Order allow,deny
                    Deny from all
                </IfModule>
                HTACCESS,
            public_path('plugins') => <<<'HTACCESS'
                # Published plugin assets: static files only, nothing executed.
                Require all denied

                <FilesMatch "\.(?i:css|js|map|json|svg|png|jpe?g|gif|webp|avif|ico|woff2?|ttf|eot|otf|mp4|webm)$">
                    Require all granted
                </FilesMatch>

                Options -Indexes -ExecCGI -Includes

                <IfModule mod_php.c>
                    php_flag engine off
                </IfModule>
                <FilesMatch "\.(?i:php\d?|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp|shtml)$">
                    SetHandler none
                    Require all denied
                </FilesMatch>
                HTACCESS,
        ];

        foreach ($guards as $directory => $rules) {
            try {
                File::ensureDirectoryExists($directory);

                if (! is_file($directory.DIRECTORY_SEPARATOR.'.htaccess')) {
                    File::put($directory.DIRECTORY_SEPARATOR.'.htaccess', $rules.PHP_EOL);
                }
            } catch (\Throwable $e) {
                // An unwritable folder is reported by whatever needed it next.
                report($e);
            }
        }
    }

    public function publicPath(string $slug): string
    {
        return public_path('plugins'.DIRECTORY_SEPARATOR.$slug);
    }

    /** Forgets what this request has read about plugins. */
    public function flush(): void
    {
        $this->rows = null;
        $this->enabledSlugs = null;
    }

    /**
     * Also drops the route and view caches, which were built with whatever
     * plugins were on at the time. Only for changes to which plugins run.
     */
    private function flushAll(): void
    {
        $this->flush();

        try {
            Artisan::call('route:clear');
            Artisan::call('view:clear');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @throws PluginInstallException */
    private function findOrFail(string $slug): Plugin
    {
        return Plugin::where('slug', $slug)->first()
            ?? throw new PluginInstallException("There is no installed plugin called [{$slug}].");
    }

    private function hasTable(): bool
    {
        // Only a positive answer is remembered - see ModuleManager::hasTable().
        if ($this->tableExists === true) {
            return true;
        }

        try {
            return $this->tableExists = Schema::hasTable('plugins');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
