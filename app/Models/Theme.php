<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    protected $fillable = [
        'slug', 'name', 'version', 'author', 'author_url',
        'description', 'screenshot', 'is_active', 'meta', 'options',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'meta' => 'array',
            'options' => 'array',
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

    public function isDefault(): bool
    {
        return $this->slug === config('cms.themes.default');
    }
}
