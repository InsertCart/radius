<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Support\HtmlSanitizer;
use App\Http\Controllers\Admin\Concerns\HandlesSeoFields;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PostController extends Controller
{
    use HandlesSeoFields;

    /** Editor HTML is re-checked here; the browser is not a trusted filter. */
    public function __construct(private HtmlSanitizer $sanitizer) {}

    public function index(Request $request): View
    {
        $posts = Post::with(['category', 'author'])
            ->when($request->filled('q'), fn ($q) => $q->search($request->string('q')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.posts.index', [
            'posts' => $posts,
            'categories' => Category::blog()->orderBy('name')->pluck('name', 'id'),
            'filters' => $request->only(['q', 'status', 'category']),
        ]);
    }

    public function create(): View
    {
        return view('admin.posts.form', $this->formData(new Post([
            'status' => 'draft',
            'allow_comments' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $post = new Post($this->attributes($validated, $request));
        $post->author_id = $request->user()->id;
        $post->save();

        $post->tags()->sync($this->resolveTags($request));

        activity('post.created', "Created the post \"{$post->title}\".", $post);

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Post created.');
    }

    public function edit(Post $post): View
    {
        return view('admin.posts.form', $this->formData($post));
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $validated = $request->validate($this->rules($post));

        $post->fill($this->attributes($validated, $request))->save();
        $post->tags()->sync($this->resolveTags($request));

        activity('post.updated', "Updated the post \"{$post->title}\".", $post);

        return back()->with('status', 'Post saved.');
    }

    public function show(Post $post): RedirectResponse
    {
        return redirect()->route('admin.posts.edit', $post);
    }

    public function destroy(Post $post): RedirectResponse
    {
        $title = $post->title;
        $post->delete();

        activity('post.deleted', "Deleted the post \"{$title}\".");

        return redirect()->route('admin.posts.index')->with('status', 'Post moved to trash.');
    }

    // Helpers -------------------------------------------------------------

    private function rules(?Post $post = null): array
    {
        return array_merge([
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'alpha_dash'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:draft,published,scheduled,archived'],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
            'allow_comments' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'string', 'max:500'],
        ], $this->seoRules());
    }

    private function attributes(array $validated, Request $request): array
    {
        return array_merge([
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $this->sanitizer->clean($validated['content'] ?? null),
            'featured_image' => $validated['featured_image'] ?? null,
            'status' => $validated['status'],
            'published_at' => $validated['published_at'] ?? null,
            'is_featured' => $request->boolean('is_featured'),
            'allow_comments' => $request->boolean('allow_comments'),
        ], $this->seoAttributes($validated, $request));
    }

    /**
     * Tags arrive as a comma-separated string; unknown ones are created on the
     * fly so an author never has to leave the editor to add a tag.
     *
     * @return int[]
     */
    private function resolveTags(Request $request): array
    {
        $names = collect(explode(',', (string) $request->input('tags')))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->take(20);

        return $names->map(function (string $name) {
            return Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            )->id;
        })->all();
    }

    private function formData(Post $post): array
    {
        return [
            'post' => $post,
            'categories' => Category::blog()->orderBy('name')->pluck('name', 'id'),
            'schemaTypes' => $this->schemaTypes(),
            'tagList' => $post->exists ? $post->tags->pluck('name')->implode(', ') : '',
        ];
    }
}
