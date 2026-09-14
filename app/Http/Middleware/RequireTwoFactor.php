<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a session at the two-factor challenge until the second factor is
 * satisfied, and pushes admins into enrolment when the site requires it.
 *
 * Authentication happens in two steps: the password check logs the user in but
 * marks the session as awaiting 2FA. Every request then passes through here
 * until the challenge is answered, so a stolen session cookie captured between
 * the two steps is worthless.
 */
class RequireTwoFactor
{
    /** Routes the user must still be able to reach mid-challenge. */
    private const ALLOWED_ROUTES = [
        'two-factor.challenge',
        'two-factor.verify',
        'two-factor.recovery',
        'two-factor.setup',
        'two-factor.enable',
        'two-factor.confirm',
        'admin.logout',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $this->isAllowed($request)) {
            return $next($request);
        }

        // Step two of login is still outstanding.
        if ($user->hasTwoFactorEnabled() && ! session('auth.two_factor_confirmed')) {
            return $this->challenge($request);
        }

        // The site owner made 2FA mandatory for staff and this account has
        // not set it up yet.
        if ($user->isStaff() && setting('admin_2fa_required', false) && ! $user->hasTwoFactorEnabled()) {
            if ($request->expectsJson()) {
                abort(403, 'Two-factor authentication is required for admin accounts.');
            }

            return redirect()->route('two-factor.setup')
                ->with('warning', 'Two-factor authentication is required for admin accounts. Please set it up to continue.');
        }

        return $next($request);
    }

    private function isAllowed(Request $request): bool
    {
        $name = $request->route()?->getName();

        return $name !== null && in_array($name, self::ALLOWED_ROUTES, true);
    }

    private function challenge(Request $request): Response
    {
        if ($request->expectsJson()) {
            abort(423, 'Two-factor authentication is required.');
        }

        return redirect()->route('two-factor.challenge');
    }
}
