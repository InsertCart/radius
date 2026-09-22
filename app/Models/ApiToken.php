<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One customer, signed in on one device, through one registered app.
 *
 * Only digests are stored. A token that has been issued cannot be read back
 * out of this table, which is the point: a database dump does not let anybody
 * sign in as a customer.
 */
class ApiToken extends Model
{
    protected $fillable = [
        'api_client_id', 'user_id', 'device_name',
        'token_hash', 'refresh_hash',
        'expires_at', 'refresh_expires_at', 'last_used_at', 'last_used_ip', 'revoked_at',
    ];

    protected $hidden = ['token_hash', 'refresh_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    // Relationships -------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    // Scopes --------------------------------------------------------------

    /** Neither revoked nor past its expiry. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    // State ---------------------------------------------------------------

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function refreshIsUsable(): bool
    {
        return ! $this->isRevoked()
            && $this->refresh_hash !== null
            && ($this->refresh_expires_at === null || $this->refresh_expires_at->isFuture());
    }

    public function revoke(): void
    {
        if ($this->isRevoked()) {
            return;
        }

        $this->forceFill([
            'revoked_at' => now(),
            // The refresh half goes too, or a revoked session could mint
            // itself a fresh access token.
            'refresh_hash' => null,
        ])->save();
    }

    public function touchUsage(?string $ip): void
    {
        // Once a minute at most: this runs on every authenticated request.
        if ($this->last_used_at && $this->last_used_at->gt(now()->subMinute())) {
            return;
        }

        $this->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ])->saveQuietly();
    }

    /** How a token is looked up: by digest, never by value. */
    public static function digest(string $token): string
    {
        return hash('sha256', $token);
    }
}
