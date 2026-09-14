<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'name', 'sku', 'options', 'price', 'sale_price',
        'stock', 'image', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'price' => 'integer',
            'sale_price' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** A null variant price means "inherit whatever the parent charges". */
    public function effectivePrice(): int
    {
        if (! is_null($this->sale_price) && $this->sale_price > 0) {
            return (int) $this->sale_price;
        }

        return (int) ($this->price ?? $this->product->effectivePrice());
    }

    public function inStock(int $quantity = 1): bool
    {
        if (! $this->product->manage_stock || $this->product->allow_backorder) {
            return true;
        }

        return $this->stock >= $quantity;
    }

    public function imageUrl(): ?string
    {
        if (blank($this->image)) {
            return $this->product?->imageUrl();
        }

        return str_starts_with($this->image, 'http')
            ? $this->image
            : Storage::disk(config('cms.media.disk'))->url($this->image);
    }

    /** "Size: M / Color: Blue" */
    public function optionsLabel(): string
    {
        return collect($this->options ?? [])
            ->map(fn ($value, $key) => "{$key}: {$value}")
            ->implode(' / ');
    }
}
