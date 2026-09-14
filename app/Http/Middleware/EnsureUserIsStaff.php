<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the admin panel to staff accounts.
 *
 * Takes an optional role list, so a route can be narrowed further:
 * ->middleware('staff:admin') keeps editors out of settings screens.
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        if (! $user->isStaff() || ! $user->isActive()) {
            abort(403, 'You do not have access to the admin panel.');
        }

        if ($roles !== [] && ! $user->hasRole(...$roles)) {
            abort(403, 'Your role does not allow this action.');
        }

        return $next($request);
    }
}
