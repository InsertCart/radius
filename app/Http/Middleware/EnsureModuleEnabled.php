<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards routes that belong to an optional module.
 *
 * Route registration already skips disabled modules, so this is a second line
 * of defence for routes reached by other means (a cached route table, a direct
 * controller call in a queued job).
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        foreach ($modules as $module) {
            abort_if(modules()->disabled($module), 404);
        }

        return $next($request);
    }
}
