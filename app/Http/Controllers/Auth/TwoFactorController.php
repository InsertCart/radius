<?php

namespace App\Http\Controllers\Auth;

use App\Cms\Support\TwoFactorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * TOTP two-factor: enrolment, the login challenge, and recovery codes.
 */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    // Login challenge -----------------------------------------------------

    public function challenge(Request $request): RedirectResponse|View
    {
        if (! $request->user()->hasTwoFactorEnabled() || $request->session()->get('auth.two_factor_confirmed')) {
            return redirect()->intended('/');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        if (! $this->twoFactor->verify($request->user(), $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => 'That code is not valid. Check your authenticator app and try again.',
            ]);
        }

        return $this->completeChallenge($request);
    }

    public function recovery(Request $request): RedirectResponse
    {
        $request->validate(['recovery_code' => ['required', 'string']]);

        if (! $this->twoFactor->useRecoveryCode($request->user(), $request->input('recovery_code'))) {
            throw ValidationException::withMessages([
                'recovery_code' => 'That recovery code is not valid, or has already been used.',
            ]);
        }

        $remaining = count($request->user()->two_factor_recovery_codes ?? []);

        return $this->completeChallenge($request)->with(
            'warning',
            $remaining > 0
                ? "You have {$remaining} recovery codes left. Generate a new set from your profile."
                : 'You have used your last recovery code. Generate a new set now.'
        );
    }

    private function completeChallenge(Request $request): RedirectResponse
    {
        $request->session()->put('auth.two_factor_confirmed', true);

        // A new session id on privilege escalation, same reasoning as login.
        $request->session()->regenerate();

        activity('two_factor.passed', 'Passed the two-factor challenge.');

        $user = $request->user();

        return redirect()->intended($user->isStaff() ? route('admin.dashboard') : route('account.dashboard'));
    }

    // Enrolment -----------------------------------------------------------

    public function setup(Request $request): View
    {
        $user = $request->user();

        return view('auth.two-factor-setup', [
            'user' => $user,
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => filled($user->two_factor_secret) && ! $user->hasTwoFactorEnabled(),
            'qrCode' => filled($user->two_factor_secret) ? $this->twoFactor->qrCodeSvg($user) : null,
            'secret' => filled($user->two_factor_secret) ? $this->twoFactor->formattedSecret($user) : null,
            'recoveryCodes' => $request->session()->get('two_factor.recovery_codes'),
        ]);
    }

    /** Generates a secret and shows the QR code, without enabling 2FA yet. */
    public function enable(Request $request): RedirectResponse
    {
        // Re-enrolling replaces a second factor that is already protecting the
        // account, so it is gated exactly like removing one. Nothing may
        // weaken an existing factor on the strength of the password alone.
        if ($request->user()->hasTwoFactorEnabled()) {
            $request->validate(['current_password' => ['required', 'string']]);

            if (! Hash::check($request->input('current_password'), $request->user()->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'That password is not correct.',
                ]);
            }
        }

        $this->twoFactor->beginEnrolment($request->user());

        return redirect()->route('two-factor.setup')
            ->with('status', 'Scan the QR code with your authenticator app, then enter the six-digit code to finish.');
    }

    /** Confirms the first code and switches 2FA on. */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        if (! $this->twoFactor->confirm($request->user(), $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => 'That code is not valid. Make sure your phone clock is accurate and try again.',
            ]);
        }

        $request->session()->put('auth.two_factor_confirmed', true);

        activity('two_factor.enabled', 'Enabled two-factor authentication.');

        return redirect()->route('two-factor.setup')
            // Shown once. They are not recoverable afterwards, by design.
            ->with('two_factor.recovery_codes', $request->user()->two_factor_recovery_codes)
            ->with('status', 'Two-factor authentication is on. Save these recovery codes somewhere safe.');
    }

    public function disable(Request $request): RedirectResponse
    {
        // Re-authenticate: an unattended browser must not be enough to strip
        // the second factor off an account.
        $request->validate(['current_password' => ['required', 'string']]);

        if (! Hash::check($request->input('current_password'), $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That password is not correct.',
            ]);
        }

        if (setting('admin_2fa_required', false) && $request->user()->isStaff()) {
            return back()->with('error', 'This site requires two-factor authentication for admin accounts.');
        }

        $this->twoFactor->disable($request->user());

        activity('two_factor.disabled', 'Disabled two-factor authentication.');

        return redirect()->route('two-factor.setup')->with('status', 'Two-factor authentication is off.');
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $codes = $this->twoFactor->regenerateRecoveryCodes($request->user());

        return redirect()->route('two-factor.setup')
            ->with('two_factor.recovery_codes', $codes)
            ->with('status', 'New recovery codes generated. Your old ones no longer work.');
    }
}
