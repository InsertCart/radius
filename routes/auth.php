<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Front\AccountController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
| Shared by the storefront and the admin panel: one account table, one login
| flow, one two-factor challenge. The admin panel adds a role check on top.
*/

Route::middleware('installed')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'attempt'])
            ->middleware('throttle:10,1')
            ->name('login.attempt');

        Route::get('register', [RegisterController::class, 'show'])->name('register');
        Route::post('register', [RegisterController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('register.store');

        Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('forgot-password', [PasswordResetController::class, 'email'])
            ->middleware('throttle:5,1')
            ->name('password.email');
        Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
        Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.update');
    });

    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    /*
    | Two-factor. The challenge routes sit behind 'auth' but deliberately not
    | behind '2fa': the user is signed in but not yet fully authenticated, and
    | must be able to reach the challenge itself.
    */
    Route::middleware('auth')->group(function () {
        Route::get('two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
        Route::post('two-factor/challenge', [TwoFactorController::class, 'verify'])
            ->middleware('throttle:10,1')
            ->name('two-factor.verify');
        Route::post('two-factor/recovery', [TwoFactorController::class, 'recovery'])
            ->middleware('throttle:5,1')
            ->name('two-factor.recovery');

        Route::get('two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
        Route::post('two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
        Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::delete('two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
        Route::post('two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])
            ->name('two-factor.recovery-codes');
    });

    // Customer account area.
    Route::middleware(['auth', '2fa'])->prefix('account')->name('account.')->group(function () {
        Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
        Route::get('profile', [AccountController::class, 'profile'])->name('profile');
        Route::patch('profile', [AccountController::class, 'updateProfile'])->name('profile.update');
        Route::patch('password', [AccountController::class, 'updatePassword'])->name('password.update');

        Route::middleware('module:shop')->group(function () {
            Route::get('orders', [AccountController::class, 'orders'])->name('orders');
            Route::get('orders/{order}', [AccountController::class, 'showOrder'])->name('orders.show');
            // Throttled: a purchased file is often large, and a paid account
            // should not be able to turn the download route into a way of
            // running up the site's bandwidth bill.
            Route::get('orders/{order}/download/{item}', [AccountController::class, 'download'])
                ->middleware('throttle:20,1')
                ->name('orders.download');
        });
    });
});
