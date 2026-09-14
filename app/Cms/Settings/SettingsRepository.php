<?php

namespace App\Cms\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The single gateway to every configurable option in the CMS.
 *
 * The whole table is loaded once per request and cached, so reading a setting
 * anywhere - in a Blade view, a middleware, a gateway driver - costs an array
 * lookup rather than a query. Writes bust the cache.
 */
class SettingsRepository
{
    private const CACHE_KEY = 'cms.settings';
    private const CACHE_TTL = 86400;

    /** Decoded values, keyed by setting name. Null until first load. */
    private ?array $items = null;

    /** Guards against querying a table that does not exist yet (pre-install). */
    private ?bool $tableExists = null;

    public function all(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        if (! $this->hasTable()) {
            return $this->items = [];
        }

        $this->items = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Setting::query()
                ->get(['key', 'value', 'type', 'is_encrypted'])
                ->mapWithKeys(fn (Setting $s) => [$s->key => $this->decode($s)])
                ->all();
        });

        return $this->items;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        if ($value !== null && $value !== '') {
            return $value;
        }

        // Fall back to the schema default before the caller's default, so a
        // fresh install behaves sensibly even with an empty settings table.
        return $default ?? $this->schemaDefault($key);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function set(string $key, mixed $value, ?string $group = null): void
    {
        $definition = $this->definitionFor($key);
        $type = $definition['type'] ?? 'text';
        $encrypt = $type === 'secret';

        $stored = match (true) {
            $value === null => null,
            $type === 'boolean' => $value ? '1' : '0',
            is_array($value) => json_encode($value),
            default => (string) $value,
        };

        if ($encrypt && filled($stored)) {
            $stored = Crypt::encryptString($stored);
        }

        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $stored,
                'group' => $group ?? $this->groupFor($key),
                'type' => $type,
                'is_encrypted' => $encrypt,
            ]
        );

        $this->flush();
    }

    /** Bulk write, used by the admin settings form. */
    public function setMany(array $values, ?string $group = null): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $group);
        }
    }

    public function forget(string $key): void
    {
        Setting::where('key', $key)->delete();
        $this->flush();
    }

    public function flush(): void
    {
        $this->items = null;
        Cache::forget(self::CACHE_KEY);
    }

    // Schema helpers ------------------------------------------------------

    /** The field definition from config/settings.php, if the key is declared. */
    public function definitionFor(string $key): ?array
    {
        foreach (config('settings', []) as $group) {
            if (isset($group['fields'][$key])) {
                return $group['fields'][$key];
            }
        }

        return null;
    }

    public function groupFor(string $key): string
    {
        foreach (config('settings', []) as $groupKey => $group) {
            if (isset($group['fields'][$key])) {
                return $groupKey;
            }
        }

        return 'general';
    }

    public function schemaDefault(string $key): mixed
    {
        return $this->definitionFor($key)['default'] ?? null;
    }

    /** Every declared key with its default, used to seed a fresh install. */
    public function defaults(): array
    {
        $defaults = [];

        foreach (config('settings', []) as $group) {
            foreach ($group['fields'] ?? [] as $key => $field) {
                if (($field['type'] ?? 'text') === 'notice') {
                    continue;
                }
                $defaults[$key] = $field['default'] ?? null;
            }
        }

        return $defaults;
    }

    /**
     * Writes any declared setting that has no row yet. Safe to run repeatedly;
     * it never overwrites a value the site owner has already chosen.
     */
    public function seedMissingDefaults(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        $existing = Setting::pluck('key')->flip();
        $written = 0;

        foreach ($this->defaults() as $key => $value) {
            if ($existing->has($key) || $value === null) {
                continue;
            }
            $this->set($key, $value);
            $written++;
        }

        return $written;
    }

    // Internals -----------------------------------------------------------

    private function decode(Setting $setting): mixed
    {
        $value = $setting->value;

        if ($setting->is_encrypted && filled($value)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Throwable $e) {
                // Almost always a changed APP_KEY. Fail soft: an unreadable
                // SMTP password should not take the whole site down.
                Log::warning("Could not decrypt setting [{$setting->key}].");

                return null;
            }
        }

        return match ($setting->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? $value + 0 : 0,
            default => $value,
        };
    }

    private function hasTable(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        try {
            return $this->tableExists = Schema::hasTable('settings');
        } catch (\Throwable $e) {
            // No database configured yet - the installer is about to run.
            return $this->tableExists = false;
        }
    }
}
