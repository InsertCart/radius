<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Legacy-URL map, so a buyer migrating from an old site keeps their rankings.
 */
class SeoRedirect extends Model
{
    protected $fillable = ['source', 'destination', 'status_code', 'is_active', 'hits', 'last_hit_at'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status_code' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Stored without a leading slash so lookups are unambiguous.
        static::saving(fn (self $r) => $r->source = trim($r->source, '/'));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function recordHit(): void
    {
        $this->newQuery()->whereKey($this->getKey())->update([
            'hits' => $this->hits + 1,
            'last_hit_at' => now(),
        ]);
    }
}
