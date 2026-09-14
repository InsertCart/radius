<?php

namespace App\Models;

use App\Models\Concerns\FlushesPublicCache;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single nested tree shared by the blog and the shop, kept apart by 'type'.
 * One table means one admin screen and one set of SEO fields for both.
 */
class Category extends Model
{
    use FlushesPublicCache, HasSeo, HasSlug;

    public const TYPE_BLOG = 'blog';
    public const TYPE_SHOP = 'shop';

    protected $fillable = [
        'type', 'parent_id', 'name', 'slug', 'description', 'image', 'icon',
        'sort_order', 'is_active', 'show_in_menu',
        'meta_title', 'meta_description', 'meta_keywords', 'og_image',
        'canonical_url', 'schema_type', 'schema_data', 'noindex',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_menu' => 'boolean',
            'noindex' => 'boolean',
            'schema_data' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function scopeBlog(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_BLOG);
    }

    public function scopeShop(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SHOP);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function url(): string
    {
        return $this->type === self::TYPE_SHOP
            ? safe_route('shop.category', $this->slug)
            : safe_route('blog.category', $this->slug);
    }

    /** Breadcrumb trail from the root down to this category. */
    public function ancestors(): array
    {
        $trail = [];
        $node = $this->parent;
        $guard = 0;

        // The guard stops a cycle introduced by hand-editing parent_id from
        // hanging the request.
        while ($node && $guard++ < 10) {
            array_unshift($trail, $node);
            $node = $node->parent;
        }

        return $trail;
    }

    protected function seoDefaultSchemaType(): ?string
    {
        return 'CollectionPage';
    }
}
