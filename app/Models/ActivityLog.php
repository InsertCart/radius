<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

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

    /** Days entries are kept, per the Advanced setting. 0 means keep forever. */
    public static function retentionDays(): int
    {
        return max(0, settings()->int('activity_log_retention_days', 45));
    }

    /** Delete entries older than the retention period. Returns how many went. */
    public static function prune(): int
    {
        $days = static::retentionDays();

        return $days > 0
            ? static::where('created_at', '<', now()->subDays($days))->delete()
            : 0;
    }

    /**
     * Prune at most once a day without needing the scheduler. Many hosts run
     * this CMS without a cron entry, and the log is only written to when an
     * admin does something, so that is also when it is worth tidying.
     */
    public static function pruneIfDue(): void
    {
        if (Cache::add('activity_log.pruned', true, now()->addDay())) {
            static::prune();
        }
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
