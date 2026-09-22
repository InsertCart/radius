<?php

namespace App\Cms\Api;

use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Issues, finds, rotates and revokes the tokens a signed-in customer's app
 * carries.
 *
 * Two tokens come out of a sign-in. The access token is short-lived and goes
 * on every request; the refresh token is long-lived, is sent only to
 * /auth/refresh, and is replaced each time it is used. That rotation is the
 * useful part: a refresh token copied off a device stops working the moment
 * the real device refreshes, and the mismatch is visible afterwards.
 */
class TokenIssuer
{
    public function __construct(private ApiManager $api) {}

    /**
     * @return array{token: string, refresh: string, model: ApiToken}
     */
    public function issue(ApiClient $client, User $user, ?string $deviceName = null, ?string $ip = null): array
    {
        $token = $this->newToken();
        $refresh = $this->newToken();

        $model = ApiToken::create([
            'api_client_id' => $client->id,
            'user_id' => $user->id,
            'device_name' => $deviceName ? Str::limit(trim($deviceName), 120, '') : null,
            'token_hash' => ApiToken::digest($token),
            'refresh_hash' => ApiToken::digest($refresh),
            'expires_at' => now()->addDays($this->api->tokenLifetimeDays()),
            'refresh_expires_at' => now()->addDays($this->api->refreshLifetimeDays()),
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ]);

        return ['token' => $token, 'refresh' => $refresh, 'model' => $model];
    }

    /**
     * The token row behind a presented access token, or null.
     *
     * Only usable tokens come back: a revoked or expired row is the same as
     * no row at all to everything downstream.
     */
    public function find(string $plain): ?ApiToken
    {
        if (trim($plain) === '') {
            return null;
        }

        $token = ApiToken::with(['user', 'client'])
            ->where('token_hash', ApiToken::digest($plain))
            ->first();

        return $token && $token->isUsable() ? $token : null;
    }

    /**
     * Swap a refresh token for a fresh pair.
     *
     * The same row is reused rather than a new one created, so "devices
     * signed in to this account" stays a list of devices rather than a list
     * of refreshes.
     *
     * @return array{token: string, refresh: string, model: ApiToken}|null
     */
    public function refresh(ApiClient $client, string $presented, ?string $ip = null): ?array
    {
        $row = ApiToken::with(['user', 'client'])
            ->where('refresh_hash', ApiToken::digest($presented))
            ->first();

        if (! $row || ! $row->refreshIsUsable()) {
            return null;
        }

        // The refresh has to come back through the app it was issued to.
        if ($row->api_client_id !== $client->id) {
            return null;
        }

        if (! $row->user || ! $row->user->isActive()) {
            $row->revoke();

            return null;
        }

        $token = $this->newToken();
        $refresh = $this->newToken();

        $row->forceFill([
            'token_hash' => ApiToken::digest($token),
            'refresh_hash' => ApiToken::digest($refresh),
            'expires_at' => now()->addDays($this->api->tokenLifetimeDays()),
            'refresh_expires_at' => now()->addDays($this->api->refreshLifetimeDays()),
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ])->save();

        return ['token' => $token, 'refresh' => $refresh, 'model' => $row];
    }

    /** Sign every device out of an account. Used when a password changes. */
    public function revokeAllFor(User $user, ?int $except = null): int
    {
        $query = ApiToken::where('user_id', $user->id)->whereNull('revoked_at');

        if ($except) {
            $query->where('id', '!=', $except);
        }

        return $query->update(['revoked_at' => now(), 'refresh_hash' => null]);
    }

    /**
     * Delete rows nothing can use any more.
     *
     * Revoked and expired tokens are kept for a while on purpose - they are
     * what lets an admin see that a device was signed out - but not forever.
     */
    public function prune(int $keepDays = 30): int
    {
        return ApiToken::where(function ($query) {
            $query->whereNotNull('revoked_at')->orWhere('expires_at', '<', now());
        })
            ->where('updated_at', '<', now()->subDays($keepDays))
            ->delete();
    }

    private function newToken(): string
    {
        return Str::random(64);
    }
}
