<?php

use App\Cms\Modules\ModuleManager;
use App\Cms\Search\SearchManager;
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
    /**
     * URL for a file in the active theme's published assets, with a version
     * appended so browsers fetch it again when it changes.
     *
     * Theme assets are served from a fixed path - themes/<slug>/css/theme.css -
     * so without a version a browser that has seen the file keeps using its
     * copy after an update replaces it. Themes used to handle that with the
     * version in theme.json, which only worked if somebody remembered to bump
     * it; the stylesheet changed twice without it moving, and every returning
     * visitor kept the old styles.
     *
     * The version is taken from the published file itself, so it changes
     * exactly when the file does. It costs a stat per asset, and only the
     * files a layout actually references are looked at.
     *
     * A theme that still appends its own ?v= is unaffected: the extra query is
     * ignored by the web server, and the URL still changes when the file does.
     */
    function theme_asset(string $file, bool $versioned = true): string
    {
        $relative = config('cms.themes.asset_url').'/'.themes()->activeSlug().'/'.ltrim($file, '/');
        $url = asset($relative);

        if (! $versioned) {
            return $url;
        }

        $path = public_path($relative);

        // Not published (yet): no version to give, and inventing one would
        // hide the missing file behind a URL that looks valid.
        if (! is_file($path)) {
            return $url;
        }

        return $url.'?v='.substr(md5(filemtime($path).'-'.filesize($path)), 0, 10);
    }
}

if (! function_exists('theme_section')) {
    /**
     * Render one of the active theme's builder sections with its declared
     * defaults, so a theme's own templates and the visual builder draw the
     * section from the same view with the same settings.
     *
     *     {!! theme_section('hero') !!}
     *     {!! theme_section('rail', ['title' => 'New in', 'source' => 'latest']) !!}
     */
    function theme_section(string $key, array $settings = []): string
    {
        $sections = app(\App\Cms\Themes\ThemeSections::class);
        $section = $sections->get($key);

        if (! $section) {
            return '';
        }

        return view('theme::'.$section['view'], [
            'settings' => array_merge($sections->defaults($key), $settings),
            'context' => [],
            'editing' => false,
            'model' => null,
        ])->render();
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

if (! function_exists('media_url')) {
    /**
     * Turn a stored media path into a URL, leaving addresses that are already
     * absolute alone. Settings of type 'image' hold a path on the media disk,
     * but an admin may also paste a CDN address into one.
     */
    function media_url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return str_starts_with($path, 'http') || str_starts_with($path, 'data:')
            ? $path
            : \Illuminate\Support\Facades\Storage::disk(config('cms.media.disk'))->url($path);
    }
}

if (! function_exists('site_logo_url')) {
    /**
     * The site logo in dark ink, for light backgrounds: whatever the owner
     * uploaded under Settings -> General, falling back to the logo this
     * product ships with.
     *
     * Views should call this instead of reading setting('site_logo')
     * themselves - that returns a media-disk path and is empty on a fresh
     * install, which is how every header ended up with its own copy of the
     * same "is it set?" branch.
     */
    function site_logo_url(bool $small = false): string
    {
        return media_url(setting('site_logo'))
            ?? brand_asset($small ? 'logo_small' : 'logo')
            ?? '';
    }
}

if (! function_exists('site_logo_light_url')) {
    /**
     * The site logo in light ink, for dark backgrounds.
     *
     * Falls back to the owner's ordinary logo before it falls back to ours: a
     * site that uploaded one logo and never made a light version is better
     * served by their own mark, even at poor contrast, than by somebody
     * else's brand appearing in their admin panel.
     */
    function site_logo_light_url(bool $small = false): string
    {
        return media_url(setting('site_logo_light'))
            ?? media_url(setting('site_logo'))
            ?? brand_asset($small ? 'logo_small_light' : 'logo_light')
            ?? '';
    }
}

if (! function_exists('site_favicon_url')) {
    /** The favicon the owner uploaded, or the one this product ships with. */
    function site_favicon_url(): string
    {
        return media_url(setting('site_favicon')) ?? brand_asset('favicon') ?? '';
    }
}

if (! function_exists('site_favicon_light_url')) {
    /**
     * The favicon for browsers reporting a dark colour scheme.
     *
     * There is deliberately no upload field for this one: an uploaded favicon
     * is used as-is for both schemes, because asking a site owner for two
     * 16-pixel icons buys less than it costs them.
     */
    function site_favicon_light_url(): string
    {
        return media_url(setting('site_favicon')) ?? brand_asset('favicon_light') ?? '';
    }
}

if (! function_exists('site_color_scheme')) {
    /**
     * Which ink the front end should use: 'light', 'dark', or 'system' when
     * the choice belongs to the visitor's browser and has to be made in CSS
     * rather than here.
     */
    function site_color_scheme(): string
    {
        $mode = (string) setting('dark_mode', 'system');

        return in_array($mode, ['light', 'dark'], true) ? $mode : 'system';
    }
}

if (! function_exists('site_logo_is_custom')) {
    /** Whether the owner has replaced the shipped logo with their own. */
    function site_logo_is_custom(): bool
    {
        return filled(setting('site_logo')) || filled(setting('site_logo_light'));
    }
}

if (! function_exists('seo')) {
    function seo(): SeoManager
    {
        return app(SeoManager::class);
    }
}

if (! function_exists('search')) {
    function search(): SearchManager
    {
        return app(SearchManager::class);
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
