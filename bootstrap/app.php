<?php

use App\Cms\Support\FirstRun;
use App\Cms\Support\RootRewrite;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

// Before anything else: a freshly extracted copy has no .env, and without one
// Laravel cannot build an encrypter, so it dies while booting and the setup
// wizard is never reached. This writes a starter .env with a key unique to this
// installation. It does nothing at all once .env exists.
FirstRun::ensureEnvironmentFile(dirname(__DIR__));

// The root .htaccess lets the site answer without /public in the address. That
// leaves SCRIPT_NAME disagreeing with the URL the visitor actually asked for,
// which Symfony reads as the application being mounted one directory deeper
// than it is - and every route misses. Corrected here, before the Request is
// built from these values.
RootRewrite::align();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Split out so each file stays readable, and so the admin and
            // installer route files can carry their own middleware stacks.
            Route::middleware('web')->group(base_path('routes/installer.php'));
            Route::middleware('web')->group(base_path('routes/auth.php'));
            Route::middleware('web')->group(base_path('routes/admin.php'));

            // Last: its catch-all must not shadow any route above it.
            Route::middleware('web')->group(base_path('routes/pages.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'installed' => \App\Http\Middleware\EnsureInstalled::class,
            'not-installed' => \App\Http\Middleware\RedirectIfInstalled::class,
            'module' => \App\Http\Middleware\EnsureModuleEnabled::class,
            'staff' => \App\Http\Middleware\EnsureUserIsStaff::class,
            '2fa' => \App\Http\Middleware\RequireTwoFactor::class,
        ]);

        // Order matters: redirects are resolved before anything renders, and
        // the maintenance check runs before a controller does any work.
        $middleware->web(append: [
            \App\Http\Middleware\HandleSeoRedirects::class,
            \App\Http\Middleware\MaintenanceMode::class,
        ]);

        // Forwarded headers are only believable when something trustworthy set
        // them. Trusting every proxy means trusting the client: X-Forwarded-For
        // becomes whatever the caller types, which silently turns Request::ip()
        // - and so every rate limit, lockout and audit entry keyed on it - into
        // an attacker-controlled value, and lets X-Forwarded-Host rewrite the
        // host in generated links such as password-reset emails.
        //
        // So: trust nothing by default, which is correct for the direct-Apache
        // and shared-hosting setups this CMS is usually installed on. Sites
        // behind a load balancer or Cloudflare set TRUSTED_PROXIES to that
        // proxy's address, a comma-separated list, or '*' if the app can only
        // ever be reached through it.
        if ($proxies = trim((string) env('TRUSTED_PROXIES', ''))) {
            $middleware->trustProxies(
                at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)),
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password', 'password', 'password_confirmation',
            'two_factor_code', 'recovery_code', 'card_number', 'cvv',
        ]);
    })->create();
