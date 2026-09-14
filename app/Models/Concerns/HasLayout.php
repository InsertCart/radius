<?php

namespace App\Models\Concerns;

use App\Cms\Builder\StyleRegistry;
use App\Models\Layout;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Gives a content model an optional visual layout.
 *
 * The important property here is that adding the builder changes nothing for
 * an existing theme. A theme renders page content with {!! $page->content !!},
 * and that keeps working: the content accessor returns the builder's HTML when
 * a layout has been published, and the raw stored HTML when it has not.
 *
 * A theme therefore needs no changes to support the builder, and the editor
 * can be switched off per page at any time to fall back to the classic editor.
 *
 * Admin forms must read the stored value with rawContent() rather than the
 * accessor, or they would try to edit rendered output.
 */
trait HasLayout
{
    public function layout(): MorphOne
    {
        return $this->morphOne(Layout::class, 'layoutable');
    }

    /** True when this record has a published, non-empty visual layout. */
    public function usesBuilder(): bool
    {
        $layout = $this->relationLoaded('layout') ? $this->layout : $this->layout()->first();

        return $layout !== null
            && $layout->is_enabled
            && $layout->published_at !== null
            && $layout->tree() !== [];
    }

    /** The layout for this record, created on first use. */
    public function builderLayout(): Layout
    {
        return Layout::forModel($this);
    }

    /**
     * The HTML for this record's body.
     *
     * Falls back to the stored WYSIWYG content whenever the builder is not in
     * play, so switching a page back to the classic editor is a one-click
     * change with nothing lost.
     */
    public function renderedBody(): string
    {
        // rawContent() rather than the column directly, so a model storing its
        // body elsewhere - a product uses `description` - can redirect it.
        if (! $this->usesBuilder()) {
            return (string) $this->rawContent();
        }

        $layout = $this->layout;

        // Collected here rather than echoed inline, so the stylesheet lands in
        // the document head with the rest of the builder's CSS.
        if (filled($layout->compiled_css)) {
            app(StyleRegistry::class)->add('layout-'.$layout->id, $layout->compiled_css);
        }

        return $layout->renderHtml(['model' => $this]);
    }

    /**
     * Transparent bridge for themes: {!! $page->content !!} keeps working and
     * silently returns the built layout once one exists.
     */
    public function getContentAttribute(mixed $value): mixed
    {
        if ($this->usesBuilder()) {
            return $this->renderedBody();
        }

        return $value;
    }

    /** What the admin editor loads: always the stored HTML, never the layout. */
    public function rawContent(): ?string
    {
        return $this->getRawOriginal('content');
    }

    /** Turn the builder off for this record without deleting the layout. */
    public function disableBuilder(): void
    {
        $this->layout()->update(['is_enabled' => false]);
        $this->unsetRelation('layout');
    }

    public function enableBuilder(): void
    {
        $this->builderLayout()->update(['is_enabled' => true]);
        $this->unsetRelation('layout');
    }

    /** Where the editor opens for this record. */
    public function builderUrl(): string
    {
        return safe_route('admin.builder.edit', [
            'type' => static::builderTypeKey(),
            'id' => $this->getKey(),
        ]);
    }

    /** Short key used in builder URLs, e.g. "page" for App\Models\Page. */
    public static function builderTypeKey(): string
    {
        return strtolower(class_basename(static::class));
    }
}
