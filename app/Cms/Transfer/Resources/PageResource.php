<?php

namespace App\Cms\Transfer\Resources;

use App\Cms\Support\HtmlSanitizer;
use App\Cms\Transfer\ExportContext;
use App\Cms\Transfer\ImportContext;
use App\Cms\Transfer\ImportReport;
use App\Models\Page;
use Illuminate\Database\Eloquent\Model;

/**
 * Static pages, with their nesting and their builder layout.
 *
 * The body is read with rawContent() rather than through the model, because
 * the content accessor returns *rendered* builder output once a layout exists.
 * Exporting that would bake today's HTML into the archive and lose the tree
 * that produced it.
 */
class PageResource extends TransferResource
{
    public function __construct(private HtmlSanitizer $sanitizer) {}

    public function key(): string
    {
        return 'pages';
    }

    public function label(): string
    {
        return 'Pages';
    }

    public function modelClass(): string
    {
        return Page::class;
    }

    public function module(): ?string
    {
        return 'pages';
    }

    public function hint(): string
    {
        return 'Static pages, their parent-child nesting and any builder layout.';
    }

    protected function hasStatus(): bool
    {
        return true;
    }

    public function record(Model $model, ExportContext $context): array
    {
        /** @var Page $model */
        return array_filter([
            'title' => $model->title,
            'slug' => $model->slug,
            'content' => $model->rawContent(),
            'featured_image' => $context->file($model->featured_image),
            'template' => $model->template,
            'status' => $model->status,
            'show_in_menu' => (bool) $model->show_in_menu,
            'is_homepage' => (bool) $model->is_homepage,
            'sort_order' => (int) $model->sort_order,
            'parent' => $model->parent?->slug,
            'seo' => $this->seo($model, $context),
            'layout' => $this->layout($model, $context),
        ] + $this->timestamps($model), fn ($value) => $value !== null);
    }

    public function import(array $record, ImportContext $context): string
    {
        $slug = $record['slug'] ?? null;

        if (blank($slug)) {
            return ImportReport::SKIPPED;
        }

        $existing = Page::where('slug', $slug)->first();

        if ($existing && ! $context->options->updatesExisting()) {
            return ImportReport::SKIPPED;
        }

        $page = $existing ?: new Page(['slug' => $slug]);

        $page->fill(array_merge([
            'title' => $record['title'] ?? $slug,
            // Cleaned on the way in for the same reason editor output is: the
            // file was written somewhere else, and this HTML is rendered
            // unescaped to every visitor.
            'content' => $this->sanitizer->clean($context->rewrite($record['content'] ?? null)),
            'featured_image' => $context->mediaPath($record['featured_image'] ?? null),
            'template' => $record['template'] ?? null,
            'status' => $context->options->statusFor($record['status'] ?? null) ?? 'draft',
            'show_in_menu' => (bool) ($record['show_in_menu'] ?? false),
            'sort_order' => (int) ($record['sort_order'] ?? 0),
            'is_homepage' => $this->homepage($record, $page, $context),
        ], $this->seoAttributes($record, $context)));

        $this->applyTimestamps($page, $record);
        $page->save();

        $this->applyLayout($page, $record, $context);

        return $this->outcome(! $existing);
    }

    /**
     * Whether this page may take over as the home page.
     *
     * Saving a page with is_homepage set demotes whichever page holds it now,
     * so an import is not allowed to do that to a site that already made the
     * choice. A site with no home page yet - a fresh install being seeded - is
     * a different matter, and gets the one the bundle nominated.
     */
    private function homepage(array $record, Page $page, ImportContext $context): bool
    {
        if (! ($record['is_homepage'] ?? false)) {
            return (bool) $page->is_homepage;
        }

        if ($page->is_homepage) {
            return true;
        }

        if (! Page::where('is_homepage', true)->exists()) {
            return true;
        }

        $context->report->note('pages', '"'.($record['title'] ?? $record['slug']).'" was the home page on the old site; this site already has one, so it was imported as an ordinary page.');

        return false;
    }

    public function needsLinkPass(): bool
    {
        return true;
    }

    public function link(array $record, ImportContext $context): void
    {
        if (blank($record['parent'] ?? null) || blank($record['slug'] ?? null)) {
            return;
        }

        $child = Page::where('slug', $record['slug'])->first();
        $parent = Page::where('slug', $record['parent'])->first();

        if (! $child || ! $parent || $child->is($parent) || $child->parent_id === $parent->id) {
            return;
        }

        $child->parent_id = $parent->id;
        $child->save();
    }

    public function csvColumns(): array
    {
        return [
            'title' => 'Title',
            'slug' => 'Slug',
            'status' => 'Status',
            'parent' => 'Parent',
            'template' => 'Template',
            'show_in_menu' => 'In menu',
            'created_at' => 'Created',
        ];
    }
}
