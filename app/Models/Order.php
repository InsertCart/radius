<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    public const STATUSES = ['pending', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'];
    public const PAYMENT_STATUSES = ['unpaid', 'awaiting', 'paid', 'failed', 'refunded', 'partially_refunded'];

    protected $fillable = [
        'order_number', 'user_id', 'email', 'phone', 'status', 'payment_status',
        'payment_gateway', 'transaction_id', 'currency',
        'subtotal', 'tax_total', 'shipping_total', 'discount_total', 'grand_total',
        'coupon_code', 'billing_address', 'shipping_address',
        'customer_note', 'admin_note', 'tracking_number', 'ip_address',
        'paid_at', 'shipped_at', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'shipping_total' => 'integer',
            'discount_total' => 'integer',
            'grand_total' => 'integer',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            $order->order_number ??= static::generateOrderNumber();
        });
    }

    /**
     * Sequential-looking but not guessable: prefix, date, and a random block
     * so a customer cannot enumerate other people's orders from their own.
     */
    public static function generateOrderNumber(): string
    {
        $prefix = setting('shop_order_prefix', 'ORD-');

        do {
            $number = $prefix.now()->format('ymd').'-'.strtoupper(Str::random(6));
        } while (static::where('order_number', $number)->exists());

        return $number;
    }

    // Relationships -------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->latest();
    }

    // Scopes --------------------------------------------------------------

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // State transitions ---------------------------------------------------

    public function markPaid(?string $transactionId = null): void
    {
        $this->forceFill([
            'payment_status' => 'paid',
            'transaction_id' => $transactionId ?? $this->transaction_id,
            'paid_at' => $this->paid_at ?? now(),
            // Only advance the fulfilment status if the order is still sitting
            // at 'pending'; an admin may already have moved it further along.
            'status' => $this->status === 'pending' ? 'processing' : $this->status,
        ])->save();
    }

    public function markFailed(?string $reason = null): void
    {
        $this->forceFill([
            'payment_status' => 'failed',
            'admin_note' => trim($this->admin_note."\n".$reason),
        ])->save();
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isEditable(): bool
    {
        return ! in_array($this->status, ['completed', 'cancelled', 'refunded'], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'completed' => 'green',
            'processing' => 'blue',
            'shipped' => 'indigo',
            'cancelled' => 'red',
            'refunded' => 'amber',
            default => 'gray',
        };
    }

    public function paymentStatusColor(): string
    {
        return match ($this->payment_status) {
            'paid' => 'green',
            'failed' => 'red',
            'refunded', 'partially_refunded' => 'amber',
            'awaiting' => 'blue',
            default => 'gray',
        };
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }
}
