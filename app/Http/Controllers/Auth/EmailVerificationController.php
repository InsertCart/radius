<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Email verification for customer accounts, used when the site owner turns on
 * "Require email verification". The link in the email is signed, and
 * EmailVerificationRequest also checks it belongs to the signed-in account, so
 * a forwarded link cannot verify someone else's address.
 */
class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.dashboard');
        }

        return view(theme_view('auth.verify-email', 'auth.verify-email'), [
            'user' => $request->user(),
        ]);
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->intended(route('account.dashboard'))
            ->with('status', 'Thanks - your email address is verified.');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.dashboard');
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'We could not send the email just now. Please try again later.');
        }

        return back()->with('status', 'A new verification link is on its way.');
    }
}
