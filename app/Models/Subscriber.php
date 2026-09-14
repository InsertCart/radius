<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Subscriber extends Model
{
    protected $fillable = [
        'email', 'name', 'status', 'token', 'source', 'ip_address', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $subscriber) {
            $subscriber->email = strtolower(trim($subscriber->email));
            // Random token, used to build a one-click unsubscribe link.
            $subscriber->token ??= Str::random(48);
        });
    }

    public function scopeSubscribed(Builder $query): Builder
    {
        return $query->where('status', 'subscribed');
    }

    public function unsubscribeUrl(): string
    {
        return safe_route('newsletter.unsubscribe', $this->token);
    }
}
