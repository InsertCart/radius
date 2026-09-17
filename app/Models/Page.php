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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Page extends Model implements Searchable
{
    use FlushesPublicCache, HasLayout, HasSeo, HasSlug, IsSearchable, SoftDeletes;

    protected string $slugSource = 'title';

    protected $fillable = [
        'parent_id', 'title', 'slug', 'content', 'featured_image', 'template',
        'status', 'show_in_menu', 'is_homepage', 'sort_order',
        'meta_title', 'meta_description', 'meta_keywords', 'og_image',
        'canonical_url', 'schema_type', 'schema_data', 'noindex',
    ];

    protected function casts(): array
    {
        return [
            'show_in_menu' => 'boolean',
            'is_homepage' => 'boolean',
            'noindex' => 'boolean',
            'schema_data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Exactly one page may be the homepage.
        static::saved(function (self $page) {
            if ($page->is_homepage) {
                static::where('id', '!=', $page->id)
                    ->where('is_homepage', true)
                    ->update(['is_homepage' => false]);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public static function searchableQuery(bool $includeScheduled = false): Builder
    {
        return static::query()->published();
    }

    public static function searchableColumns(): array
    {
        return ['title' => 5, 'content' => 1];
    }

    public function toSearchResult(): array
    {
        return [
            'title' => (string) $this->title,
            'url' => $this->url(),
            'excerpt' => $this->searchExcerpt($this->meta_description ?: $this->content),
            'image' => $this->imageUrl(),
            'meta' => null,
            'visible_from' => null,
        ];
    }

    public function url(): string
    {
        return $this->is_homepage ? url('/') : safe_route('page.show', $this->slug);
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

    protected function seoDefaultSchemaType(): ?string
    {
        return 'WebPage';
    }
}
