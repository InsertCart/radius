<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpLoginController;
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

        // Sign in with a texted code. Answers 404 unless OTP login is on.
        Route::get('login/code', [OtpLoginController::class, 'show'])->name('login.otp');
        Route::post('login/code/send', [OtpLoginController::class, 'send'])
            ->middleware('throttle:5,1')
            ->name('login.otp.send');
        Route::post('login/code/verify', [OtpLoginController::class, 'verify'])
            ->middleware('throttle:10,1')
            ->name('login.otp.verify');
        Route::post('login/code/cancel', [OtpLoginController::class, 'cancel'])->name('login.otp.cancel');

        Route::get('register', [RegisterController::class, 'show'])->name('register');
        Route::post('register', [RegisterController::class, 'store'])
            ->middleware(['throttle:5,1', 'recaptcha'])
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

    /*
    | Email verification. Only enforced while the "Require email verification"
    | setting is on - see EnsureEmailIsVerified. The link is signed, so it
    | cannot be forged or altered.
    */
    Route::middleware(['auth', '2fa'])->group(function () {
        Route::get('email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
        Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
    });

    // Customer account area.
    Route::middleware(['auth', '2fa', 'verified-email'])->prefix('account')->name('account.')->group(function () {
        Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
        Route::get('profile', [AccountController::class, 'profile'])->name('profile');
        Route::patch('profile', [AccountController::class, 'updateProfile'])->name('profile.update');
        Route::patch('password', [AccountController::class, 'updatePassword'])->name('password.update');

        Route::middleware('module:shop')->group(function () {
            // The address book, so checkout never asks for the same address
            // a second time.
            Route::get('addresses', [AccountController::class, 'addresses'])->name('addresses');
            Route::post('addresses', [AccountController::class, 'storeAddress'])->name('addresses.store');
            Route::patch('addresses/{address}', [AccountController::class, 'updateAddress'])->name('addresses.update');
            Route::patch('addresses/{address}/default', [AccountController::class, 'makeDefaultAddress'])->name('addresses.default');
            Route::delete('addresses/{address}', [AccountController::class, 'destroyAddress'])->name('addresses.destroy');

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
