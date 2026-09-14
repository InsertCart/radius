<?php

namespace App\Models;

use App\Models\Concerns\FlushesPublicCache;
use App\Models\Concerns\HasLayout;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Post extends Model
{
    use FlushesPublicCache, HasLayout, HasSeo, HasSlug, SoftDeletes;

    protected string $slugSource = 'title';

    protected $fillable = [
        'category_id', 'author_id', 'title', 'slug', 'excerpt', 'content',
        'featured_image', 'status', 'published_at', 'is_featured', 'allow_comments',
        'meta_title', 'meta_description', 'meta_keywords', 'og_image',
        'canonical_url', 'schema_type', 'schema_data', 'noindex',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'allow_comments' => 'boolean',
            'noindex' => 'boolean',
            'schema_data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $post) {
            // Roughly 200 words a minute; good enough for a "5 min read" badge.
            $words = str_word_count(strip_tags((string) $post->content));
            $post->reading_minutes = max(1, (int) ceil($words / 200));

            if ($post->status === 'published' && is_null($post->published_at)) {
                $post->published_at = now();
            }
        });
    }

    // Relationships -------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->comments()->where('status', 'approved')->whereNull('parent_id')->latest();
    }

    // Scopes --------------------------------------------------------------

    /**
     * Everything the public site is allowed to see: published, and not
     * scheduled for a future date.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(function (Builder $q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhere('content', 'like', $like);
        });
    }

    // Presentation --------------------------------------------------------

    public function url(): string
    {
        return safe_route('blog.show', $this->slug);
    }

    public function imageUrl(?string $size = null): ?string
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
        return $this->excerpt ?: Str::limit(trim(strip_tags((string) $this->content)), $length);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && (is_null($this->published_at) || $this->published_at->isPast());
    }

    protected function seoDefaultSchemaType(): ?string
    {
        return 'Article';
    }
}
