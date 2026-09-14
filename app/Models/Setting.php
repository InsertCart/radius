<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Raw storage for the settings key/value table.
 *
 * Application code should go through the SettingsRepository (or the settings()
 * helper) rather than touching this model, so that caching and decryption stay
 * in one place.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'type', 'is_encrypted'];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }
}
