<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Support\HtmlSanitizer;
use App\Http\Controllers\Admin\Concerns\HandlesSeoFields;
use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class PageController extends Controller
{
    use HandlesSeoFields;

    /** Editor HTML is re-checked here; the browser is not a trusted filter. */
    public function __construct(private HtmlSanitizer $sanitizer) {}

    public function index(Request $request): View
    {
        $pages = Page::with('parent')
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('is_homepage')
            ->orderBy('sort_order')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pages.index', [
            'pages' => $pages,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('admin.pages.form', $this->formData(new Page(['status' => 'draft'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $page = Page::create($this->attributes($validated, $request));

        activity('page.created', "Created the page \"{$page->title}\".", $page);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page created.');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.form', $this->formData($page));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $validated = $request->validate($this->rules($page));

        $page->fill($this->attributes($validated, $request))->save();

        activity('page.updated', "Updated the page \"{$page->title}\".", $page);

        return back()->with('status', 'Page saved.');
    }

    public function show(Page $page): RedirectResponse
    {
        return redirect()->route('admin.pages.edit', $page);
    }

    public function destroy(Page $page): RedirectResponse
    {
        if ($page->is_homepage) {
            return back()->with('error', 'Choose a different homepage before deleting this page.');
        }

        $title = $page->title;
        $page->delete();

        activity('page.deleted', "Deleted the page \"{$title}\".");

        return redirect()->route('admin.pages.index')->with('status', 'Page moved to trash.');
    }

    private function rules(?Page $page = null): array
    {
        return array_merge([
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'alpha_dash'],
            'parent_id' => ['nullable', 'exists:pages,id'],
            'content' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'template' => ['nullable', 'string', 'max:60'],
            'status' => ['required', 'in:draft,published'],
            'show_in_menu' => ['nullable', 'boolean'],
            'is_homepage' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], $this->seoRules());
    }

    private function attributes(array $validated, Request $request): array
    {
        return array_merge([
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'content' => $this->sanitizer->clean($validated['content'] ?? null),
            'featured_image' => $validated['featured_image'] ?? null,
            'template' => ($validated['template'] ?? '') ?: null,
            'status' => $validated['status'],
            'show_in_menu' => $request->boolean('show_in_menu'),
            'is_homepage' => $request->boolean('is_homepage'),
            'sort_order' => $validated['sort_order'] ?? 0,
        ], $this->seoAttributes($validated, $request));
    }

    private function formData(Page $page): array
    {
        return [
            'page' => $page,
            'parents' => Page::where('id', '!=', $page->id ?? 0)->orderBy('title')->pluck('title', 'id'),
            'templates' => $this->availableTemplates(),
            'schemaTypes' => $this->schemaTypes(),
        ];
    }

    /**
     * Page templates offered by the active theme: every Blade file under
     * views/pages/ becomes a selectable layout.
     */
    private function availableTemplates(): array
    {
        $templates = ['' => 'Default'];
        $directory = themes()->path(themes()->activeSlug().'/views/pages');

        if (! is_dir($directory)) {
            return $templates;
        }

        foreach (File::files($directory) as $file) {
            $name = str_replace('.blade.php', '', $file->getFilename());

            // 'show' is the generic page renderer, not a selectable template.
            if ($name === 'show') {
                continue;
            }

            $templates[$name] = ucfirst(str_replace(['-', '_'], ' ', $name));
        }

        return $templates;
    }
}
