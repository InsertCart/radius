<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'author_name', 'rating',
        'title', 'body', 'status', 'verified_purchase',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'verified_purchase' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Keep the product's cached rating aggregate in step with its reviews.
        static::saved(fn (self $review) => $review->product?->refreshRating());
        static::deleted(fn (self $review) => $review->product?->refreshRating());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function displayName(): string
    {
        return $this->user?->name ?: ($this->author_name ?: 'Anonymous');
    }
}
