<?php

namespace App\Http\Controllers\Api\V1;

use App\Cms\Api\ApiManager;
use App\Cms\Api\Resource;
use App\Cms\Api\TokenIssuer;
use App\Cms\Shop\CartService;
use App\Cms\Support\TwoFactorService;
use App\Http\Controllers\Api\ApiController;
use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Signing a customer in from an app, and everything that follows from it.
 *
 * The rules the website's own login obeys are obeyed here too, because they
 * are the same accounts: the same lockout after repeated failures, the same
 * single answer for a wrong password and an unknown address, the same
 * two-factor challenge, the same suspended-account check.
 *
 * What is different is the session. There is none. A successful sign-in
 * returns a token pair; the access token goes on every later request, the
 * refresh token buys a new pair and is replaced each time it is used.
 */
class AuthController extends ApiController
{
    public function __construct(
        private TokenIssuer $tokens,
        private TwoFactorService $twoFactor,
        private ApiManager $api,
    ) {}

    // Signing in -----------------------------------------------------------

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        if ($seconds = $this->lockedOutFor($request)) {
            return $this->fail(
                "Too many sign-in attempts. Try again in {$seconds} seconds.",
                429,
                'too_many_attempts'
            );
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Auth::validate(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            RateLimiter::hit($this->throttleKey($request), (int) config('cms.security.login_decay_minutes', 5) * 60);

            // One answer for both, so the API cannot be used to find out which
            // email addresses have accounts here.
            return $this->fail('Those credentials do not match our records.', 401, 'invalid_credentials');
        }

        RateLimiter::clear($this->throttleKey($request));

        if ($refusal = $this->refuseAccount($user)) {
            return $refusal;
        }

        // 2FA is not optional here just because the caller is an app: the
        // password alone must not be enough anywhere it is not enough on the
        // website.
        if ($user->hasTwoFactorEnabled()) {
            return $this->data([
                'two_factor_required' => true,
                'challenge' => $this->beginTwoFactor($user, $this->client($request)),
                'expires_in' => 300,
            ]);
        }

        return $this->grant($request, $user);
    }

    /**
     * The second half of a sign-in that needed a code.
     *
     * The challenge is a one-time handle held in the cache for five minutes.
     * It is consumed whichever way this goes, so a wrong code means starting
     * again with the password rather than guessing codes against it.
     */
    public function twoFactor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge' => ['required', 'string', 'max:128'],
            'code' => ['nullable', 'string', 'max:32'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        if (blank($validated['code'] ?? null) && blank($validated['recovery_code'] ?? null)) {
            return $this->fail('Send the code from your authenticator app, or one of your recovery codes.', 422, 'code_required');
        }

        $pending = Cache::pull($this->challengeKey($validated['challenge']));

        if (! is_array($pending) || $pending['client'] !== $this->client($request)->id) {
            return $this->fail('That sign-in has expired. Start again.', 401, 'challenge_expired');
        }

        $user = User::find($pending['user']);

        if (! $user) {
            return $this->fail('That sign-in has expired. Start again.', 401, 'challenge_expired');
        }

        if ($refusal = $this->refuseAccount($user)) {
            return $refusal;
        }

        $verified = filled($validated['recovery_code'] ?? null)
            ? $this->twoFactor->useRecoveryCode($user, $validated['recovery_code'])
            : $this->twoFactor->verify($user, (string) $validated['code']);

        if (! $verified) {
            RateLimiter::hit($this->throttleKey($request, $user->email), 300);

            return $this->fail('That code is not correct.', 401, 'invalid_code');
        }

        return $this->grant($request, $user);
    }

