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
    ];

    protected function casts(): array
    {
        return [
            'conversions' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected $appends = ['url'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * URL for a generated size ('thumb', 'medium', 'large'), falling back to
     * the original when the conversion was never made (SVGs, PDFs, videos).
     */
    public function conversionUrl(string $size = 'thumb'): string
    {
        $path = $this->conversions[$size] ?? null;

        return $path ? Storage::disk($this->disk)->url($path) : $this->url;
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

    /** Removes the original and every generated size from disk. */
    public function deleteFiles(): void
    {
        $disk = Storage::disk($this->disk);
        $disk->delete($this->path);

        foreach ($this->conversions ?? [] as $path) {
            $disk->delete($path);
        }
    }
}
