<?php

namespace App\Cms\Transfer\Resources;

use App\Cms\Transfer\ExportContext;
use App\Cms\Transfer\ImportContext;
use App\Cms\Transfer\ImportReport;
use App\Models\Category;
use Illuminate\Database\Eloquent\Model;

/**
 * Blog and shop categories, which share one table and are told apart by type.
 *
 * Identified by type plus slug, because "news" may legitimately exist in both
 * trees. Parents are joined up in a second pass: a tree in a file is in
 * whatever order the database handed it over, and a child may well arrive
 * before the parent it belongs under.
 */
class CategoryResource extends TransferResource
{
    public function key(): string
    {
        return 'categories';
    }

    public function label(): string
    {
        return 'Categories';
    }

    public function modelClass(): string
    {
        return Category::class;
    }

    public function hint(): string
    {
        return 'The blog and shop category trees, including how they nest.';
    }

    public function record(Model $model, ExportContext $context): array
    {
        /** @var Category $model */
        return [
            'type' => $model->type,
            'name' => $model->name,
            'slug' => $model->slug,
            'description' => $model->description,
            'image' => $context->file($model->image),
            'icon' => $model->icon,
            'sort_order' => (int) $model->sort_order,
            'is_active' => (bool) $model->is_active,
            'show_in_menu' => (bool) $model->show_in_menu,
            'parent' => $model->parent?->slug,
            'seo' => $this->seo($model, $context),
        ] + $this->timestamps($model);
    }

    public function import(array $record, ImportContext $context): string
    {
        $slug = $record['slug'] ?? null;
        $type = in_array($record['type'] ?? null, [Category::TYPE_BLOG, Category::TYPE_SHOP], true)
            ? $record['type']
            : Category::TYPE_BLOG;

        if (blank($slug)) {
            return ImportReport::SKIPPED;
        }

        $existing = Category::where('type', $type)->where('slug', $slug)->first();

        if ($existing && ! $context->options->updatesExisting()) {
            return ImportReport::SKIPPED;
        }

        $category = $existing ?: new Category(['type' => $type, 'slug' => $slug]);

        $category->fill(array_merge([
            'type' => $type,
            'name' => $record['name'] ?? $slug,
            'description' => $record['description'] ?? null,
            'image' => $context->mediaPath($record['image'] ?? null),
            'icon' => $record['icon'] ?? null,
            'sort_order' => (int) ($record['sort_order'] ?? 0),
            'is_active' => (bool) ($record['is_active'] ?? true),
            'show_in_menu' => (bool) ($record['show_in_menu'] ?? true),
        ], $this->seoAttributes($record, $context)));

        $this->applyTimestamps($category, $record);
        $category->save();

        return $this->outcome(! $existing);
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

        $type = $record['type'] ?? Category::TYPE_BLOG;

        $child = Category::where('type', $type)->where('slug', $record['slug'])->first();
        $parent = Category::where('type', $type)->where('slug', $record['parent'])->first();

        // A category cannot be its own parent, and the tree walker in the
        // model only guards ten levels of cycle - so do not create one here.
        if (! $child || ! $parent || $child->is($parent) || $child->parent_id === $parent->id) {
            return;
        }

        $child->parent_id = $parent->id;
        $child->save();
    }

    public function csvColumns(): array
    {
        return [
            'type' => 'Type',
            'name' => 'Name',
            'slug' => 'Slug',
            'parent' => 'Parent',
            'description' => 'Description',
            'is_active' => 'Active',
            'sort_order' => 'Order',
        ];
    }
}
