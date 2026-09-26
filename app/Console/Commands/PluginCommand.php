<?php

namespace App\Console\Commands;

use App\Cms\Plugins\PluginInstallException;
use App\Cms\Plugins\PluginManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Plugins from the command line.
 *
 * Mostly for the day a plugin takes the admin panel down with it: switching
 * it off here needs nothing but a shell. Also starts a new plugin from a
 * working skeleton, and builds the ZIP a plugin is distributed as - checked by
 * the same manifest rules an upload goes through, with the directory entry
 * that lists it printed alongside.
 */
class PluginCommand extends Command
{
    protected $signature = 'cms:plugin
        {action : list, make, enable, disable, uninstall, sync or package}
        {slug? : The plugin folder name}
        {--name= : make: the plugin\'s display name (default: from the slug)}
        {--author= : make: the author shown on the Plugins screen}
        {--output= : package: where to write the ZIP (default: storage/app/private/plugin-packages)}
        {--url= : package: folder URL the ZIP will be served from, for the directory entry}';

    protected $description = 'Create, list, switch on or off, uninstall or package a plugin';

    public function handle(PluginManager $plugins): int
    {
        $action = $this->argument('action');
        $slug = (string) $this->argument('slug');

        if (! in_array($action, ['list', 'sync'], true) && $slug === '') {
            $this->error("The {$action} action needs a plugin slug.");

            return self::FAILURE;
        }

        try {
            return match ($action) {
                'list' => $this->list($plugins),
                'sync' => $this->sync($plugins),
                'make' => $this->make($plugins, $slug),
                'enable' => $this->done($plugins->enable($slug), "{$slug} is now on."),
                'disable' => $this->done($plugins->disable($slug), "{$slug} is now off."),
                'uninstall' => $this->done($plugins->uninstall($slug), "{$slug} uninstalled."),
                'package' => $this->package($plugins, $slug),
                default => $this->unknown($action),
            };
        } catch (PluginInstallException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function list(PluginManager $plugins): int
    {
        $invalid = $plugins->sync();

        $this->table(['Slug', 'Name', 'Version', 'State'], collect($plugins->all())->map(fn ($p) => [
            $p->slug, $p->name, $p->version, $p->enabled ? 'on' : 'off',
        ])->values()->all());

        foreach ($invalid as $folder => $reason) {
            $this->warn("plugins/{$folder} skipped: {$reason}");
        }

        if ($plugins->safeMode()) {
            $this->warn('Safe mode is on (CMS_PLUGINS_SAFE_MODE): no plugin is loaded.');
        }

        return self::SUCCESS;
    }

    private function sync(PluginManager $plugins): int
    {
        $invalid = $plugins->sync();
        $this->info(count($plugins->all()).' plugin(s) known.');

        foreach ($invalid as $folder => $reason) {
            $this->warn("plugins/{$folder} skipped: {$reason}");
        }

        return self::SUCCESS;
    }

    private function package(PluginManager $plugins, string $slug): int
    {
        $source = $plugins->path($slug);
        $manifest = $plugins->readManifest($source);

        if ($manifest['slug'] !== $slug) {
            throw new PluginInstallException("The folder is [{$slug}] but plugin.json says [{$manifest['slug']}].");
        }

        // Beside theme packages, on the private disk: never web-reachable, and
        // never swept into a CMS release.
        $directory = $this->option('output') ?: storage_path('app/private/plugin-packages');
        File::ensureDirectoryExists($directory);

        $zipPath = $directory.DIRECTORY_SEPARATOR."{$slug}-{$manifest['version']}.zip";
        @unlink($zipPath);

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->error("Could not write {$zipPath}.");

            return self::FAILURE;
        }

        $count = 0;

        foreach (File::allFiles($source, true) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            // Development leftovers never travel.
            if (preg_match('#(^|/)(\.|node_modules/|vendor/bin/|tests/)#', $relative)) {
                continue;
            }

            // Forward slashes, always - see ThemeInstaller::extract() for what
            // a Windows-style entry name does to a Linux server.
            $zip->addFile($file->getPathname(), "{$slug}/{$relative}");
            $count++;
        }

        $zip->close();

        $sha256 = hash_file('sha256', $zipPath);

        $this->info("Packaged {$manifest['name']} {$manifest['version']}: {$count} files.");
        $this->line($zipPath);
        $this->line("SHA-256: {$sha256}");

        // The entry for the plugin directory's catalogue, so the checksum is
        // never copied by hand - a wrong one makes every site refuse the file.
        $folder = rtrim((string) ($this->option('url') ?: "https://www.example.com/marketplace/{$slug}"), '/');
        $entry = array_filter([
            'slug' => $slug,
            'name' => $manifest['name'],
            'version' => (string) $manifest['version'],
            'author' => $manifest['author'] ?? null,
            'author_url' => $manifest['author_url'] ?? null,
            'description' => $manifest['description'] ?? null,
            'tags' => [],
            'download' => "{$folder}/".basename($zipPath),
            'sha256' => $sha256,
            'size' => filesize($zipPath),
            'requires' => $manifest['requires'] !== '0' ? $manifest['requires'] : null,
            'tested' => cms_version(),
            'price' => 0,
            'purchase_url' => null,
            'license' => $manifest['license'] ?? null,
            'updated_at' => now()->toDateString(),
        ], fn ($value) => $value !== null);

        $this->newLine();
        $this->line('Directory entry (add to "items" in the plugin catalogue; for a paid plugin set price and purchase_url):');
        $this->line(json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    /**
     * A new plugin that installs, switches on and shows a settings screen as
     * it stands - the quickest way to see how the pieces fit.
     */
    private function make(PluginManager $plugins, string $slug): int
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new PluginInstallException('A plugin slug may only contain lowercase letters, numbers and single hyphens, e.g. my-plugin.');
        }

        $directory = $plugins->path($slug);

        if (file_exists($directory)) {
            throw new PluginInstallException("plugins/{$slug} already exists.");
        }

        $studly = \Illuminate\Support\Str::studly($slug);
        $name = (string) ($this->option('name') ?: \Illuminate\Support\Str::headline($slug));
        $namespace = trim((string) config('cms.plugins.namespace', 'RadiusPlugins'), '\\').'\\'.$studly;
        $provider = "{$studly}ServiceProvider";

        $files = [
            'plugin.json' => json_encode(array_filter([
                'name' => $name,
                'slug' => $slug,
                'version' => '1.0.0',
                'description' => "{$name} for Radius.",
                'author' => $this->option('author') ?: null,
                'requires' => cms_version(),
                'namespace' => $namespace,
                'provider' => "{$namespace}\\{$provider}",
                'settings_route' => "admin.{$slug}.settings",
            ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",

            "src/{$provider}.php" => <<<PHP
                <?php

                namespace {$namespace};

                use App\\Cms\\Plugins\\PluginServiceProvider;

                class {$provider} extends PluginServiceProvider
                {
                    public function register(): void
                    {
                        //
                    }

                    public function boot(): void
                    {
                        \$this->loadPluginViews();
                        \$this->loadAdminRoutes();

                        \$this->hooks()->addAdminLink('System', '{$name}', 'admin.{$slug}.settings', 'admin.{$slug}.*');
                    }

                    /** Runs once, after the plugin is switched on and its migrations have run. */
                    public function activated(): void
                    {
                        //
                    }

                    /** Runs before the plugin's tables are rolled back and its folder deleted. */
                    public function uninstalling(): void
                    {
                        //
                    }
                }

                PHP,

            'routes/admin.php' => <<<PHP
                <?php

                use Illuminate\\Support\\Facades\\Route;

                // Under the admin prefix, for administrators only.
                Route::get('{$slug}', fn () => view('{$slug}::settings', [
                    'plugin' => plugins()->find('{$slug}'),
                ]))->name('{$slug}.settings');

                PHP,

            'resources/views/settings.blade.php' => <<<BLADE
                @extends('admin.layout')
                @section('title', '{$name}')

                @section('content')
                    <x-admin.card title="It works">
                        <p class="text-sm text-slate-600">
                            This screen comes from plugins/{$slug}/resources/views/settings.blade.php.
                            Version {{ \$plugin->version }}.
                        </p>
                    </x-admin.card>
                @endsection

                BLADE,

            'README.md' => "# {$name}\n\nA plugin for Radius.\n\n"
                ."Package it for distribution with `php artisan cms:plugin package {$slug}`.\n",
        ];

        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($directory.DIRECTORY_SEPARATOR.$path));
            File::put($directory.DIRECTORY_SEPARATOR.$path, $contents);
        }

        $plugins->sync();

        $this->info("Created plugins/{$slug}.");
        $this->line("Switch it on under System -> Plugins, or: php artisan cms:plugin enable {$slug}");

        return self::SUCCESS;
    }

    private function done(mixed $_, string $message): int
    {
        $this->info($message);

        return self::SUCCESS;
    }

    private function unknown(string $action): int
    {
        $this->error("Unknown action [{$action}]. Use list, make, enable, disable, uninstall, sync or package.");

        return self::FAILURE;
    }
}
