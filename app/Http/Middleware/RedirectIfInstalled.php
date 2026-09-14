<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the installer once setup is complete. Without this, anyone could
 * revisit the wizard and point the site at their own database.
 */
class RedirectIfInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (file_exists(config('cms.install_lock'))) {
            return redirect('/')->with('error', 'This site is already installed.');
        }

        return $next($request);
    }
}
