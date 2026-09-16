<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Navigation menus. Themes render them by slug, so 'header' and 'footer' are
 * the two a theme can normally rely on existing.
 */
class MenuController extends Controller
{
    public function index(): View
    {
        return view('admin.menus.index', [
            'menus' => Menu::withCount('items')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.menus.form', ['menu' => new Menu]);
    }

    public function store(Request $request): RedirectResponse
    {
        $menu = Menu::create($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'alpha_dash', 'unique:menus,slug'],
            'description' => ['nullable', 'string', 'max:255'],
        ]));

        activity('menu.created', "Created the \"{$menu->name}\" menu.", $menu);

        return redirect()->route('admin.menus.edit', $menu)->with('status', 'Menu created.');
    }

    public function edit(Menu $menu): View
    {
        return view('admin.menus.edit', [
            'menu' => $menu->load('items.children'),
            'items' => $menu->items()->whereNull('parent_id')->with('children')->orderBy('sort_order')->get(),
            'linkTargets' => $this->linkTargets(),
            'moduleOptions' => collect(config('cms.modules'))->map(fn ($m) => $m['name'])->all(),
        ]);
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $menu->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('menus', 'slug')->ignore($menu->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]));

        // The admin URL is keyed by slug, so going "back" after the slug
        // changed would land on an address this menu no longer answers to.
        return redirect()->route('admin.menus.edit', $menu)->with('status', 'Menu saved.');
    }

    public function show(Menu $menu): RedirectResponse
    {
        return redirect()->route('admin.menus.edit', $menu);
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $name = $menu->name;
        $menu->delete();

        activity('menu.deleted', "Deleted the \"{$name}\" menu.");

        return redirect()->route('admin.menus.index')->with('status', 'Menu deleted.');
    }

    // Items ---------------------------------------------------------------

    public function storeItem(Request $request, Menu $menu): RedirectResponse
    {
        $validated = $request->validate($this->itemRules());

        $menu->items()->create($validated + [
            'sort_order' => (int) $menu->items()->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return back()->with('status', 'Menu item added.');
    }

    public function updateItem(Request $request, MenuItem $item): RedirectResponse
    {
        $item->update($request->validate($this->itemRules()) + [
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Menu item saved.');
    }

    public function destroyItem(MenuItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('status', 'Menu item removed.');
    }

    /** Drag-and-drop reordering; accepts a flat list of id/parent/position. */
    public function reorder(Request $request, Menu $menu): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.parent_id' => ['nullable', 'integer'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        // Scoped to this menu, so a crafted request cannot reparent another
        // menu's items into this one.
        foreach ($validated['items'] as $row) {
            $menu->items()->whereKey($row['id'])->update([
                'parent_id' => $row['parent_id'] ?: null,
                'sort_order' => $row['sort_order'],
            ]);
        }

        return response()->json(['saved' => true]);
    }

    private function itemRules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:custom,page,post,category,product,route'],
            'url' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'exists:menu_items,id'],
            'target' => ['required', 'in:_self,_blank'],
            'icon' => ['nullable', 'string', 'max:60'],
            'requires_module' => ['nullable', 'string', 'max:40'],
        ];
    }

    /** Content the admin can link to without typing a URL. */
    private function linkTargets(): array
    {
        $targets = [
            'page' => \App\Models\Page::published()->orderBy('title')->pluck('title', 'id')->all(),
        ];

        if (modules()->enabled('blog')) {
            $targets['post'] = \App\Models\Post::published()->orderBy('title')->limit(100)->pluck('title', 'id')->all();
            $targets['category'] = \App\Models\Category::blog()->orderBy('name')->pluck('name', 'id')->all();
        }

        if (modules()->enabled('shop')) {
            $targets['product'] = \App\Models\Product::published()->orderBy('name')->limit(100)->pluck('name', 'id')->all();
        }

        return $targets;
    }
}
