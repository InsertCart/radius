<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Generates a URL-safe, unique slug on save when the model does not already
 * carry one. Models opt in by declaring $slugSource (defaults to 'name').
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function ($model) {
            $source = property_exists($model, 'slugSource') ? $model->slugSource : 'name';

            if (blank($model->slug) && filled($model->{$source})) {
                $model->slug = $model->generateUniqueSlug($model->{$source});
            } elseif ($model->isDirty('slug') && filled($model->slug)) {
                $model->slug = $model->generateUniqueSlug($model->slug);
            }
        });
    }

    public function generateUniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: Str::random(8);
        $slug = $base;
        $suffix = 1;

        while ($this->slugExists($slug)) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        $query = static::query()->where('slug', $slug);

        // Categories are scoped per type, so 'news' may exist under both the
        // blog and the shop tree without colliding.
        if ($this->getTable() === 'categories' && filled($this->type)) {
            $query->where('type', $this->type);
        }

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($this), true)) {
            $query->withTrashed();
        }

        return $query->exists();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
