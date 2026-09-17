<?php

use App\Http\Controllers\Admin\BuilderController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CommentController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\ThemeMarketplaceController;
use App\Http\Controllers\Admin\ToolsController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
| The prefix comes from CMS_ADMIN_PREFIX in .env, so a site owner can move the
| panel off the predictable /admin path without touching code.
|
| Module-owned sections are wrapped in 'module:<slug>' as well as being hidden
| from the navigation, so a disabled module cannot be reached by typing a URL.
*/

Route::prefix(config('cms.admin_prefix', 'admin'))
    ->name('admin.')
    ->middleware('installed')
    ->group(function () {

        Route::middleware('guest')->group(function () {
            Route::get('login', [LoginController::class, 'show'])->name('login');
            Route::post('login', [LoginController::class, 'attempt'])
                ->middleware('throttle:10,1')
                ->name('login.attempt');
        });

        Route::post('logout', [LoginController::class, 'logout'])->name('logout');

        Route::middleware(['auth', 'staff', '2fa'])->group(function () {

            Route::get('/', DashboardController::class)->name('dashboard');

            // Content ---------------------------------------------------
            Route::middleware('module:pages')->group(function () {
                Route::resource('pages', PageController::class);
            });

            Route::middleware('module:blog')->group(function () {
                Route::resource('posts', PostController::class);
                Route::resource('comments', CommentController::class)->only(['index', 'update', 'destroy']);
                Route::patch('comments/{comment}/approve', [CommentController::class, 'approve'])->name('comments.approve');
            });

            // Categories serve both the blog and the shop, so the section
            // stays available while either of them is on.
            Route::resource('categories', CategoryController::class);

            Route::resource('menus', MenuController::class);
            Route::post('menus/{menu}/items', [MenuController::class, 'storeItem'])->name('menus.items.store');
            Route::patch('menu-items/{item}', [MenuController::class, 'updateItem'])->name('menus.items.update');
            Route::delete('menu-items/{item}', [MenuController::class, 'destroyItem'])->name('menus.items.destroy');
            Route::post('menus/{menu}/reorder', [MenuController::class, 'reorder'])->name('menus.reorder');

            // Shop ------------------------------------------------------
            Route::middleware('module:shop')->group(function () {
                Route::resource('products', ProductController::class);
                Route::resource('coupons', CouponController::class);

                Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
                Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
                Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
                Route::patch('orders/{order}/payment', [OrderController::class, 'markPaid'])->name('orders.paid');
                Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');
                Route::post('orders/{order}/reconcile', [OrderController::class, 'reconcile'])->name('orders.reconcile');
                Route::get('orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
            });

            // Gateway credentials are secrets, so this section is admin-only
            // even though the shop itself is not.
            Route::middleware(['module:payments', 'staff:admin'])->group(function () {
                Route::get('payments', [PaymentGatewayController::class, 'index'])->name('payments.index');
                Route::get('payments/{gateway}', [PaymentGatewayController::class, 'edit'])->name('payments.edit');
                Route::put('payments/{gateway}', [PaymentGatewayController::class, 'update'])->name('payments.update');
                Route::patch('payments/{gateway}/toggle', [PaymentGatewayController::class, 'toggle'])->name('payments.toggle');
            });

            // Media -----------------------------------------------------
            Route::get('media', [MediaController::class, 'index'])->name('media.index');
            Route::post('media', [MediaController::class, 'store'])->name('media.store');
            Route::patch('media/{medium}', [MediaController::class, 'update'])->name('media.update');
            Route::delete('media/{medium}', [MediaController::class, 'destroy'])->name('media.destroy');
            Route::get('media/browse', [MediaController::class, 'browse'])->name('media.browse');

            // Users -----------------------------------------------------
            // Admin-only without exception: anything that can create an
            // account, change a password or clear someone's second factor is
            // a way to become another user, so an editor must not reach it.
            Route::middleware('staff:admin')->group(function () {
                Route::resource('users', UserController::class);
                Route::patch('users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
                Route::delete('users/{user}/two-factor', [UserController::class, 'resetTwoFactor'])->name('users.two-factor.reset');
            });

            // Marketing -------------------------------------------------
            Route::middleware('module:contact')->group(function () {
                Route::get('contact-submissions', [ContactController::class, 'index'])->name('contact.index');
                Route::get('contact-submissions/{submission}', [ContactController::class, 'show'])->name('contact.show');
                Route::delete('contact-submissions/{submission}', [ContactController::class, 'destroy'])->name('contact.destroy');
            });

            Route::middleware('module:newsletter')->group(function () {
                Route::get('subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
                Route::get('subscribers/export', [SubscriberController::class, 'export'])->name('subscribers.export');
                Route::delete('subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');
            });

            // SEO -------------------------------------------------------
            Route::middleware('module:seo')->group(function () {
                Route::get('seo', [SeoController::class, 'index'])->name('seo.index');
                Route::put('seo/meta/{routeKey}', [SeoController::class, 'updateMeta'])->name('seo.meta.update');
                Route::get('seo/redirects', [SeoController::class, 'redirects'])->name('seo.redirects');
                Route::post('seo/redirects', [SeoController::class, 'storeRedirect'])->name('seo.redirects.store');
                Route::delete('seo/redirects/{redirect}', [SeoController::class, 'destroyRedirect'])->name('seo.redirects.destroy');
                Route::post('seo/sitemap/ping', [SeoController::class, 'pingSitemap'])->name('seo.sitemap.ping');
            });

            // Appearance ------------------------------------------------
            // A theme is Blade, and Blade is compiled to PHP and executed, so
            // installing or activating one is equivalent to deploying code.
            // That is an owner's decision, never an editor's.
            Route::middleware('staff:admin')->group(function () {
                // The theme directory. Declared before the {slug} routes below
                // so "marketplace" can never be read as a theme's folder name.
                Route::get('themes/marketplace', [ThemeMarketplaceController::class, 'index'])->name('themes.marketplace.index');
                Route::post('themes/marketplace/refresh', [ThemeMarketplaceController::class, 'refresh'])->name('themes.marketplace.refresh');
                Route::get('themes/marketplace/{slug}', [ThemeMarketplaceController::class, 'show'])->name('themes.marketplace.show');
                Route::post('themes/marketplace/{slug}/install', [ThemeMarketplaceController::class, 'install'])
                    ->middleware('throttle:10,1')
                    ->name('themes.marketplace.install');

                Route::get('themes', [ThemeController::class, 'index'])->name('themes.index');
                Route::post('themes/upload', [ThemeController::class, 'upload'])->name('themes.upload');
                Route::post('themes/{slug}/activate', [ThemeController::class, 'activate'])->name('themes.activate');
                Route::delete('themes/{slug}', [ThemeController::class, 'destroy'])->name('themes.destroy');
                Route::post('themes/sync', [ThemeController::class, 'sync'])->name('themes.sync');
            });

            // Visual builder --------------------------------------------
            Route::prefix('builder')->name('builder.')->group(function () {
                Route::get('/', [BuilderController::class, 'index'])->name('index');

                // The editor and the document loaded into its canvas.
                Route::get('edit/{type}/{id}', [BuilderController::class, 'edit'])->name('edit');
                Route::get('preview/{type}/{id}', [BuilderController::class, 'preview'])->name('preview');
                Route::get('region/{region}', [BuilderController::class, 'editRegion'])->name('region');
                Route::get('region/{region}/preview', [BuilderController::class, 'previewRegion'])->name('preview.region');

                // Saved sections. Declared before the {layout} routes so
                // "presets" is never mistaken for a layout id.
                Route::get('presets', [BuilderController::class, 'presets'])->name('presets');
                Route::post('presets', [BuilderController::class, 'storePreset'])->name('presets.store');

                Route::patch('toggle/{type}/{id}', [BuilderController::class, 'toggle'])->name('toggle');

                // A ready-made starting point for a theme region.
                Route::get('starter/{region}/{key}', [BuilderController::class, 'starter'])->name('starter');

                // Endpoints the editor talks to while you work.
                Route::post('{layout}/draft', [BuilderController::class, 'saveDraft'])->name('draft');
                Route::post('{layout}/publish', [BuilderController::class, 'publish'])->name('publish');
                Route::post('{layout}/render', [BuilderController::class, 'render'])->name('render');
                Route::post('{layout}/styles', [BuilderController::class, 'styles'])->name('styles');

                // The recovery path: discard a layout and fall back to the
                // theme's own markup, or to the classic editor content.
                Route::post('{layout}/restore-default', [BuilderController::class, 'restoreDefault'])->name('restore-default');

                Route::get('{layout}/revisions', [BuilderController::class, 'revisions'])->name('revisions');
                Route::post('{layout}/revisions/{revision}/restore', [BuilderController::class, 'restore'])->name('revisions.restore');
            });

            // System ----------------------------------------------------
            // Switching a module off removes routes and sections from the
            // whole site, which is a configuration decision, not an editing one.
            Route::middleware('staff:admin')->group(function () {
                Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
                Route::patch('modules/{slug}/toggle', [ModuleController::class, 'toggle'])->name('modules.toggle');
            });

            // Settings screens are admin-only; editors stop at content.
            Route::middleware('staff:admin')->group(function () {
                Route::get('settings/{group?}', [SettingsController::class, 'edit'])->name('settings.edit');
                Route::put('settings/{group}', [SettingsController::class, 'update'])->name('settings.update');

                Route::get('system', [SystemController::class, 'index'])->name('system.index');
                Route::get('system/activity', [SystemController::class, 'activity'])->name('system.activity');
                Route::get('system/logs', [SystemController::class, 'logs'])->name('system.logs');
                Route::post('system/security-check', [SystemController::class, 'checkExposure'])->name('system.security-check');

                // Updates. Admin-only, and never reachable by an editor: this
                // group rewrites the application's own code.
                Route::get('updates', [UpdateController::class, 'index'])->name('updates.index');
                Route::post('updates/check', [UpdateController::class, 'check'])->name('updates.check');
                Route::post('updates/apply', [UpdateController::class, 'apply'])->name('updates.apply');
                Route::get('updates/finalize', [UpdateController::class, 'finalize'])->name('updates.finalize');
                Route::post('updates/rollback', [UpdateController::class, 'rollback'])->name('updates.rollback');
                Route::post('updates/backup', [UpdateController::class, 'backupNow'])->name('updates.backup');
                Route::get('updates/backups/{name}', [UpdateController::class, 'downloadBackup'])->name('updates.backups.download');
                Route::delete('updates/backups/{name}', [UpdateController::class, 'destroyBackup'])->name('updates.backups.destroy');

                Route::post('tools/cache/clear', [ToolsController::class, 'clearCache'])->name('tools.cache.clear');
                Route::post('tools/cache/optimize', [ToolsController::class, 'optimize'])->name('tools.cache.optimize');
                Route::post('tools/storage/link', [ToolsController::class, 'storageLink'])->name('tools.storage.link');
                Route::post('tools/mail/test', [ToolsController::class, 'testMail'])->name('tools.mail.test');
                Route::post('tools/sms/test', [ToolsController::class, 'testSms'])->name('tools.sms.test');
                Route::post('tools/push/test', [ToolsController::class, 'testPush'])->name('tools.push.test');
                Route::post('tools/search/rebuild', [ToolsController::class, 'rebuildSearch'])->name('tools.search.rebuild');
            });
        });
    });
