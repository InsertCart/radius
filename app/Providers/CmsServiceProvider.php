<?php

namespace App\Providers;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Builder\LayoutRenderer;
use App\Cms\Builder\RegionManager;
use App\Cms\Builder\StyleCompiler;
use App\Cms\Builder\StyleRegistry;
use App\Cms\Firebase\FirebaseManager;
use App\Cms\Mail\MailConfigurator;
use App\Cms\Modules\ModuleManager;
use App\Cms\Payments\PaymentManager;
use App\Cms\Search\SearchManager;
use App\Cms\Seo\SeoManager;
use App\Cms\Settings\SettingsRepository;
use App\Cms\Support\AdminNavigation;
use App\Cms\Sms\SmsManager;
use App\Cms\Themes\ThemeManager;
use App\Models\Menu;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the CMS on top of the Laravel skeleton: registers the managers as
 * singletons, applies the site owner's saved settings to the runtime config,
 * and exposes the Blade directives themes rely on.
 */
class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ([
            SettingsRepository::class,
            ModuleManager::class,
            ThemeManager::class,
            SeoManager::class,
            PaymentManager::class,
            SmsManager::class,
            FirebaseManager::class,
            SearchManager::class,

            // Visual builder.
            BlockRegistry::class,
            StyleCompiler::class,
            LayoutRenderer::class,
            RegionManager::class,
            StyleRegistry::class,
        ] as $service) {
            $this->app->singleton($service);
        }
    }

    public function boot(): void
    {
        // Nothing below can run before the database exists. On a fresh copy
        // the installer handles the request instead.
        if ($this->isInstalled()) {
            $this->applyRuntimeSettings();
            app(ThemeManager::class)->registerViewNamespace();

            // Saved content has to reach the search index. Registered only
            // on an installed site: the models' tables may not exist before.
            app(SearchManager::class)->observe();
        }

        $this->registerRateLimiters();

        $this->registerBladeDirectives();
        $this->shareViewState();

        Paginator::useTailwind();
    }

    /**
     * Pushes database-held settings into Laravel's config so that the mailer,
     * timezone and URL scheme all follow what the site owner chose in the
     * admin panel rather than what is hard-coded in .env.
     */
    private function applyRuntimeSettings(): void
    {
        $settings = app(SettingsRepository::class);

        if ($timezone = $settings->get('timezone')) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        if ($locale = $settings->get('locale')) {
            app()->setLocale($locale);
        }

        if ($name = $settings->get('site_name')) {
            config(['app.name' => $name]);
        }

        // Behind a load balancer or a shared-hosting proxy, Laravel often
        // cannot tell that the original request was HTTPS.
        if ($settings->bool('force_https')) {
            URL::forceScheme('https');
        }

        app(MailConfigurator::class)->apply();
    }

    private function registerBladeDirectives(): void
    {
        // @module('shop') ... @endmodule
        Blade::if('module', fn (string $slug) => modules()->enabled($slug));

        // @anymodule('shop', 'blog') ... @endanymodule
        Blade::if('anymodule', fn (string ...$slugs) => modules()->anyEnabled(...$slugs));

        // @setting('maintenance_mode') ... @endsetting
        Blade::if('setting', fn (string $key) => (bool) setting($key));

        // @admin ... @endadmin
        Blade::if('admin', fn () => auth()->check() && auth()->user()->isAdmin());

        // @staff ... @endstaff
        Blade::if('staff', fn () => auth()->check() && auth()->user()->isStaff());

        // {!! money helper as a directive for terse theme markup !!}
        Blade::directive('money', fn ($expression) => "<?php echo e(money({$expression})); ?>");

        // Emits the full SEO head block: title, meta, Open Graph, JSON-LD.
        Blade::directive('seoHead', fn () => '<?php echo seo()->render(); ?>');

        // The live search script, once per page, only when live results are
        // on. Search forms put this next to themselves, so a theme never has
        // to add it to its layout.
        Blade::directive('searchScripts', fn () => '<?php echo search()->scripts(); ?>');

        $this->registerBuilderDirectives();
    }

    /**
     * Directives the visual builder adds to themes.
     *
     * @region is the bridge between hand-written templates and the editor. A
     * theme wraps a part of itself:
     *
     *     @region('header')
     *         ... the theme's own header ...
     *     @endregion
     *
     * With no layout built for that region the markup inside simply renders,
     * so an existing theme behaves exactly as before. Once an admin designs a
     * header in the editor, that layout is rendered instead.
     */
    private function registerBuilderDirectives(): void
    {
        $manager = RegionManager::class;

        // An optional second argument passes context to the layout's widgets:
        // @region('product', ['model' => $product]).
        Blade::directive('region', function (string $expression) use ($manager) {
            return "<?php \$__cbArgs = [{$expression}]; \$__cbRegion = \$__cbArgs[0];
                app({$manager}::class)->markRendered(\$__cbRegion);
                if (app({$manager}::class)->has(\$__cbRegion)):
                    echo app({$manager}::class)->render(\$__cbRegion, \$__cbArgs[1] ?? []);
                else: ?>";
        });

        Blade::directive('endregion', fn () => '<?php endif; unset($__cbRegion, $__cbArgs); ?>');

        // True when a region has been taken over by the builder, for themes
        // that want to adjust their own wrapper markup.
        Blade::if('regionbuilt', fn (string $region) => app(RegionManager::class)->has($region));

        // The collected builder stylesheet plus any web fonts, for the head.
        Blade::directive('builderStyles', fn () => '<?php echo app('.StyleRegistry::class.'::class)->render(); ?>');

        // The small runtime that powers accordions, tabs, counters and
        // lightboxes. Only emitted on pages that actually contain a layout.
        Blade::directive('builderScripts', function () {
            return '<?php echo view("builder.runtime")->render(); ?>';
        });
    }

    /**
     * Live search fires a request per pause in typing, so it gets its own,
     * owner-adjustable budget per visitor rather than sharing a generic one.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('search', function ($request) {
            return Limit::perMinute(app(SearchManager::class)->rateLimit())->by($request->ip());
        });
    }

    /** Values views can rely on without every controller passing them. */
    private function shareViewState(): void
    {
        // The sidebar is rebuilt per request because it depends on the signed-in
        // user's role, the enabled modules and the live badge counts.
        View::composer('admin.*', function ($view) {
            $view->with('adminNavigation', app(AdminNavigation::class)->build());
        });

        View::composer(['theme::*', 'components.*'], function ($view) {
            $view->with('siteMenus', $this->menusForTheme());
        });
    }

    /**
     * Menus the active theme can render, keyed by slug. Items belonging to a
     * disabled module are filtered out here so themes never have to check.
     */
    private function menusForTheme(): array
    {
        if (! $this->isInstalled()) {
            return [];
        }

        try {
            return Cache::remember('cms.menus.rendered', 3600, function () {
                return Menu::with('tree.children')->get()->mapWithKeys(function (Menu $menu) {
                    return [$menu->slug => $menu->tree];
                })->all();
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function isInstalled(): bool
    {
        return file_exists(config('cms.install_lock'));
    }
}
