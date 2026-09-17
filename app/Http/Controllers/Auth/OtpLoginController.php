<?php

namespace App\Http\Controllers\Auth;

use App\Cms\Sms\SmsManager;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sign in with a code texted to the phone number on the account
 * (Settings → SMS → Allow OTP login).
 *
 * Customer accounts only. Staff reach the admin panel, where a password - and
 * possibly a second factor - should not be replaceable by whoever holds the
 * phone. An account with 2FA still has to pass the challenge afterwards.
 *
 * The code always goes to the number stored on the account, never to the
 * number typed in, so matching the typed number loosely cannot deliver a code
 * to the wrong phone.
 */
class OtpLoginController extends Controller
{
    private const SESSION_KEY = 'otp_login';

    public function __construct(private SmsManager $sms) {}

    public function show(Request $request): View
    {
        $this->ensureEnabled();

        return view(theme_view('auth.otp-login', 'auth.otp-login'), [
            'pending' => $request->session()->get(self::SESSION_KEY),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);

        $request->session()->forget(self::SESSION_KEY);

        // One reply whether or not the number matched, so the form cannot be
        // used to find out which numbers have accounts.
        $generic = 'If that number belongs to an account, we have texted it a sign-in code.';

        $user = $this->findCustomer($data['phone']);

        // An unknown number still moves on to the code screen, which then
        // refuses every code - otherwise the next screen would give it away.
        if (! $user) {
            $request->session()->put(self::SESSION_KEY, ['user_id' => null, 'phone' => null, 'typed' => $data['phone']]);

            return redirect()->route('login.otp')->with('status', $generic);
        }

        $result = $this->sms->sendOtp($user->phone, 'login');

        if (! $result['sent']) {
            return redirect()->route('login.otp')->withInput()->with('error', $result['message']);
        }

        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'phone' => $user->phone,
            'typed' => $data['phone'],
        ]);

        return redirect()->route('login.otp')->with('status', $generic);
    }

    public function verify(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! $pending) {
            return redirect()->route('login.otp')->with('error', 'Please ask for a new code.');
        }

        $user = $pending['user_id'] ? User::find($pending['user_id']) : null;

        if (! $user || ! $user->isActive() || $user->isStaff() || $user->phone !== $pending['phone']
            || ! $this->sms->verifyOtp($pending['phone'], $data['code'], 'login')) {
            throw ValidationException::withMessages(['code' => 'That code is not correct or has expired.']);
        }

        $request->session()->forget(self::SESSION_KEY);

        $guestSessionId = $request->session()->getId();

        Auth::login($user);
        $request->session()->regenerate();

        LoginController::afterLogin($request, $user, $guestSessionId);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('auth.two_factor_confirmed', false);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->put('auth.two_factor_confirmed', true);

        return redirect()->intended(route('account.dashboard'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('login.otp');
    }

    public static function enabled(): bool
    {
        return (bool) setting('sms_otp_login', false) && app(SmsManager::class)->isEnabled();
    }

    private function ensureEnabled(): void
    {
        abort_unless(self::enabled(), 404);
    }

    /**
     * The one active customer whose stored number is the typed one. Numbers
     * are saved in whatever format people typed, so they are compared as
     * digits, and a national number matches its international form. More
     * than one match means the number is shared, and nobody is signed in.
     */
    private function findCustomer(string $typed): ?User
    {
        $wanted = $this->digits($typed);

        if (strlen($wanted) < 8) {
            return null;
        }

        $matches = User::query()
            ->where('role', User::ROLE_CUSTOMER)
            ->where('status', 'active')
            ->whereNotNull('phone')
            // Cheap narrowing before the exact comparison below.
            ->where('phone', 'like', '%'.substr($wanted, -2))
            ->get()
            ->filter(function (User $user) use ($wanted) {
                $stored = $this->digits($user->phone);

                if (strlen($stored) < 8) {
                    return false;
                }

                return $stored === $wanted || str_ends_with($stored, $wanted) || str_ends_with($wanted, $stored);
            });

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function digits(?string $phone): string
    {
        return ltrim(preg_replace('/\D+/', '', (string) $phone), '0');
    }
}
