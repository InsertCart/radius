<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'enabled', 'is_core', 'sort_order', 'config'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_core' => 'boolean',
            'config' => 'array',
        ];
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /** Core modules are structural and may not be switched off. */
    public function canBeDisabled(): bool
    {
        return ! $this->is_core;
    }
}
