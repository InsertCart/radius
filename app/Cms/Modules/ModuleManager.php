<?php

namespace App\Cms\Modules;

use App\Models\Module;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Decides which optional features are switched on.
 *
 * The enabled set is read once and cached, because it is consulted on nearly
 * every request - by the route registrar, the admin navigation and the
 * @module Blade directive. A disabled module registers no routes at all, so
 * turning one off genuinely removes work rather than just hiding links.
 */
class ModuleManager
{
    private const CACHE_KEY = 'cms.modules.enabled';
    private const CACHE_TTL = 86400;

    /** Slugs of enabled modules. Null until first load. */
    private ?array $enabled = null;

    private ?bool $tableExists = null;

    /** Every module declared in config/cms.php, merged with its database row. */
    public function all(): array
    {
        $declared = config('cms.modules', []);
        $rows = $this->hasTable() ? Module::all()->keyBy('slug') : collect();

        $modules = [];

        foreach ($declared as $slug => $definition) {
            $row = $rows->get($slug);

            $modules[$slug] = array_merge($definition, [
                'slug' => $slug,
                'enabled' => $row ? (bool) $row->enabled : true,
                'installed' => (bool) $row,
                'config' => $row?->config ?? [],
            ]);
        }

        return $modules;
    }

    public function enabledSlugs(): array
    {
        if ($this->enabled !== null) {
            return $this->enabled;
        }

        if (! $this->hasTable()) {
            // Before the installer has run, treat everything as available so
            // the installer's own routes and views can boot.
            return $this->enabled = array_keys(config('cms.modules', []));
        }

        return $this->enabled = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => Module::enabled()->pluck('slug')->all()
        );
    }

    public function enabled(string $slug): bool
    {
        return in_array($slug, $this->enabledSlugs(), true);
    }

    public function disabled(string $slug): bool
    {
        return ! $this->enabled($slug);
    }

    /** True only when every named module is on. */
    public function allEnabled(string ...$slugs): bool
    {
        foreach ($slugs as $slug) {
            if (! $this->enabled($slug)) {
                return false;
            }
        }

        return true;
    }

    public function anyEnabled(string ...$slugs): bool
    {
        foreach ($slugs as $slug) {
            if ($this->enabled($slug)) {
                return true;
            }
        }

        return false;
    }

    public function isCore(string $slug): bool
    {
        return (bool) config("cms.modules.{$slug}.core", false);
    }

    public function definition(string $slug): array
    {
        return config("cms.modules.{$slug}", []);
    }

    public function name(string $slug): string
    {
        return config("cms.modules.{$slug}.name", ucfirst($slug));
    }

    // Mutation ------------------------------------------------------------

    /**
     * Turning a module on also turns on whatever it depends on - the shop is
     * useless without payments, so enabling it silently enables that too.
     *
     * @return string[] Slugs that were switched on as dependencies.
     */
    public function enable(string $slug): array
    {
        $alsoEnabled = [];

        foreach ($this->definition($slug)['requires'] ?? [] as $dependency) {
            if ($this->disabled($dependency)) {
                Module::where('slug', $dependency)->update(['enabled' => true]);
                $alsoEnabled[] = $dependency;
            }
        }

        Module::where('slug', $slug)->update(['enabled' => true]);
        $this->flush();

        return $alsoEnabled;
    }

    /**
     * @return string[] Slugs switched off because they depended on this one.
     *
     * @throws \RuntimeException when the module is structural.
     */
    public function disable(string $slug): array
    {
        if ($this->isCore($slug)) {
            throw new \RuntimeException("The [{$this->name($slug)}] module is required and cannot be disabled.");
        }

        // Anything that requires this module has to go down with it, or it
        // would be left half-working (a shop with no way to take money).
        $alsoDisabled = [];

        foreach (config('cms.modules', []) as $otherSlug => $definition) {
            if (in_array($slug, $definition['requires'] ?? [], true) && $this->enabled($otherSlug)) {
                Module::where('slug', $otherSlug)->update(['enabled' => false]);
                $alsoDisabled[] = $otherSlug;
            }
        }

        Module::where('slug', $slug)->update(['enabled' => false]);
        $this->flush();

        return $alsoDisabled;
    }

    public function toggle(string $slug): bool
    {
        $this->enabled($slug) ? $this->disable($slug) : $this->enable($slug);

        return $this->enabled($slug);
    }

    /** Which enabled modules would break if $slug were switched off. */
    public function dependents(string $slug): array
    {
        return collect(config('cms.modules', []))
            ->filter(fn ($definition, $other) => in_array($slug, $definition['requires'] ?? [], true) && $this->enabled($other))
            ->keys()
            ->all();
    }

    /**
     * Creates a row for any module declared in config but missing from the
     * database. Called by the installer and by `php artisan cms:sync`.
     */
    public function sync(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        $created = 0;
        $sort = 0;

        foreach (config('cms.modules', []) as $slug => $definition) {
            $module = Module::firstOrNew(['slug' => $slug]);

            if (! $module->exists) {
                $module->enabled = true;
                $created++;
            }

            $module->fill([
                'name' => $definition['name'] ?? ucfirst($slug),
                'description' => $definition['description'] ?? null,
                'is_core' => $definition['core'] ?? false,
                'sort_order' => $sort++,
            ])->save();
        }

        $this->flush();

        return $created;
    }

    public function flush(): void
    {
        $this->enabled = null;
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
            return $this->tableExists = Schema::hasTable('modules');
        } catch (\Throwable $e) {
            // No connection yet, most likely. Not remembered, for the same reason.
            return false;
        }
    }
}
