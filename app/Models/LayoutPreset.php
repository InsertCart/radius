<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable chunk of layout: either a saved section an admin can drop into
 * any page, or a whole starter page template.
 */
class LayoutPreset extends Model
{
    use HasSlug;

    protected $fillable = [
        'name', 'slug', 'category', 'type', 'description',
        'thumbnail', 'data', 'is_global', 'is_builtin', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_global' => 'boolean',
            'is_builtin' => 'boolean',
        ];
    }

    public function scopeSections(Builder $query): Builder
    {
        return $query->where('type', 'section');
    }

    public function scopePages(Builder $query): Builder
    {
        return $query->where('type', 'page');
    }
}