    /** Swap a refresh token for a new pair. The old pair stops working here. */
    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string', 'max:128'],
        ]);

        $issued = $this->tokens->refresh(
            $this->client($request),
            $validated['refresh_token'],
            $request->ip()
        );

        if (! $issued) {
            return $this->fail('That session has expired. Sign in again.', 401, 'invalid_refresh_token');
        }

        $user = $issued['model']->user;

        if ($refusal = $this->refuseAccount($user)) {
            $issued['model']->revoke();

            return $refusal;
        }

        return $this->tokenPayload($issued, $user);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->token($request)?->revoke();

        return $this->message('Signed out.');
    }

    // The signed-in customer ------------------------------------------------

    public function me(Request $request): JsonResponse
    {
        return $this->data(Resource::user($request->user()));
    }

    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        // Deliberately narrow. Email changes go through the website, where
        // there is a verification flow to hang them on, and role and status
        // are not the customer's to set from anywhere.
        $user->fill($validated)->save();

        return $this->data(Resource::user($user->fresh()));
    }

    /**
     * Change a password, and sign every other device out.
     *
     * If a password is being changed because it leaked, leaving the other
     * sessions alive would make the change pointless.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->fail('Your current password is not correct.', 422, 'wrong_password');
        }

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

        $signedOut = $this->tokens->revokeAllFor($user, except: $this->token($request)?->id);

        return $this->message('Password changed.', [
            'data' => ['other_devices_signed_out' => $signedOut],
        ]);
    }

    /** Where this account is signed in, so a customer can cut off a lost phone. */
    public function devices(Request $request): JsonResponse
    {
        $current = $this->token($request)?->id;

        $devices = ApiToken::with('client')
            ->where('user_id', $request->user()->id)
            ->active()
            ->latest('last_used_at')
            ->get()
            ->map(fn (ApiToken $token) => Resource::device($token, $current))
            ->all();

        return $this->data($devices);
    }

    public function revokeDevice(Request $request, int $device): JsonResponse
    {
        // Scoped to this customer's own tokens, so an id from somewhere else
        // finds nothing rather than signing a stranger out.
        $token = ApiToken::where('user_id', $request->user()->id)->find($device);

        if (! $token) {
            return $this->fail('No such device.', 404, 'not_found');
        }

        $token->revoke();

        return $this->message('That device has been signed out.');
    }

    // Registration ----------------------------------------------------------

    public function register(Request $request): JsonResponse
    {
        if (! setting('registration_enabled', true)) {
            return $this->fail('This site is not accepting new accounts.', 403, 'registration_closed');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            // An account made through the API is a customer. There is no
            // argument the app can send to change that.
            'role' => User::ROLE_CUSTOMER,
            'status' => 'active',
            'email_verified_at' => setting('email_verification', false) ? null : now(),
        ]);

        // The account exists; a mail server that is down must not turn a
        // successful sign-up into an error. They can ask for the link again.
        try {
            event(new Registered($user));
        } catch (\Throwable $e) {
            report($e);
        }

        activity('api.registered', "{$user->email} signed up from an app.", $user);

        return $this->grant($request, $user, status: 201);
    }

    /**
     * Send a password reset link.
     *
     * The link goes to the website, not into the app: a reset is exactly the
     * moment to make somebody prove they can read the mailbox, and the web
     * flow already does that with a signed, expiring URL.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            Password::sendResetLink($validated);
        } catch (\Throwable $e) {
            report($e);
        }

        // Always the same answer, whether or not that address has an account.
        return $this->message('If that address has an account, a reset link is on its way.');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->message('That address is already verified.');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);

            return $this->fail('We could not send the email just now. Please try again shortly.', 503, 'mail_failed');
        }

        return $this->message('Verification email sent.');
    }

    // Internals -------------------------------------------------------------

    /** Issue a token pair and hand back the standard sign-in payload. */
    private function grant(Request $request, User $user, int $status = 200): JsonResponse
    {
        $issued = $this->tokens->issue(
            $this->client($request),
            $user,
            $request->input('device_name') ?: Str::limit((string) $request->userAgent(), 120, ''),
            $request->ip()
        );

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        $this->mergeGuestCart($request, $user);

        return $this->tokenPayload($issued, $user, $status);
    }

    /**
     * @param  array{token: string, refresh: string, model: ApiToken}  $issued
     */
    private function tokenPayload(array $issued, User $user, int $status = 200): JsonResponse
    {
        return $this->data([
            'access_token' => $issued['token'],
            'refresh_token' => $issued['refresh'],
            'token_type' => 'Bearer',
            'expires_at' => $issued['model']->expires_at?->toIso8601String(),
            'refresh_expires_at' => $issued['model']->refresh_expires_at?->toIso8601String(),
            'user' => Resource::user($user),
        ], status: $status);
    }

    /**
     * Carry a cart built before signing in into the account, the same way the
     * website does when a visitor logs in mid-shop.
     */
    private function mergeGuestCart(Request $request, User $user): void
    {
        $cartToken = trim((string) $request->header('X-Cart-Token', ''));

        if ($cartToken === '' || ! $this->api->feature('shop')) {
            return;
        }

        try {
            app(CartService::class)->mergeGuestCart($cartToken, $user->id);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Reasons an otherwise valid password still does not get a token. */
    private function refuseAccount(?User $user): ?JsonResponse
    {
        if (! $user) {
            return $this->fail('That sign-in has expired. Start again.', 401, 'challenge_expired');
        }

        if (! $user->isActive()) {
            return $this->fail('This account has been suspended.', 403, 'account_inactive');
        }

        if ($user->isStaff() && ! $this->api->allowsStaff()) {
            return $this->fail(
                'Staff accounts cannot sign in through the API. Use the admin panel in a browser.',
                403,
                'staff_blocked'
            );
        }

        return null;
    }

    private function beginTwoFactor(User $user, ApiClient $client): string
    {
        $challenge = Str::random(64);

        Cache::put($this->challengeKey($challenge), [
            'user' => $user->id,
            'client' => $client->id,
        ], now()->addMinutes(5));

        return $challenge;
    }

    private function challengeKey(string $challenge): string
    {
        return 'api.2fa:'.hash('sha256', $challenge);
    }

    /** Seconds left on a lockout, or 0. Keyed on email and IP, as on the website. */
    private function lockedOutFor(Request $request): int
    {
        $key = $this->throttleKey($request);
        $max = (int) config('cms.security.login_max_attempts', 5);

        return RateLimiter::tooManyAttempts($key, $max)
            ? RateLimiter::availableIn($key)
            : 0;
    }

    private function throttleKey(Request $request, ?string $email = null): string
    {
        return 'api-login:'.Str::transliterate(
            Str::lower($email ?? (string) $request->input('email')).'|'.$request->ip()
        );
    }
}
