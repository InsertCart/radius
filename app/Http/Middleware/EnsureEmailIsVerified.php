<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds customers at the verification notice until they confirm their email
 * address - but only while the site owner has verification switched on.
 *
 * Laravel's own 'verified' middleware is not used because it always enforces,
 * whatever the setting says. Staff are left alone: their accounts are created
 * in the admin panel or installer, never through the public sign-up form.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isStaff() || $user->hasVerifiedEmail() || ! setting('email_verification', false)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Your email address is not verified.');
        }

        return redirect()->route('verification.notice');
    }
}
