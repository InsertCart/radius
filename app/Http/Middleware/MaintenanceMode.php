<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows a holding page to visitors while the site owner works on the site.
 *
 * Unlike `php artisan down`, this is a database switch the owner can flip from
 * the admin panel, and staff keep full access so they can preview their work.
 */
class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! setting('maintenance_mode', false)) {
            return $next($request);
        }

        if ($request->user()?->isStaff()) {
            return $next($request);
        }

        // The admin login has to stay reachable, or enabling maintenance mode
        // while signed out would lock the owner out of their own site.
        if ($request->routeIs('admin.login', 'admin.login.attempt', 'install.*')) {
            return $next($request);
        }

        return response()->view('errors.maintenance', [
            'message' => setting('maintenance_message', 'We will be back shortly.'),
        ], 503);
    }
}
