<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpToken extends Model
{
    protected $fillable = ['user_id', 'name', 'token_hash', 'can_write'];

    protected $hidden = ['token_hash'];

    protected $casts = ['can_write' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
