<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\LoginController as FrontLoginController;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin panel sign-in.
 *
 * Separate from the storefront login so the panel can live on its own
 * (optionally obscured) prefix and refuse non-staff accounts outright.
 */
class LoginController extends Controller
{
    public function show(): View
    {
        return view('admin.auth.login');
    }

    public function attempt(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'admin-login|'.Str::lower($credentials['email']).'|'.$request->ip();
        $maxAttempts = (int) config('cms.security.login_max_attempts', 5);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $user = User::where('email', $credentials['email'])->first();

        // Non-staff accounts get the same generic failure as a wrong password,
        // so this form cannot be used to discover which users are admins.
        if (! $user || ! $user->isStaff() || ! Auth::validate($credentials)) {
            RateLimiter::hit($key, config('cms.security.login_decay_minutes', 5) * 60);

            activity('admin.login_failed', 'Failed admin sign-in for '.$credentials['email']);

            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => 'This account has been suspended.']);
        }

        RateLimiter::clear($key);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        FrontLoginController::afterLogin($request, $user);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('auth.two_factor_confirmed', false);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->put('auth.two_factor_confirmed', true);

        activity('admin.login', 'Signed in to the admin panel.');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        activity('admin.logout', 'Signed out of the admin panel.');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
