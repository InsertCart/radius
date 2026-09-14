<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Discount codes. 'value' means percent*100 for percent coupons (so 12.5%
 * is stored as 1250) and minor currency units for fixed coupons.
 */
class Coupon extends Model
{
    protected $fillable = [
        'code', 'description', 'type', 'value', 'min_order_total', 'max_discount',
        'usage_limit', 'usage_limit_per_user', 'used_count',
        'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_order_total' => 'integer',
            'max_discount' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $coupon) => $coupon->code = strtoupper(trim($coupon->code)));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Returns null when the coupon may be used, or a human-readable reason
     * why it may not. Returning the reason lets checkout tell the customer
     * exactly what is wrong instead of a generic "invalid code".
     */
    public function validationError(int $orderSubtotal, ?User $user = null): ?string
    {
        if (! $this->is_active) {
            return 'This coupon is no longer active.';
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return 'This coupon is not valid yet.';
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return 'This coupon has expired.';
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return 'This coupon has reached its usage limit.';
        }

        if ($orderSubtotal < $this->min_order_total) {
            return 'Your order does not meet the minimum for this coupon.';
        }

        if ($user && $this->usage_limit_per_user) {
            $used = Order::where('user_id', $user->id)
                ->where('coupon_code', $this->code)
                ->whereIn('payment_status', ['paid', 'awaiting'])
                ->count();

            if ($used >= $this->usage_limit_per_user) {
                return 'You have already used this coupon.';
            }
        }

        return null;
    }

    public function isUsableBy(int $orderSubtotal, ?User $user = null): bool
    {
        return is_null($this->validationError($orderSubtotal, $user));
    }

    /** Discount in minor units, never more than the subtotal itself. */
    public function discountFor(int $subtotal): int
    {
        $discount = match ($this->type) {
            'percent' => (int) round($subtotal * ($this->value / 10000)),
            'fixed' => (int) $this->value,
            default => 0,   // free_shipping is applied to the shipping line instead
        };

        if ($this->max_discount) {
            $discount = min($discount, (int) $this->max_discount);
        }

        return max(0, min($discount, $subtotal));
    }

    public function givesFreeShipping(): bool
    {
        return $this->type === 'free_shipping';
    }

    public function displayValue(): string
    {
        return match ($this->type) {
            'percent' => rtrim(rtrim(number_format($this->value / 100, 2), '0'), '.').'%',
            'fixed' => money($this->value),
            default => 'Free shipping',
        };
    }
}
