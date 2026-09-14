<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A point-in-time snapshot of a published layout, so a bad save can be undone
 * after the fact rather than only within the editor's own undo stack.
 */
class LayoutRevision extends Model
{
    protected $fillable = ['layout_id', 'data', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Rough size of the snapshot, shown in the revisions list. */
    public function summary(): string
    {
        $sections = count($this->data ?? []);

        return $sections.' '.\Illuminate\Support\Str::plural('section', $sections);
    }
}
