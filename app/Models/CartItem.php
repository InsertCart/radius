<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'variant_id', 'quantity', 'unit_price', 'options'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'quantity' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function lineTotal(): int
    {
        return $this->unit_price * $this->quantity;
    }

    /**
     * The price the product would cost right now. Compared against the
     * snapshotted unit_price so the cart can warn about stale pricing.
     */
    public function currentPrice(): int
    {
        return $this->variant?->effectivePrice() ?? $this->product->effectivePrice();
    }

    public function priceHasChanged(): bool
    {
        return $this->currentPrice() !== (int) $this->unit_price;
    }

    public function name(): string
    {
        $name = $this->product->name;

        return $this->variant ? $name.' - '.$this->variant->optionsLabel() : $name;
    }

    public function imageUrl(): ?string
    {
        return $this->variant?->imageUrl() ?? $this->product->imageUrl();
    }

    public function isAvailable(): bool
    {
        if (! $this->product || $this->product->status !== 'published') {
            return false;
        }

        return $this->variant
            ? $this->variant->inStock($this->quantity)
            : $this->product->inStock($this->quantity);
    }
}
