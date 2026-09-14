<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin audit trail. Written through the activity() helper so that call sites
 * stay a single line.
 */
class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id',
        'description', 'properties', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): ?Model
    {
        if (blank($this->subject_type) || ! class_exists($this->subject_type)) {
            return null;
        }

        return $this->subject_type::find($this->subject_id);
    }
}
