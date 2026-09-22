<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes every answer on an API route JSON, whatever the caller asked for.
 *
 * Without this, a validation failure or a 404 is decided by the Accept header
 * the app happened to send, and an app that forgets one gets an HTML error
 * page - or worse, a redirect to the login screen - where it expected a
 * machine-readable answer.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
