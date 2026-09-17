<?php

use App\Http\Controllers\Front\BlogController;
use App\Http\Controllers\Front\CartController;
use App\Http\Controllers\Front\CheckoutController;
use App\Http\Controllers\Front\ContactController;
use App\Http\Controllers\Front\HomeController;
use App\Http\Controllers\Front\NewsletterController;
use App\Http\Controllers\Front\PushController;
use App\Http\Controllers\Front\SearchController;
use App\Http\Controllers\Front\SeoController;
use App\Http\Controllers\Front\ShopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
| Routes for optional modules are only registered when that module is turned
| on. A disabled module therefore costs nothing at all: no route matching, no
| controller resolution, no view lookups.
|
| The catch-all page route lives in routes/pages.php, which is loaded after
| every other route file so it can never shadow one of them.
*/

Route::middleware('installed')->group(function () {

    Route::get('/', HomeController::class)->name('home');

    // SEO ---------------------------------------------------------------
    if (modules()->enabled('seo')) {
        Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
        Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
    }

    // Search ------------------------------------------------------------
    // Live results are always routed; the controller answers 404 while they
    // are switched off, so toggling the setting needs no route reload. The
    // results page is only routed when enabled, because /search would
    // otherwise shadow a CMS page an owner created with that slug.
    Route::get('search/suggest', [SearchController::class, 'suggest'])
        ->middleware('throttle:search')
        ->name('search.suggest');

    if (search()->pageEnabled()) {
        Route::get('search', [SearchController::class, 'index'])->name('search');
    }

    // Blog --------------------------------------------------------------
    if (modules()->enabled('blog')) {
        Route::prefix('blog')->name('blog.')->group(function () {
            Route::get('/', [BlogController::class, 'index'])->name('index');
            Route::get('category/{slug}', [BlogController::class, 'category'])->name('category');
            Route::get('tag/{slug}', [BlogController::class, 'tag'])->name('tag');
            Route::get('{slug}', [BlogController::class, 'show'])->name('show');
            Route::post('{slug}/comment', [BlogController::class, 'comment'])
                ->middleware('throttle:10,1')
                ->name('comment');
        });
    }

    // Shop --------------------------------------------------------------
    if (modules()->enabled('shop')) {
        Route::prefix('shop')->name('shop.')->group(function () {
            Route::get('/', [ShopController::class, 'index'])->name('index');
            Route::get('category/{slug}', [ShopController::class, 'category'])->name('category');
            Route::get('{slug}', [ShopController::class, 'show'])->name('show');
            Route::post('{slug}/review', [ShopController::class, 'review'])
                ->middleware('throttle:5,1')
                ->name('review');
        });

        Route::prefix('cart')->name('cart.')->group(function () {
            Route::get('/', [CartController::class, 'index'])->name('index');
            Route::post('add', [CartController::class, 'add'])->name('add');
            Route::patch('{item}', [CartController::class, 'update'])->name('update');
            Route::delete('{item}', [CartController::class, 'remove'])->name('remove');
            Route::post('coupon', [CartController::class, 'applyCoupon'])->name('coupon');
            Route::delete('coupon/remove', [CartController::class, 'removeCoupon'])->name('coupon.remove');
        });

        Route::prefix('checkout')->name('checkout.')->group(function () {
            Route::get('/', [CheckoutController::class, 'index'])->name('index');
            Route::post('/', [CheckoutController::class, 'store'])->middleware('throttle:20,1')->name('store');
            Route::get('pay/{order}', [CheckoutController::class, 'pay'])->name('pay');

            // The provider sends the customer back here. Both GET and POST are
            // accepted because PayU returns by form POST while the rest use GET.
            Route::match(['get', 'post'], 'return/{gateway}/{order}', [CheckoutController::class, 'handleReturn'])
                ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
                ->name('return');

            Route::match(['get', 'post'], 'cancel/{gateway}/{order}', [CheckoutController::class, 'cancel'])
                ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
                ->name('cancel');

            Route::get('success/{order}', [CheckoutController::class, 'success'])->name('success');
        });

        // Server-to-server callbacks. No CSRF token exists for these, and the
        // driver verifies the provider's signature instead.
        Route::post('webhooks/payments/{gateway}', [CheckoutController::class, 'webhook'])
            ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
            ->name('checkout.webhook');
    }

    // Contact -----------------------------------------------------------
    if (modules()->enabled('contact')) {
        Route::get('contact', [ContactController::class, 'show'])->name('contact');
        Route::post('contact', [ContactController::class, 'submit'])
            ->middleware('throttle:5,1')
            ->name('contact.submit');
    }

    // Newsletter --------------------------------------------------------
    if (modules()->enabled('newsletter')) {
        Route::post('newsletter/subscribe', [NewsletterController::class, 'subscribe'])
            ->middleware('throttle:5,1')
            ->name('newsletter.subscribe');
        Route::get('newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])
            ->name('newsletter.unsubscribe');
    }

    // Firebase push -----------------------------------------------------
    if (modules()->enabled('firebase')) {
        Route::post('push/register', [PushController::class, 'register'])
            ->middleware('throttle:20,1')
            ->name('push.register');
        // Served from the site root because a service worker can only control
        // pages at or below its own path.
        Route::get('firebase-messaging-sw.js', [PushController::class, 'serviceWorker'])
            ->name('push.sw');
    }

});
