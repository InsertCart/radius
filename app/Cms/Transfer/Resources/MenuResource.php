<?php

namespace App\Cms\Transfer\Resources;

use App\Cms\Transfer\ExportContext;
use App\Cms\Transfer\ImportContext;
use App\Cms\Transfer\ImportReport;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Navigation menus and their items, as a tree.
 *
 * An item that points at a page does not carry that page's id - it carries its
 * slug, and the id is looked up on the way in. An item whose target is not on
 * this site keeps its label and falls back to a plain link, which is far
 * kinder than a menu entry that silently sends visitors to the home page.
 *
 * Items are replaced wholesale when a menu is updated. A menu is one arranged
 * thing, and merging two orderings produces an order nobody chose.
 */
class MenuResource extends TransferResource
{
    public function key(): string
    {
        return 'menus';
    }

    public function label(): string
    {
        return 'Menus';
    }

    public function modelClass(): string
    {
        return Menu::class;
    }

    public function hint(): string
    {
        return 'Navigation menus, their items and nesting.';
    }

    protected function dateColumn(): ?string
    {
        return null;
    }

    protected function baseQuery(): Builder
    {
        return Menu::query()->with('items');
    }

    public function record(Model $model, ExportContext $context): array
    {
        /** @var Menu $model */
        $items = $model->items->groupBy('parent_id');

        return [
            'name' => $model->name,
            'slug' => $model->slug,
            'description' => $model->description,
            'items' => $this->branch($items, null),
        ] + $this->timestamps($model);
    }

    /** @param Collection<int|string, Collection<int, MenuItem>> $grouped */
    private function branch($grouped, ?int $parentId): array
    {
        $branch = [];

        foreach ($grouped->get($parentId, collect())->sortBy('sort_order') as $item) {
            $branch[] = array_filter([
                'label' => $item->label,
                'type' => $item->type,
                'url' => $item->url,
                'reference' => $this->reference($item),
                'target' => $item->target,
                'icon' => $item->icon,
                'sort_order' => (int) $item->sort_order,
                'is_active' => (bool) $item->is_active,
                'requires_module' => $item->requires_module,
                'children' => $this->branch($grouped, $item->id),
            ], fn ($value) => $value !== null && $value !== []);
        }

        return $branch;
    }

    /** The slug of whatever a content-backed item points at. */
    private function reference(MenuItem $item): ?string
    {
        if (blank($item->reference_id)) {
            return null;
        }

        return match ($item->type) {
            'page' => Page::find($item->reference_id)?->slug,
            'post' => Post::find($item->reference_id)?->slug,
            'category' => Category::find($item->reference_id)?->slug,
            'product' => Product::find($item->reference_id)?->slug,
            default => null,
        };
    }

    public function import(array $record, ImportContext $context): string
    {
        $slug = $record['slug'] ?? null;

        if (blank($slug) && blank($record['name'] ?? null)) {
            return ImportReport::SKIPPED;
        }

        $slug = $slug ? Str::slug($slug) : Str::slug($record['name']);
        $existing = Menu::where('slug', $slug)->first();

        if ($existing && ! $context->options->updatesExisting()) {
            return ImportReport::SKIPPED;
        }

        $menu = $existing ?: new Menu(['slug' => $slug]);

        $menu->fill([
            'slug' => $slug,
            'name' => $record['name'] ?? Str::headline($slug),
            'description' => $record['description'] ?? null,
        ])->save();

        if ($existing) {
            $menu->items()->delete();
        }

        $this->items($menu, (array) ($record['items'] ?? []), null, $context);

        return $this->outcome(! $existing);
    }

    private function items(Menu $menu, array $items, ?int $parentId, ImportContext $context, int $depth = 0): void
    {
        // Ten levels is far past any real menu, and stops a hand-edited file
        // with a cycle in it from recursing until the request dies.
        if ($depth > 10) {
            return;
        }

        foreach ($items as $index => $item) {
            if (blank($item['label'] ?? null)) {
                continue;
            }

            [$type, $referenceId, $url] = $this->target($item, $context);

            $created = $menu->items()->create([
                'parent_id' => $parentId,
                'label' => $item['label'],
                'type' => $type,
                'url' => $url,
                'reference_id' => $referenceId,
                'target' => in_array($item['target'] ?? null, ['_self', '_blank'], true) ? $item['target'] : '_self',
                'icon' => $item['icon'] ?? null,
                'sort_order' => (int) ($item['sort_order'] ?? $index),
                'is_active' => (bool) ($item['is_active'] ?? true),
                'requires_module' => $item['requires_module'] ?? null,
            ]);

            $this->items($menu, (array) ($item['children'] ?? []), $created->id, $context, $depth + 1);
        }
    }

    /**
     * Resolves what an item points at on this site.
     *
     * @return array{0: string, 1: ?int, 2: ?string} type, reference id, url
     */
    private function target(array $item, ImportContext $context): array
    {
        $type = $item['type'] ?? 'custom';
        $slug = $item['reference'] ?? null;

        if (blank($slug)) {
            return [$type === 'route' ? 'route' : 'custom', null, $item['url'] ?? null];
        }

        $model = match ($type) {
            'page' => Page::where('slug', $slug)->first(),
            'post' => Post::where('slug', $slug)->first(),
            'category' => Category::where('slug', $slug)->first(),
            'product' => Product::where('slug', $slug)->first(),
            default => null,
        };

        if ($model) {
            return [$type, $model->getKey(), null];
        }

        $context->report->note('menus', 'Menu item "'.$item['label'].'" pointed at a '.$type.' this site has not got, so it was imported as a plain link.');

        return ['custom', null, $item['url'] ?? null];
    }
}
