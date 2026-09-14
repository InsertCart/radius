<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'variant_id', 'name', 'sku',
        'quantity', 'unit_price', 'line_total', 'options',
        'digital_file', 'digital_name', 'digital_size', 'download_count',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** May be null: the product can be deleted long after the order ships. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** Whether a download link should be shown for this line at all. */
    public function isDownloadable(): bool
    {
        return filled($this->digital_file) && $this->order->isPaid();
    }

    /**
     * Why a download is being refused, or null when it is allowed.
     *
     * Returned as a message rather than a boolean because the customer needs
     * to know which of the two limits they have run into - "try again later"
     * is no help when the answer is "you have used all five downloads".
     */
    public function downloadRefusalReason(): ?string
    {
        if (! $this->isDownloadable()) {
            return 'This order has not been paid for yet.';
        }

        $limit = (int) setting('shop_download_limit', 5);

        if ($limit > 0 && $this->download_count >= $limit) {
            return "You have reached the download limit for this file ({$limit}). "
                .'Please contact us if you need access again.';
        }

        if ($this->downloadsExpireAt()?->isPast()) {
            return 'The download period for this order has ended. '
                .'Please contact us if you need access again.';
        }

        return null;
    }

    public function canDownload(): bool
    {
        return $this->downloadRefusalReason() === null;
    }

    /** Null when downloads never expire, which is the default. */
    public function downloadsExpireAt(): ?\Illuminate\Support\Carbon
    {
        $days = (int) setting('shop_download_days', 0);

        if ($days <= 0) {
            return null;
        }

        $from = $this->order->paid_at ?? $this->order->created_at;

        return $from?->copy()->addDays($days);
    }

    /** How many downloads are left, or null when there is no limit. */
    public function downloadsRemaining(): ?int
    {
        $limit = (int) setting('shop_download_limit', 5);

        return $limit > 0 ? max(0, $limit - (int) $this->download_count) : null;
    }
}
