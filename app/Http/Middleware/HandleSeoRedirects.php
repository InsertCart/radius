<?php

namespace App\Http\Middleware;

use App\Models\SeoRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the admin-managed redirect map, so a buyer migrating from an old
 * site keeps the rankings attached to its URLs.
 *
 * The whole map is cached as one array: on most sites it is a handful of rows,
 * and a cache hit costs nothing compared with a query per request.
 */
class HandleSeoRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nothing to redirect to before the site is set up, and the tables this
        // reads do not exist yet. Without this the setup wizard's own first
        // request dies querying seo_redirects in a database the buyer has not
        // created - which is a confusing way to be told to create one.
        if (! cms_installed() || modules()->disabled('seo')) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        if ($path === '') {
            return $next($request);
        }

        try {
            $map = Cache::remember(
                'cms.seo.redirects',
                3600,
                fn () => SeoRedirect::active()->pluck('id', 'source')->all()
            );

            if (! isset($map[$path])) {
                return $next($request);
            }

            $redirect = SeoRedirect::find($map[$path]);

            if (! $redirect || ! $redirect->is_active) {
                return $next($request);
            }

            $redirect->recordHit();
        } catch (\Throwable $e) {
            // A redirect map is a convenience. If the database is unreachable
            // or mid-migration, serve the page rather than turning every single
            // request on the site into a 500.
            report($e);

            return $next($request);
        }

        $destination = str_starts_with($redirect->destination, 'http')
            ? $redirect->destination
            : url($redirect->destination);

        return redirect()->away($destination, $redirect->status_code);
    }
}
