<?php

use App\Cms\Support\FirstRun;
use App\Cms\Support\RootRewrite;
use App\Http\Middleware\Api\AuthenticateApiToken;
use App\Http\Middleware\Api\EnsureApiFeature;
use App\Http\Middleware\Api\ForceJsonResponse;
use App\Http\Middleware\Api\VerifyApiClient;
use App\Http\Middleware\CachePages;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\HandleSeoRedirects;
use App\Http\Middleware\InjectBuilderStyles;
use App\Http\Middleware\InjectRecaptcha;
use App\Http\Middleware\MaintenanceMode;
use App\Http\Middleware\RedirectIfInstalled;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\VerifyRecaptcha;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Routing\Middleware\SubstituteBindings;
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

            // The mobile API. Registered only while its module is on, and on
            // a stack of its own: no session, no cookies, no CSRF token, so
            // nothing here can be reached by a browser that merely happens to
            // be signed in to the site. A request is authenticated by headers
            // an app sets deliberately, or it is refused - see
            // App\Http\Middleware\Api\VerifyApiClient.
            if (modules()->enabled('api')) {
                Route::middleware([
                    ForceJsonResponse::class,
                    'installed',
                    VerifyApiClient::class,
                    'throttle:api',
                    SubstituteBindings::class,
                ])
                    ->prefix(config('api.prefix', 'api'))
                    ->group(base_path('routes/api.php'));
            }

            // Last: its catch-all must not shadow any route above it.
            Route::middleware('web')->group(base_path('routes/pages.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'installed' => EnsureInstalled::class,
            'not-installed' => RedirectIfInstalled::class,
            'module' => EnsureModuleEnabled::class,
            'staff' => EnsureUserIsStaff::class,
            '2fa' => RequireTwoFactor::class,
            'verified-email' => EnsureEmailIsVerified::class,
            'recaptcha' => VerifyRecaptcha::class,

            // Mobile API.
            'api.json' => ForceJsonResponse::class,
            'api.client' => VerifyApiClient::class,
            'api.auth' => AuthenticateApiToken::class,
            'api.feature' => EnsureApiFeature::class,
        ]);

        // Order matters: redirects are resolved before anything renders, and
        // the maintenance check runs before a controller does any work.
        $middleware->web(append: [
            HandleSeoRedirects::class,
            MaintenanceMode::class,
            // Outside InjectRecaptcha, so a cached page keeps its script.
            CachePages::class,
            // Inside CachePages, so a cached page keeps its builder styles.
            InjectBuilderStyles::class,
            InjectRecaptcha::class,
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

        // A path under /api that matches no route never enters the API's own
        // middleware group - ForceJsonResponse included - so without this,
        // a typo'd endpoint or an unmatched method got Laravel's ordinary
        // HTML error page instead of JSON. An API should never hand a client
        // a page to parse, matched route or not.
        $exceptions->shouldRenderJsonWhen(function ($request, Throwable $e) {
            return $request->is(trim((string) config('api.prefix', 'api'), '/').'/*')
                || $request->expectsJson();
        });

        // The rate limiter throws before any API controller runs, so its
        // response would otherwise be whatever Laravel's default exception
        // rendering produces for it: no 'error' code to branch on - breaking
        // the one promise every other failure in this API keeps - and, with
        // APP_DEBUG on, a stack trace in the body. The Retry-After header
        // Laravel already computed is kept as it was.
        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            if (! $request->is(trim((string) config('api.prefix', 'api'), '/').'/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Too many requests. Please slow down and try again shortly.',
                'error' => 'rate_limited',
            ], 429, $e->getHeaders());
        });
    })->create();
