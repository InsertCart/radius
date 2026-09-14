<?php

namespace App\Models;

use App\Models\Concerns\FlushesPublicCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use FlushesPublicCache;

    protected $fillable = [
        'menu_id', 'parent_id', 'label', 'type', 'url', 'reference_id',
        'target', 'icon', 'sort_order', 'is_active', 'requires_module',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    /**
     * Resolves the item to a real URL. Content-backed items look up the target
     * lazily so that renaming a page's slug does not break the menu.
     */
    public function resolveUrl(): string
    {
        return match ($this->type) {
            'page' => optional(Page::find($this->reference_id))->url() ?? url('/'),
            'post' => optional(Post::find($this->reference_id))->url() ?? url('/'),
            'category' => optional(Category::find($this->reference_id))->url() ?? url('/'),
            'product' => optional(Product::find($this->reference_id))->url() ?? url('/'),
            'route' => $this->safeRoute(),
            default => $this->url ?: '#',
        };
    }

    /** A named route may vanish when its module is disabled. */
    private function safeRoute(): string
    {
        if (blank($this->url)) {
            return '#';
        }

        return \Illuminate\Support\Facades\Route::has($this->url) ? route($this->url) : '#';
    }

    /** Hidden automatically when the module it belongs to is switched off. */
    public function isVisible(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return blank($this->requires_module) || modules()->enabled($this->requires_module);
    }
}
