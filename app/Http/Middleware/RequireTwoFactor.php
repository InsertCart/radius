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
    /**
     * Routes reachable while a challenge is outstanding.
     *
     * Deliberately only the challenge itself and the way out. Enrolment is NOT
     * here: a session that has proved the password but not the second factor
     * must not be able to enrol a new authenticator, because staging a fresh
     * secret overwrites the one it has failed to satisfy - which would turn
     * "I know the password" into a complete bypass of the second factor.
     */
    private const CHALLENGE_ROUTES = [
        'two-factor.challenge',
        'two-factor.verify',
        'two-factor.recovery',
        'admin.logout',
        'logout',
    ];

    /**
     * Additionally reachable by someone who has no second factor yet and is
     * being pushed into setting one up. Nothing here can weaken an existing
     * second factor, because reaching it requires not having one.
     */
    private const ENROLMENT_ROUTES = [
        'two-factor.setup',
        'two-factor.enable',
        'two-factor.confirm',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $route = $request->route()?->getName();

        // The challenge itself, and signing out, are always reachable.
        if ($route !== null && in_array($route, self::CHALLENGE_ROUTES, true)) {
            return $next($request);
        }

        // Step two of login is still outstanding. Note this is checked BEFORE
        // the enrolment routes are allowed through: someone who already has a
        // second factor has nothing to enrol, they have a challenge to answer.
        if ($user->hasTwoFactorEnabled() && ! session('auth.two_factor_confirmed')) {
            return $this->challenge($request);
        }

        if ($route !== null && in_array($route, self::ENROLMENT_ROUTES, true)) {
            return $next($request);
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

    private function challenge(Request $request): Response
    {
        if ($request->expectsJson()) {
            abort(423, 'Two-factor authentication is required.');
        }

        return redirect()->route('two-factor.challenge');
    }
}
