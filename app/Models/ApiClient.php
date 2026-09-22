<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A registered app: the credential a mobile build carries so the API will
 * talk to it at all.
 *
 * This is what stands between the API and the open internet. Knowing an
 * endpoint's address gets a caller a 401 and nothing else - every request has
 * to name a client and prove it holds that client's secret.
 *
 * One row per app, not per user. A customer's own sign-in is an ApiToken,
 * issued through one of these.
 */
class ApiClient extends Model
{
    protected $fillable = [
        'name', 'platform', 'client_id', 'secret', 'secret_hint',
        'enabled', 'created_by',
    ];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_used_at' => 'datetime',
            // Encrypted, not hashed: verifying a signed request means
            // recomputing its HMAC, which needs the secret itself.
            'secret' => 'encrypted',
        ];
    }

    public const PLATFORMS = [
        'mobile' => 'Mobile app (iOS & Android)',
        'ios' => 'iOS app',
        'android' => 'Android app',
        'other' => 'Something else',
    ];

    // Relationships -------------------------------------------------------

    public function tokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes --------------------------------------------------------------

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    // Credentials ---------------------------------------------------------

    /**
     * Register an app and hand back its credentials.
     *
     * The secret is returned here and nowhere else, ever again: the admin
     * screen shows it once, and after that the only way to get a working
     * secret is to generate a new one.
     *
     * @return array{0: self, 1: string} The client and its plaintext secret.
     */
    public static function issue(string $name, string $platform = 'mobile', ?int $createdBy = null): array
    {
        $secret = self::newSecret();

        $client = self::create([
            'name' => $name,
            'platform' => array_key_exists($platform, self::PLATFORMS) ? $platform : 'mobile',
            'client_id' => self::newClientId(),
            'secret' => $secret,
            'secret_hint' => substr($secret, -4),
            'enabled' => true,
            'created_by' => $createdBy,
        ]);

        return [$client, $secret];
    }

    /** Replace this app's secret. Every build still carrying the old one stops working. */
    public function regenerateSecret(): string
    {
        $secret = self::newSecret();

        $this->forceFill([
            'secret' => $secret,
            'secret_hint' => substr($secret, -4),
        ])->save();

        return $secret;
    }

    /**
     * Whether a presented secret is this client's.
     *
     * hash_equals rather than ===, so the comparison takes the same time
     * whatever the caller guessed.
     */
    public function secretMatches(string $presented): bool
    {
        $secret = (string) $this->secret;

        return $secret !== '' && hash_equals($secret, $presented);
    }

    /** The plaintext secret, for recomputing a request signature. */
    public function signingSecret(): string
    {
        return (string) $this->secret;
    }

    public function touchUsage(?string $ip): void
    {
        // Written without touching updated_at, and only once a minute, so a
        // chatty app does not turn every read into a write.
        if ($this->last_used_at && $this->last_used_at->gt(now()->subMinute())) {
            return;
        }

        $this->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ])->saveQuietly();
    }

    public function platformName(): string
    {
        return self::PLATFORMS[$this->platform] ?? ucfirst((string) $this->platform);
    }

    public function activeTokenCount(): int
    {
        return $this->tokens()->active()->count();
    }

    private static function newClientId(): string
    {
        // Prefixed so a key found in a log or a support ticket is recognisable
        // for what it is - and so a leaked one can be searched for.
        return 'rad_'.Str::lower(Str::random(28));
    }

    private static function newSecret(): string
    {
        return Str::random(64);
    }
}
