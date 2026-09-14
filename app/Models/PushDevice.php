<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Firebase Cloud Messaging registration token. Tokens are far longer than
 * an indexable column, so uniqueness is enforced on a sha256 of the token.
 */
class PushDevice extends Model
{
    protected $fillable = ['user_id', 'token', 'token_hash', 'platform', 'user_agent', 'last_used_at'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $device) => $device->token_hash = hash('sha256', $device->token));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
