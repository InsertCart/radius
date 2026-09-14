<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Short-lived one-time codes for SMS verification and OTP login.
 * Only a hash of the code is stored, so a database read cannot reveal it.
 */
class OtpCode extends Model
{
    protected $fillable = [
        'identifier', 'channel', 'purpose', 'code_hash',
        'attempts', 'expires_at', 'consumed_at',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return ! is_null($this->consumed_at);
    }

    public function matches(string $code): bool
    {
        return Hash::check($code, $this->code_hash);
    }

    public function consume(): void
    {
        $this->forceFill(['consumed_at' => now()])->save();
    }
}
