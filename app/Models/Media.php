<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'name', 'file_name', 'mime_type', 'extension', 'size', 'disk', 'path',
        'width', 'height', 'alt', 'title', 'conversions', 'uploaded_by',
        'on_cdn', 'has_local_copy',
    ];

    /**
     * Matches the column defaults, so a record that has not been reloaded
     * still answers honestly about where its file is. Without these a fresh
     * upload reports null for both, which reads as "unknown" everywhere that
     * has to choose an address.
     */
    protected $attributes = [
        'on_cdn' => false,
        'has_local_copy' => true,
    ];

    protected function casts(): array
    {
        return [
            'conversions' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'on_cdn' => 'boolean',
            'has_local_copy' => 'boolean',
        ];
    }

    protected $appends = ['url'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Where this file is served from - this server, or whichever storage
     * provider or CDN the owner configured. The manager decides; a file that
     * has not reached the provider yet is still served from here.
     */
    public function getUrlAttribute(): string
    {
        return cdn()->urlForMedia($this);
    }

    /**
     * URL for a generated size ('thumb', 'medium', 'large'), falling back to
     * the original when the conversion was never made (SVGs, PDFs, videos).
     */
    public function conversionUrl(string $size = 'thumb'): string
    {
        return isset($this->conversions[$size])
            ? cdn()->urlForMedia($this, $size)
            : $this->url;
    }

    /** Every path this record owns: the original and each generated size. */
    public function paths(): array
    {
        return array_values(array_merge([$this->path], array_values($this->conversions ?? [])));
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $i === 0 ? 0 : 1).' '.$units[$i];
    }

    /**
     * Removes the original and every generated size, from this server and
     * from the storage provider. Both are attempted whatever the flags say:
     * a stale flag should not leave a file behind that the owner asked to
     * be gone.
     */
    public function deleteFiles(): void
    {
        $disk = Storage::disk($this->disk);

        foreach ($this->paths() as $path) {
            $disk->delete($path);

            if ($this->on_cdn) {
                cdn()->forget($path);
            }
        }
    }
}
