<?php

namespace App\Cms\Api;

use App\Cms\Settings\SettingsRepository;

/**
 * Decides what the mobile API exposes.
 *
 * Three questions are answered here, and they are asked in this order:
 *
 *   1. Is the 'api' module on? If not there is no API - routes/api.php is
 *      never even loaded.
 *   2. Is this group of endpoints switched on? Groups are declared in
 *      config/api.php and toggled under Admin -> Mobile API.
 *   3. Does the CMS module behind that group still exist? A shop API cannot
 *      be on while the shop module is off, whatever the setting says.
 *
 * Every read goes through the settings repository, which caches the whole
 * table per request, so asking these questions on every API call is free.
 */
class ApiManager
{
    /** Settings keys are namespaced so the API owns a clean corner of the table. */
    private const FEATURE_PREFIX = 'api_feature_';

    private const SECURITY_PREFIX = 'api_';

    public function __construct(private SettingsRepository $settings) {}

    // The API as a whole ---------------------------------------------------

    /** Whether the API answers at all. */
    public function enabled(): bool
    {
        return modules()->enabled('api');
    }

    public function prefix(): string
    {
        return trim((string) config('api.prefix', 'api'), '/');
    }

    public function version(): string
    {
        return (string) config('api.version', 'v1');
    }

    /** The address an app points itself at. */
    public function baseUrl(): string
    {
        return url($this->prefix().'/'.$this->version());
    }

    // Endpoint groups ------------------------------------------------------

    /** The raw catalogue from config/api.php. */
    public function catalogue(): array
    {
        return config('api.features', []);
    }

    public function definition(string $key): array
    {
        return $this->catalogue()[$key] ?? [];
    }

    public function name(string $key): string
    {
        return $this->definition($key)['name'] ?? ucfirst($key);
    }

    /**
     * Whether a group can be switched on at all: its CMS module has to be
     * enabled first. There is no point offering a shop API on a site with no
     * shop, and honouring a stale setting would be worse than pointless.
     */
    public function available(string $key): bool
    {
        $definition = $this->definition($key);

        if ($definition === []) {
            return false;
        }

        $module = $definition['module'] ?? null;

        return $module === null || modules()->enabled($module);
    }

    /** Whether a group of endpoints is live right now. */
    public function feature(string $key): bool
    {
        if (! $this->enabled() || ! $this->available($key)) {
            return false;
        }

        if (! $this->storedFeature($key)) {
            return false;
        }

        // A group is only as on as the groups it depends on. Checked at read
        // time as well as at write time, because a module going off can take
        // a dependency down without anybody touching these settings.
        foreach ($this->definition($key)['requires'] ?? [] as $required) {
            if (! $this->feature($required)) {
                return false;
            }
        }

        return true;
    }

    /** True when every named group is on. */
    public function allFeatures(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (! $this->feature($key)) {
                return false;
            }
        }

