<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_CUSTOMER = 'customer';

    /** Roles that may reach the admin panel at all. */
    public const STAFF_ROLES = [self::ROLE_ADMIN, self::ROLE_EDITOR];

    protected $fillable = [
        'name', 'email', 'email_verified_at', 'password', 'role', 'phone', 'phone_verified_at',
        'avatar', 'status', 'last_login_at', 'last_login_ip',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            // The TOTP secret and recovery codes are encrypted at rest, so a
            // leaked database dump alone does not defeat the second factor.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    // Relationships -------------------------------------------------------

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)->inPickOrder();
    }

    /**
     * The address checkout should fill itself in with, if there is one. The
     * flagged default wins; failing that, whichever was added most recently,
     * so a customer with one address never has to type it a second time.
     */
    public function defaultAddress(string $kind = 'billing'): ?Address
    {
        $column = $kind === 'shipping' ? 'is_default_shipping' : 'is_default_billing';

        return $this->hasMany(Address::class)
            ->orderByDesc($column)
            ->orderByDesc('id')
            ->first();
    }

    public function pushDevices(): HasMany
    {
        return $this->hasMany(PushDevice::class);
    }

    // Scopes --------------------------------------------------------------

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereIn('role', self::STAFF_ROLES);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    // Role helpers --------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, self::STAFF_ROLES, true);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // Two-factor ----------------------------------------------------------

    /** True only once the user has confirmed a code from their app. */
    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at);
    }

    public function avatarUrl(): string
    {
        if ($this->avatar) {
            return str_starts_with($this->avatar, 'http')
                ? $this->avatar
                : Storage::disk(config('cms.media.disk'))->url($this->avatar);
        }

        // Deterministic fallback: initials rendered by the front end.
        return 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($this->email))).'?d=mp&s=160';
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
