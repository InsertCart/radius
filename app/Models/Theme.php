<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    /** Uploaded by hand, dropped in over FTP, or bundled. */
    public const SOURCE_MANUAL = 'manual';

    /** Installed from the theme marketplace, and so eligible for its updates. */
    public const SOURCE_MARKETPLACE = 'marketplace';

    protected $fillable = [
        'slug', 'name', 'version', 'author', 'author_url',
        'description', 'screenshot', 'is_active', 'meta', 'options',
        'source', 'source_slug', 'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'meta' => 'array',
            'options' => 'array',
            'installed_at' => 'datetime',
        ];
    }

    public function path(string $sub = ''): string
    {
        return rtrim(config('cms.themes.path').DIRECTORY_SEPARATOR.$this->slug.DIRECTORY_SEPARATOR.ltrim($sub, '/\\'), DIRECTORY_SEPARATOR);
    }

    /** Public URL for a file inside the theme's published assets. */
    public function asset(string $file): string
    {
        return asset(config('cms.themes.asset_url').'/'.$this->slug.'/'.ltrim($file, '/'));
    }

    public function screenshotUrl(): ?string
    {
        return $this->screenshot ? $this->asset($this->screenshot) : null;
    }

    /** True when the theme folder is still present on disk. */
    public function existsOnDisk(): bool
    {
        return is_dir($this->path()) && is_file($this->path('theme.json'));
    }

    public function isFromMarketplace(): bool
    {
        return $this->source === self::SOURCE_MARKETPLACE && filled($this->source_slug);
    }

    public function isDefault(): bool
    {
        return $this->slug === config('cms.themes.default');
    }
}
