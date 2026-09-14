<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = [
        'driver', 'to', 'message', 'status', 'provider_reference', 'error', 'response',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
        ];
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }
}