        return true;
    }

    /** Slugs of every group that is currently answering. */
    public function enabledFeatures(): array
    {
        return array_values(array_filter(
            array_keys($this->catalogue()),
            fn (string $key) => $this->feature($key)
        ));
    }

    /**
     * The catalogue, merged with state, for the admin screen.
     *
     * @return array<string, array>
     */
    public function all(): array
    {
        $features = [];

        foreach ($this->catalogue() as $key => $definition) {
            $available = $this->available($key);

            $features[$key] = $definition + [
                'key' => $key,
                'enabled' => $this->feature($key),
                'available' => $available,
                'module_name' => isset($definition['module']) ? modules()->name($definition['module']) : null,
                'requires' => $definition['requires'] ?? [],
                'enables' => $definition['enables'] ?? [],
                'endpoints' => $definition['endpoints'] ?? [],
                'needed_by' => $this->dependents($key),
            ];
        }

        return $features;
    }

    /** Groups that would stop working if this one were switched off. */
    public function dependents(string $key): array
    {
        return array_keys(array_filter(
            $this->catalogue(),
            fn (array $definition) => in_array($key, $definition['requires'] ?? [], true)
        ));
    }

    // Writing --------------------------------------------------------------

    /**
     * Save the set of groups an admin ticked.
     *
     * The submitted list is not taken at face value. A group whose module is
     * off cannot come on; a group that needs another one drags it on; and a
     * group whose dependency is off goes off with it. Whatever is written
     * here is therefore a set that actually works.
     *
     * @param  string[]  $keys
     * @return array{0: string[], 1: string[]} Keys switched on, and off, as a side effect.
     */
    public function saveFeatures(array $keys): array
    {
        $before = $this->enabledFeatures();

        $wanted = array_values(array_intersect(
            array_keys($this->catalogue()),
            array_filter($keys, fn ($key) => $this->available((string) $key))
        ));

        // Anything newly ticked brings its companions along - this is how
        // switching the shop on switches customer accounts on with it.
        foreach (array_diff($wanted, $before) as $key) {
            foreach ($this->definition($key)['enables'] ?? [] as $companion) {
                if ($this->available($companion) && ! in_array($companion, $wanted, true)) {
                    $wanted[] = $companion;
                }
            }
        }

        $resolved = $this->resolve($wanted);

        foreach (array_keys($this->catalogue()) as $key) {
            $this->settings->set(
                self::FEATURE_PREFIX.$key,
                in_array($key, $resolved, true) ? '1' : '0',
                'api'
            );
        }

        $after = $this->enabledFeatures();

        return [
            // Only the surprises are reported back: what the admin did not
            // tick but got anyway, and what they left ticked but lost.
            array_values(array_diff($after, $before, $keys)),
            array_values(array_intersect(array_diff($before, $after), $keys)),
        ];
    }

    /**
     * Close a submitted set under its dependencies: pull in what is required,
     * then drop anything still standing on something that is not there.
     *
     * @param  string[]  $wanted
     * @return string[]
     */
    private function resolve(array $wanted): array
    {
        $set = array_values(array_unique($wanted));

        // Pull in requirements, repeatedly, so a chain two deep resolves too.
        do {
            $added = false;

            foreach ($set as $key) {
                foreach ($this->definition($key)['requires'] ?? [] as $required) {
                    if (! in_array($required, $set, true) && $this->available($required)) {
                        $set[] = $required;
                        $added = true;
                    }
                }
            }
        } while ($added);

        // Then drop whatever is left depending on something absent - a
        // requirement whose own module is off, most often.
        do {
            $removed = false;

            foreach ($set as $index => $key) {
                foreach ($this->definition($key)['requires'] ?? [] as $required) {
                    if (! in_array($required, $set, true)) {
                        unset($set[$index]);
                        $removed = true;

                        break;
                    }
                }
            }

            $set = array_values($set);
        } while ($removed);

        return $set;
    }

    /** Switch the whole catalogue to its shipped defaults. Used the first time the API comes on. */
    public function seedDefaults(): void
    {
        $defaults = [];

        foreach ($this->catalogue() as $key => $definition) {
            if (($definition['default'] ?? false) && $this->available($key)) {
                $defaults[] = $key;
            }
        }

        $this->saveFeatures($defaults);
    }

    /** Whether an owner has ever visited the API screen and saved it. */
    public function configured(): bool
    {
        foreach (array_keys($this->catalogue()) as $key) {
            if ($this->settings->has(self::FEATURE_PREFIX.$key)) {
                return true;
            }
        }

        return false;
    }

    // Security options -----------------------------------------------------

    public function signatureRequired(): bool
    {
        return $this->securityBool('signature_required');
    }

    public function signatureWindow(): int
    {
        return max(30, $this->securityInt('signature_window'));
    }

    public function tokenLifetimeDays(): int
    {
        return max(1, $this->securityInt('token_days'));
    }

    public function refreshLifetimeDays(): int
    {
        return max($this->tokenLifetimeDays(), $this->securityInt('refresh_days'));
    }

    public function rateLimit(): int
    {
        return max(1, $this->securityInt('rate_limit'));
    }

    public function authRateLimit(): int
    {
        return max(1, $this->securityInt('auth_rate_limit'));
    }

    public function allowsStaff(): bool
    {
        return $this->securityBool('allow_staff');
    }

    public function allowsGuestCart(): bool
    {
        return $this->securityBool('guest_cart');
    }

    /** Every security option with its current value, for the admin form. */
    public function security(): array
    {
        return [
            'signature_required' => $this->signatureRequired(),
            'signature_window' => $this->signatureWindow(),
            'token_days' => $this->tokenLifetimeDays(),
            'refresh_days' => $this->refreshLifetimeDays(),
            'rate_limit' => $this->rateLimit(),
            'auth_rate_limit' => $this->authRateLimit(),
            'allow_staff' => $this->allowsStaff(),
            'guest_cart' => $this->allowsGuestCart(),
        ];
    }

    public function saveSecurity(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, (array) config('api.security', []))) {
                continue;
            }

            $this->settings->set(
                self::SECURITY_PREFIX.$key,
                is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                'api'
            );
        }
    }

    // Internals ------------------------------------------------------------

    private function storedFeature(string $key): bool
    {
        return $this->settings->bool(
            self::FEATURE_PREFIX.$key,
            (bool) ($this->definition($key)['default'] ?? false)
        );
    }

    private function securityBool(string $key): bool
    {
        return $this->settings->bool(
            self::SECURITY_PREFIX.$key,
            (bool) config("api.security.{$key}", false)
        );
    }

    private function securityInt(string $key): int
    {
        return (int) $this->settings->get(
            self::SECURITY_PREFIX.$key,
            (int) config("api.security.{$key}", 0)
        );
    }
}
