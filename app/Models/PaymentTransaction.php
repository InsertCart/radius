<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per attempt against a gateway, successful or not. This is the trail
 * a site owner follows when reconciling a disputed or missing payment.
 */
class PaymentTransaction extends Model
{
    protected $fillable = [
        'order_id', 'gateway', 'type', 'reference', 'gateway_reference',
        'amount', 'currency', 'status', 'message',
        'request_payload', 'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'success' => 'green',
            'failed' => 'red',
            'pending' => 'amber',
            default => 'gray',
        };
    }
}
