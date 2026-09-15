<?php

use App\Cms\Modules\ModuleManager;
use App\Cms\Seo\SeoManager;
use App\Cms\Settings\SettingsRepository;
use App\Cms\Themes\ThemeManager;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/*
|--------------------------------------------------------------------------
| CMS helpers
|--------------------------------------------------------------------------
| Thin wrappers over the container so views and controllers can stay terse.
| Each one resolves a singleton, so calling them repeatedly is cheap.
*/

if (! function_exists('settings')) {
    function settings(): SettingsRepository
    {
        return app(SettingsRepository::class);
    }
}

if (! function_exists('setting')) {
    /** Read one configurable option, falling back to its declared default. */
    function setting(string $key, mixed $default = null): mixed
    {
        return settings()->get($key, $default);
    }
}

if (! function_exists('modules')) {
    function modules(): ModuleManager
    {
        return app(ModuleManager::class);
    }
}

if (! function_exists('module_enabled')) {
    function module_enabled(string $slug): bool
    {
        return modules()->enabled($slug);
    }
}

if (! function_exists('themes')) {
    function themes(): ThemeManager
    {
        return app(ThemeManager::class);
    }
}

if (! function_exists('theme_view')) {
    /**
     * Resolve a view from the active theme, falling back to the CMS's own
     * copy when the theme does not override it.
     */
    function theme_view(string $name, ?string $fallback = null): string
    {
        return themes()->view($name, $fallback);
    }
}

if (! function_exists('theme_asset')) {
    function theme_asset(string $file): string
    {
        return asset(config('cms.themes.asset_url').'/'.themes()->activeSlug().'/'.ltrim($file, '/'));
    }
}

if (! function_exists('brand_asset')) {
    /**
     * URL for one of the default brand files shipped in public/.
     *
     * Goes through asset() rather than a hard-coded '/', so the address is
     * right whether the document root points at public/ or at the project
     * folder with the root .htaccess doing the rewriting.
     */
    function brand_asset(string $key): ?string
    {
        $path = config("cms.brand.{$key}");

        return $path ? asset($path) : null;
    }
}

if (! function_exists('site_logo_url')) {
    /**
     * The site logo: whatever the owner uploaded under Settings -> General,
     * falling back to the logo this product ships with.
     *
     * Views should call this instead of reading setting('site_logo')
     * themselves - that returns a media-disk path and is empty on a fresh
     * install, which is how every header ended up with its own copy of the
     * same "is it set?" branch.
     */
    function site_logo_url(bool $small = false): string
    {
        $uploaded = setting('site_logo');

        if (filled($uploaded)) {
            return str_starts_with($uploaded, 'http')
                ? $uploaded
                : \Illuminate\Support\Facades\Storage::disk(config('cms.media.disk'))->url($uploaded);
        }

        return brand_asset($small ? 'logo_small' : 'logo') ?? '';
    }
}

if (! function_exists('site_favicon_url')) {
    /** The favicon the owner uploaded, or the one this product ships with. */
    function site_favicon_url(): string
    {
        $uploaded = setting('site_favicon');

        if (filled($uploaded)) {
            return str_starts_with($uploaded, 'http')
                ? $uploaded
                : \Illuminate\Support\Facades\Storage::disk(config('cms.media.disk'))->url($uploaded);
        }

        return brand_asset('favicon') ?? '';
    }
}

if (! function_exists('site_logo_is_custom')) {
    /** Whether the owner has replaced the shipped logo with their own. */
    function site_logo_is_custom(): bool
    {
        return filled(setting('site_logo'));
    }
}

if (! function_exists('seo')) {
    function seo(): SeoManager
    {
        return app(SeoManager::class);
    }
}

if (! function_exists('money')) {
    /**
     * Format an amount held in minor units (cents, paise) for display.
     *
     * Prices are stored as integers throughout the shop so that repeated
     * addition never accumulates the rounding error floats would.
     */
    function money(int|float|null $minorUnits, ?string $currency = null): string
    {
        $amount = ((int) $minorUnits) / 100;
        $symbol = setting('shop_currency_symbol', '$');
        $decimals = 2;

        // Zero-decimal currencies are stored as whole units, not hundredths.
        if (in_array(strtoupper($currency ?? setting('shop_currency', 'USD')), ['JPY', 'KRW', 'VND', 'CLP'], true)) {
            $amount = (int) $minorUnits;
            $decimals = 0;
        }

        $formatted = number_format($amount, $decimals);

        return setting('shop_currency_position', 'before') === 'after'
            ? $formatted.$symbol
            : $symbol.$formatted;
    }
}

if (! function_exists('to_minor_units')) {
    /** Convert a decimal amount typed into an admin form into minor units. */
    function to_minor_units(int|float|string|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }
}

if (! function_exists('from_minor_units')) {
    /** Convert stored minor units back into a value for an admin form. */
    function from_minor_units(int|null $minorUnits): string
    {
        return number_format(((int) $minorUnits) / 100, 2, '.', '');
    }
}

if (! function_exists('activity')) {
    /**
     * Record an entry in the admin audit trail. Failures are swallowed: an
     * unwritable log table must never break the action being logged.
     */
    function activity(string $action, ?string $description = null, ?EloquentModel $subject = null, array $properties = []): void
    {
        try {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'description' => $description,
                'properties' => $properties ?: null,
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

if (! function_exists('cms_version')) {
    function cms_version(): string
    {
        return (string) config('cms.version', '1.0.0');
    }
}

if (! function_exists('cms_installed')) {
    /**
     * Whether setup has been completed.
     *
     * Deliberately a file check and not a database one: it is consulted on
     * requests that run before any database exists, which is precisely when
     * asking the database would fail.
     */
    function cms_installed(): bool
    {
        return file_exists(config('cms.install_lock'));
    }
}

if (! function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return url(trim(config('cms.admin_prefix', 'admin').'/'.ltrim($path, '/'), '/'));
    }
}

if (! function_exists('format_date')) {
    /** Format a date using the site owner's chosen format and timezone. */
    function format_date(mixed $date, ?string $format = null): string
    {
        if (blank($date)) {
            return '';
        }

        return \Illuminate\Support\Carbon::parse($date)
            ->timezone(setting('timezone', config('app.timezone')))
            ->format($format ?? setting('date_format', 'd M Y'));
    }
}

if (! function_exists('safe_route')) {
    /**
     * Resolve a named route, returning a fallback when the route does not
     * exist.
     *
     * Disabling a module unregisters its routes, so a model's url() would
     * otherwise fatal the moment an admin listed content belonging to a module
     * they had just switched off.
     */
    function safe_route(string $name, mixed $parameters = [], string $fallback = '#'): string
    {
        if (! \Illuminate\Support\Facades\Route::has($name)) {
            return $fallback;
        }

        try {
            return route($name, $parameters);
        } catch (\Throwable $e) {
            return $fallback;
        }
    }
}
