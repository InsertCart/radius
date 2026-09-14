<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'subject', 'message',
        'status', 'extra', 'ip_address', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'extra' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function markRead(): void
    {
        if (is_null($this->read_at)) {
            $this->forceFill(['read_at' => now(), 'status' => 'read'])->save();
        }
    }
}
