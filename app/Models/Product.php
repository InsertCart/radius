<?php

namespace App\Models;

use App\Cms\Search\Concerns\IsSearchable;
use App\Cms\Search\Contracts\Searchable;
use App\Models\Concerns\FlushesPublicCache;
use App\Models\Concerns\HasLayout;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Prices are integers in the currency's minor unit (cents, paise). Every
 * accessor named *Money returns minor units; formatting happens in the view
 * through the money() helper.
 */
class Product extends Model implements Searchable
{
    use FlushesPublicCache, HasLayout, HasSeo, HasSlug, IsSearchable, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'sku', 'short_description', 'description', 'featured_image',
        'price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'cost_price',
        'type', 'manage_stock', 'stock', 'allow_backorder',
        'digital_file', 'digital_name', 'digital_size',
        'weight', 'dimensions', 'requires_shipping',
        'status', 'is_featured', 'sort_order',
        'meta_title', 'meta_description', 'meta_keywords', 'og_image',
        'canonical_url', 'schema_type', 'schema_data', 'noindex',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'sale_price' => 'integer',
            'cost_price' => 'integer',
            'stock' => 'integer',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'manage_stock' => 'boolean',
            'allow_backorder' => 'boolean',
            'requires_shipping' => 'boolean',
            'is_featured' => 'boolean',
            'noindex' => 'boolean',
            'rating' => 'float',
            'schema_data' => 'array',
        ];
    }

    // Relationships -------------------------------------------------------

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'approved')->latest();
    }

    public function gallery(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable')
            ->withPivot(['collection', 'sort_order'])
            ->wherePivot('collection', 'gallery')
            ->orderBy('mediables.sort_order');
    }

    // Scopes --------------------------------------------------------------

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('manage_stock', false)
                ->orWhere('stock', '>', 0)
                ->orWhere('allow_backorder', true);
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('short_description', 'like', $like);
        });
    }

    // Search --------------------------------------------------------------

    public static function searchableQuery(bool $includeScheduled = false): Builder
    {
        return static::query()->published();
    }

    /** The same columns scopeSearch() matches, so both engines agree on the basics. */
    public static function searchableColumns(): array
    {
        return ['name' => 5, 'sku' => 4, 'short_description' => 2];
    }

    public function searchableFields(): array
    {
        return [
            'name' => [$this->name, 5],
            'sku' => [$this->sku, 4],
            'variants' => [$this->variants()->pluck('sku')->filter()->implode(' '), 3],
            'categories' => [$this->categories()->pluck('name')->implode(' '), 2],
            'short_description' => [$this->short_description, 2],
            'description' => [$this->rawContent(), 1],
        ];
    }

    public function toSearchResult(): array
    {
        return [
            'title' => (string) $this->name,
            'url' => $this->url(),
            'excerpt' => $this->searchExcerpt($this->short_description ?: $this->rawContent()),
            'image' => $this->imageUrl(),
            'meta' => money($this->effectivePrice()),
            'visible_from' => null,
        ];
    }

    // Pricing -------------------------------------------------------------

    /** True when a sale price is set and we are inside its date window. */
    public function isOnSale(): bool
    {
        if (blank($this->sale_price) || $this->sale_price >= $this->price) {
            return false;
        }

        $now = now();

        if ($this->sale_starts_at && $now->lt($this->sale_starts_at)) {
            return false;
        }

        if ($this->sale_ends_at && $now->gt($this->sale_ends_at)) {
            return false;
        }

        return true;
    }

    /** The price a customer actually pays, in minor units. */
    public function effectivePrice(): int
    {
        return $this->isOnSale() ? (int) $this->sale_price : (int) $this->price;
    }

    public function discountPercent(): int
    {
        if (! $this->isOnSale() || $this->price <= 0) {
            return 0;
        }

        return (int) round((($this->price - $this->sale_price) / $this->price) * 100);
    }

    // Stock ---------------------------------------------------------------

    public function inStock(int $quantity = 1): bool
    {
        if (! $this->manage_stock || $this->allow_backorder) {
            return true;
        }

        return $this->stock >= $quantity;
    }

    public function isLowStock(): bool
    {
        return $this->manage_stock
            && $this->stock > 0
            && $this->stock <= (int) setting('shop_low_stock_threshold', 5);
    }

    public function isDigital(): bool
    {
        return $this->type === 'digital';
    }

    // Presentation --------------------------------------------------------

    /**
     * A product keeps its body in `description` rather than `content`, so the
     * builder bridge is wired to that column instead.
     */
    public function getDescriptionAttribute(mixed $value): mixed
    {
        return $this->usesBuilder() ? $this->renderedBody() : $value;
    }

    public function rawContent(): ?string
    {
        return $this->getRawOriginal('description');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function url(): string
    {
        return safe_route('shop.show', $this->slug);
    }

    public function imageUrl(): ?string
    {
        if (blank($this->featured_image)) {
            return null;
        }

        return str_starts_with($this->featured_image, 'http')
            ? $this->featured_image
            : Storage::disk(config('cms.media.disk'))->url($this->featured_image);
    }

    public function summary(int $length = 160): string
    {
        return $this->short_description ?: Str::limit(trim(strip_tags((string) $this->description)), $length);
    }

    /** Recomputes the cached rating aggregate after a review changes. */
    public function refreshRating(): void
    {
        $stats = $this->reviews()
            ->where('status', 'approved')
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')
            ->first();

        $this->forceFill([
            'review_count' => (int) $stats->total,
            'rating' => round((float) $stats->average, 2),
        ])->saveQuietly();
    }

    protected function seoDefaultSchemaType(): ?string
    {
        return 'Product';
    }
}
