<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends visitors to the setup wizard until the CMS has been installed.
 *
 * The lock file, not a database flag, is the source of truth: on a fresh copy
 * there is no database to ask.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! file_exists(config('cms.install_lock'))) {
            return redirect()->route('install.requirements');
        }

        return $next($request);
    }
}
