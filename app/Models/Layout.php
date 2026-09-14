<?php

namespace App\Models;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Builder\LayoutRenderer;
use App\Cms\Builder\StyleCompiler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Cache;

/**
 * A visual layout: the tree the editor produces.
 *
 * Two copies are kept. `data` is what visitors see; `draft_data` is what the
 * editor autosaves into. Publishing copies the draft over the live tree and
 * recompiles the stylesheet, so an unfinished edit never reaches the public
 * site by accident.
 */
class Layout extends Model
{
    /** Revisions kept per layout before the oldest are trimmed. */
    private const MAX_REVISIONS = 25;

    protected $fillable = [
        'layoutable_type', 'layoutable_id', 'region', 'theme_slug',
        'data', 'draft_data', 'compiled_css', 'is_enabled', 'updated_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'draft_data' => 'array',
            'is_enabled' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $layout) => $layout->flushCaches());
        static::deleted(fn (self $layout) => $layout->flushCaches());
    }

    // Relationships --------------------------------------------------------

    public function layoutable(): MorphTo
    {
        return $this->morphTo();
    }

    public function revisions(): HasMany
    {
        return $this->revisions_relation();
    }

    public function revisions_relation(): HasMany
    {
        return $this->hasMany(LayoutRevision::class)->latest();
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Tree access ----------------------------------------------------------

    /** The published tree. */
    public function tree(): array
    {
        return $this->data ?? [];
    }

    /** The tree the editor should open: the draft if there is one. */
    public function editableTree(): array
    {
        return $this->draft_data ?? $this->data ?? [];
    }

    public function isEmpty(): bool
    {
        return $this->tree() === [];
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->draft_data !== null
            && json_encode($this->draft_data) !== json_encode($this->data);
    }

    // Saving ---------------------------------------------------------------

    /** Autosave from the editor. Does not touch the live layout. */
    public function saveDraft(array $tree, ?int $userId = null): void
    {
        $this->forceFill([
            'draft_data' => $tree,
            'updated_by' => $userId ?? auth()->id(),
        ])->save();
    }

    /**
     * Make the current draft live: snapshot what is being replaced, copy the
     * draft across and recompile the stylesheet.
     */
    public function publish(array $tree, ?int $userId = null): void
    {
        if (! $this->isEmpty()) {
            $this->snapshot();
        }

        $this->forceFill([
            'data' => $tree,
            'draft_data' => $tree,
            'compiled_css' => app(StyleCompiler::class)->compile($tree),
            'is_enabled' => true,
            'updated_by' => $userId ?? auth()->id(),
            'published_at' => now(),
        ])->save();
    }

    /** Store the currently published tree as a revision. */
    public function snapshot(?string $note = null): void
    {
        LayoutRevision::create([
            'layout_id' => $this->id,
            'data' => $this->data ?? [],
            'note' => $note,
            'created_by' => auth()->id(),
        ]);

        // Keep the table from growing without bound on a busy site.
        $keep = LayoutRevision::where('layout_id', $this->id)
            ->latest('id')
            ->limit(self::MAX_REVISIONS)
            ->pluck('id');

        LayoutRevision::where('layout_id', $this->id)
            ->whereNotIn('id', $keep)
            ->delete();
    }

    public function restore(LayoutRevision $revision): void
    {
        $this->publish($revision->data);
    }

    /**
     * Put this layout back to nothing, so whatever it was overriding takes
     * over again: a theme region falls back to the theme's own markup, and
     * a page falls back to its classic editor content.
     *
     * The current version is snapshotted first, so "restore default" is
     * itself undoable from the history screen - which is the whole point of
     * offering it as a recovery step.
     */
    public function restoreDefault(?int $userId = null): void
    {
        if (! $this->isEmpty()) {
            $this->snapshot('Before restoring the default');
        }

        $this->forceFill([
            'data' => [],
            'draft_data' => null,
            'compiled_css' => null,
            'published_at' => null,
            'updated_by' => $userId ?? auth()->id(),
        ])->save();
    }

    /** Rebuild the stylesheet, after a block's control mapping has changed. */
    public function recompile(): void
    {
        $this->forceFill([
            'compiled_css' => app(StyleCompiler::class)->compile($this->tree()),
        ])->save();
    }

    // Rendering ------------------------------------------------------------

    public function renderHtml(array $context = []): string
    {
        return app(LayoutRenderer::class)->render($this->tree(), $context);
    }

    /** Widget types used, so the admin can see what a layout depends on. */
    public function widgetTypes(): array
    {
        $types = [];

        $walk = function (array $nodes) use (&$walk, &$types) {
            foreach ($nodes as $node) {
                if (($node['type'] ?? '') === 'widget' && filled($node['widgetType'] ?? null)) {
                    $types[] = $node['widgetType'];
                }

                $walk($node['elements'] ?? []);
            }
        };

        $walk($this->tree());

        return array_values(array_unique($types));
    }

    public function widgetCount(): int
    {
        $count = 0;

        $walk = function (array $nodes) use (&$walk, &$count) {
            foreach ($nodes as $node) {
                if (($node['type'] ?? '') === 'widget') {
                    $count++;
                }

                $walk($node['elements'] ?? []);
            }
        };

        $walk($this->tree());

        return $count;
    }

    // Caching --------------------------------------------------------------

    private function flushCaches(): void
    {
        if ($this->region) {
            Cache::forget('cms.builder.regions.'.$this->theme_slug);
        }

        // A layout can add or remove URLs from the sitemap by adding content.
        Cache::forget('cms.sitemap');
    }

    // Lookup helpers -------------------------------------------------------

    /** Find or create the layout for a content model. */
    public static function forModel(Model $model): self
    {
        return static::firstOrCreate([
            'layoutable_type' => $model::class,
            'layoutable_id' => $model->getKey(),
        ], [
            'data' => [],
            'is_enabled' => true,
        ]);
    }

    /** Find or create the layout for a theme region. */
    public static function forRegion(string $region, ?string $theme = null): self
    {
        return static::firstOrCreate([
            'region' => $region,
            'theme_slug' => $theme ?? themes()->activeSlug(),
        ], [
            'data' => [],
            'is_enabled' => true,
        ]);
    }
}
