<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesSeoFields;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Categories for both the blog and the shop, kept apart by the 'type' column.
 */
class CategoryController extends Controller
{
    use HandlesSeoFields;

    public function index(Request $request): View
    {
        $type = $this->resolveType($request->string('type')->toString());

        return view('admin.categories.index', [
            'type' => $type,
            'categories' => Category::where('type', $type)
                ->with('parent')
                ->withCount($type === Category::TYPE_SHOP ? 'products' : 'posts')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'availableTypes' => $this->availableTypes(),
        ]);
    }

    public function create(Request $request): View
    {
        $type = $this->resolveType($request->string('type')->toString());

        return view('admin.categories.form', $this->formData(new Category([
            'type' => $type,
            'is_active' => true,
            'show_in_menu' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $category = Category::create($this->attributes($validated, $request));

        activity('category.created', "Created the category \"{$category->name}\".", $category);

        return redirect()
            ->route('admin.categories.index', ['type' => $category->type])
            ->with('status', 'Category created.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', $this->formData($category));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate($this->rules($category));

        $category->fill($this->attributes($validated, $request))->save();

        activity('category.updated', "Updated the category \"{$category->name}\".", $category);

        // The admin URL is keyed by slug, so going "back" after the slug
        // changed would land on an address this category no longer answers to.
        return redirect()->route('admin.categories.edit', $category)->with('status', 'Category saved.');
    }

    public function show(Category $category): RedirectResponse
    {
        return redirect()->route('admin.categories.edit', $category);
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Children would be orphaned rather than deleted, so ask first.
        if ($category->children()->exists()) {
            return back()->with('error', 'Move or delete the sub-categories first.');
        }

        $type = $category->type;
        $name = $category->name;
        $category->delete();

        activity('category.deleted', "Deleted the category \"{$name}\".");

        return redirect()
            ->route('admin.categories.index', ['type' => $type])
            ->with('status', 'Category deleted.');
    }

    private function rules(?Category $category = null): array
    {
        return array_merge([
            'type' => ['required', Rule::in([Category::TYPE_BLOG, Category::TYPE_SHOP])],
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'alpha_dash'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'show_in_menu' => ['nullable', 'boolean'],
        ], $this->seoRules());
    }

    private function attributes(array $validated, Request $request): array
    {
        return array_merge([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'image' => $validated['image'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
            'show_in_menu' => $request->boolean('show_in_menu'),
        ], $this->seoAttributes($validated, $request));
    }

    private function formData(Category $category): array
    {
        return [
            'category' => $category,
            // A category cannot be its own parent, and the list is scoped to
            // the same tree so a blog category cannot sit under a shop one.
            'parents' => Category::where('type', $category->type)
                ->where('id', '!=', $category->id ?? 0)
                ->orderBy('name')
                ->pluck('name', 'id'),
            'schemaTypes' => $this->schemaTypes(),
            'availableTypes' => $this->availableTypes(),
        ];
    }

    private function availableTypes(): array
    {
        $types = [];

        if (modules()->enabled('blog')) {
            $types[Category::TYPE_BLOG] = 'Blog';
        }

        if (modules()->enabled('shop')) {
            $types[Category::TYPE_SHOP] = 'Shop';
        }

        return $types ?: [Category::TYPE_BLOG => 'Blog'];
    }

    private function resolveType(?string $type): string
    {
        $available = $this->availableTypes();

        return isset($available[$type]) ? $type : (string) array_key_first($available);
    }
}
