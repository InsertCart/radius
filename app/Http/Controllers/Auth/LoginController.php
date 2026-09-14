<?php

namespace App\Http\Controllers\Auth;

use App\Cms\Shop\CartService;
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
 * Storefront sign-in.
 *
 * Authentication is two-step whenever the account has 2FA: the password check
 * establishes the session but marks it unconfirmed, and RequireTwoFactor holds
 * everything until the code is entered.
 */
class LoginController extends Controller
{
    public function show(): View
    {
        return view(theme_view('auth.login', 'auth.login'));
    }

    public function attempt(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey($request), config('cms.security.login_decay_minutes', 5) * 60);

            // One message for both cases, so the form cannot be used to work
            // out which email addresses have accounts.
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => 'This account has been suspended.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $guestSessionId = $request->session()->getId();

        Auth::login($user, $request->boolean('remember'));

        // A fresh session id after login defeats session fixation.
        $request->session()->regenerate();

        $this->afterLogin($request, $user, $guestSessionId);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('auth.two_factor_confirmed', false);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->put('auth.two_factor_confirmed', true);

        return redirect()->intended($user->isStaff() ? route('admin.dashboard') : route('account.dashboard'));
    }

    /** Shared post-login bookkeeping, also used by the admin login. */
    public static function afterLogin(Request $request, User $user, ?string $guestSessionId = null): void
    {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        // Carry a guest cart across into the account.
        if ($guestSessionId && modules()->enabled('shop')) {
            app(CartService::class)->mergeGuestCart($guestSessionId, $user->id);
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        $maxAttempts = (int) config('cms.security.login_max_attempts', 5);

        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), $maxAttempts)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    /** Keyed on email and IP together, so one attacker cannot lock out a user. */
    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());
    }
}
