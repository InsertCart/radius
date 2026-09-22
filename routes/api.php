<?php

use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API, version 1
|--------------------------------------------------------------------------
| This file is only loaded while the 'api' module is on, so a site without an
| app registers none of it.
|
| The stack applied to every route in here is set in bootstrap/app.php:
|
|   api.json    every answer is JSON, whatever the caller's Accept header says
|   installed   the site has actually been set up
|   api.client  a registered app, proving it holds that app's secret
|   throttle    per app, per IP, at the rate the owner chose
|
| Note what is NOT in that list: no session, and therefore no session cookie
| and no CSRF token. Nothing here can be reached by a browser that merely
| happens to be signed in to the website, because the only thing that
| authenticates a caller is a header their app sets on purpose.
|
| Groups of endpoints that the owner can switch off carry api.feature as
| well. Unlike the module route files, these routes are always registered and
| the middleware decides: a feature toggle is a setting, changed far more
| often than a module is, and a stale cached route table must not be able to
| leave one answering after it was switched off - nor 404 after it was
| switched on.
|
| api.auth resolves the customer behind a bearer token. 'api.auth:optional'
| means a signed-out app may call it too - browsing products, filling a guest
| basket - and the controller decides what a guest may see.
*/

Route::prefix(config('api.version', 'v1'))->name('api.v1.')->group(function () {

    /*
    | Always available while the API is on. An app reads /site first to find
    | out which of the groups below it is allowed to call.
    */
    Route::get('site', [SiteController::class, 'show'])->name('site');
    Route::get('menus', [SiteController::class, 'menus'])->name('menus');

    // Sign in & the customer's own account -------------------------------
    Route::middleware('api.feature:auth')->group(function () {
        Route::middleware('throttle:api-auth')->group(function () {
            Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
            Route::post('auth/two-factor', [AuthController::class, 'twoFactor'])->name('auth.two-factor');
            Route::post('auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        });

        Route::middleware('api.auth')->group(function () {
            Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
            Route::patch('auth/me', [AuthController::class, 'updateMe'])->name('auth.me.update');
            Route::put('auth/password', [AuthController::class, 'updatePassword'])->name('auth.password');
            Route::get('auth/devices', [AuthController::class, 'devices'])->name('auth.devices');
            Route::delete('auth/devices/{device}', [AuthController::class, 'revokeDevice'])
                ->whereNumber('device')
                ->name('auth.devices.revoke');
        });
    });

    // Registration --------------------------------------------------------
    Route::middleware('api.feature:registration')->group(function () {
        Route::middleware('throttle:api-auth')->group(function () {
            Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
            Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot');
        });

        Route::post('auth/resend-verification', [AuthController::class, 'resendVerification'])
            ->middleware(['api.auth', 'throttle:6,1'])
            ->name('auth.resend');
    });

    // Blog ----------------------------------------------------------------
    Route::middleware('api.feature:posts')->group(function () {
        Route::get('posts', [PostController::class, 'index'])->name('posts.index');
        Route::get('post-categories', [PostController::class, 'categories'])->name('posts.categories');
        Route::get('post-tags', [PostController::class, 'tags'])->name('posts.tags');
        Route::get('posts/{slug}', [PostController::class, 'show'])->name('posts.show');

        Route::middleware('api.feature:comments')->group(function () {
            Route::get('posts/{slug}/comments', [PostController::class, 'comments'])->name('posts.comments');
            Route::post('posts/{slug}/comments', [PostController::class, 'storeComment'])
                ->middleware(['api.auth:optional', 'throttle:10,1'])
                ->name('posts.comments.store');
        });
    });

    // Pages ---------------------------------------------------------------
    Route::middleware('api.feature:pages')->group(function () {
        Route::get('pages', [PageController::class, 'index'])->name('pages.index');
        Route::get('pages/{slug}', [PageController::class, 'show'])->name('pages.show');
    });

    // Search --------------------------------------------------------------
    Route::get('search', [SearchController::class, 'index'])
        ->middleware('api.feature:search')
        ->name('search');

    // Shop ----------------------------------------------------------------
    Route::middleware('api.feature:shop')->group(function () {

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('product-categories', [ProductController::class, 'categories'])->name('products.categories');
        Route::get('products/{slug}', [ProductController::class, 'show'])->name('products.show');

        // Reviews are their own toggle: reading the catalogue and writing to
        // it are different decisions.
        Route::middleware('api.feature:reviews')->group(function () {
            Route::get('products/{slug}/reviews', [ProductController::class, 'reviews'])->name('products.reviews');
            Route::post('products/{slug}/reviews', [ProductController::class, 'storeReview'])
                ->middleware(['api.auth', 'throttle:5,1'])
                ->name('products.reviews.store');
        });

        // The basket. Open to guests when the owner allows guest carts; the
        // controller refuses otherwise.
        Route::middleware('api.auth:optional')->group(function () {
            Route::get('cart', [CartController::class, 'show'])->name('cart.show');
            Route::post('cart/items', [CartController::class, 'store'])->name('cart.store');
            Route::patch('cart/items/{item}', [CartController::class, 'update'])
                ->whereNumber('item')
                ->name('cart.update');
            Route::delete('cart/items/{item}', [CartController::class, 'destroy'])
                ->whereNumber('item')
                ->name('cart.destroy');
            Route::post('cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon');
            Route::delete('cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

            Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout.show');
            Route::post('checkout', [CheckoutController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('checkout.store');

            // A guest may read the order they just placed, with the handle
            // checkout gave them. See CheckoutController::findOwnOrder().
            Route::get('orders/{number}', [CheckoutController::class, 'order'])->name('orders.show');
        });

        Route::middleware('api.auth')->group(function () {
            Route::get('orders', [CheckoutController::class, 'orders'])->name('orders.index');

            Route::get('addresses', [AddressController::class, 'index'])->name('addresses.index');
            Route::post('addresses', [AddressController::class, 'store'])->name('addresses.store');
            Route::patch('addresses/{address}', [AddressController::class, 'update'])
                ->whereNumber('address')
                ->name('addresses.update');
            Route::delete('addresses/{address}', [AddressController::class, 'destroy'])
                ->whereNumber('address')
                ->name('addresses.destroy');
        });
    });

    // Contact & newsletter ------------------------------------------------
    Route::post('contact', [MessageController::class, 'contact'])
        ->middleware(['api.feature:contact', 'throttle:5,1'])
        ->name('contact');

    Route::post('newsletter', [MessageController::class, 'subscribe'])
        ->middleware(['api.feature:newsletter', 'throttle:5,1'])
        ->name('newsletter');

    // Push notifications --------------------------------------------------
    Route::middleware(['api.feature:push', 'api.auth:optional'])->group(function () {
        Route::post('devices', [MessageController::class, 'registerDevice'])->name('devices.register');
        Route::delete('devices', [MessageController::class, 'forgetDevice'])->name('devices.forget');
    });
});
