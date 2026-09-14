<?php

use App\Cms\Support\FirstRun;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

// Before anything else: a freshly extracted copy has no .env, and without one
// Laravel cannot build an encrypter, so it dies while booting and the setup
// wizard is never reached. This writes a starter .env with a key unique to this
// installation. It does nothing at all once .env exists.
FirstRun::ensureEnvironmentFile(dirname(__DIR__));

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

        // Sensitive parameters are stripped from exception reports and the
        // request log so credentials never reach a log file.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password', 'password', 'password_confirmation',
            'two_factor_code', 'recovery_code', 'card_number', 'cvv',
        ]);
    })->create();
