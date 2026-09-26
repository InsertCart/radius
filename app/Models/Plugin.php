<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    /** Uploaded by hand or copied into plugins/. */
    public const SOURCE_MANUAL = 'manual';

    /** Installed from the plugin directory, and so eligible for its updates. */
    public const SOURCE_MARKETPLACE = 'marketplace';

    protected $fillable = [
        'slug', 'name', 'version', 'description', 'author', 'author_url',
        'enabled', 'meta', 'config', 'source', 'source_slug', 'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'meta' => 'array',
            'config' => 'array',
            'installed_at' => 'datetime',
        ];
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function path(string $sub = ''): string
    {
        return rtrim(config('cms.plugins.path').DIRECTORY_SEPARATOR.$this->slug.DIRECTORY_SEPARATOR.ltrim($sub, '/\\'), DIRECTORY_SEPARATOR);
    }

    /** True when the plugin folder is still present on disk. */
    public function existsOnDisk(): bool
    {
        return is_file($this->path('plugin.json'));
    }

    /** Named route of the plugin's settings screen, when it has one. */
    public function settingsRoute(): ?string
    {
        $route = $this->meta['settings_route'] ?? null;

        return is_string($route) && \Illuminate\Support\Facades\Route::has($route) ? $route : null;
    }

    public function isFromMarketplace(): bool
    {
        return $this->source === self::SOURCE_MARKETPLACE && filled($this->source_slug);
    }

    /** One value from the plugin's own settings. */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->config ?? [], $key, $default);
    }
}
