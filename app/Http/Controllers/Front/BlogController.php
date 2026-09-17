<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        // Through the search manager rather than the model scope, so the
        // engine chosen under Settings -> Search answers here too.
        $posts = search()->constrain(Post::published(), 'post', $request->string('q')->toString(), rank: true)
            ->with(['category', 'author'])
            ->orderByDesc('published_at')
            ->paginate((int) setting('posts_per_page', 12))
            ->withQueryString();

        seo()->forRoute('blog.index')
            ->title(seo()->resolvedTitle() === '' ? 'Blog' : null)
            ->schema('Blog')
            ->breadcrumbs(['Home' => url('/'), 'Blog' => route('blog.index')]);

        return view('theme::blog.index', [
            'posts' => $posts,
            'categories' => $this->sidebarCategories(),
            'query' => $request->string('q')->toString(),
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::published()
            ->with(['category', 'author', 'tags'])
            ->where('slug', $slug)
            ->firstOrFail();

        // A cheap, lock-free view counter: no model events, no extra queries
        // on the way in, and a race here costs at most one uncounted view.
        Post::whereKey($post->id)->increment('views');

        seo()->forModel($post)
            ->schema($post->seoSchemaType() ?? 'Article', array_filter([
                'headline' => $post->title,
                'datePublished' => $post->published_at?->toIso8601String(),
                'dateModified' => $post->updated_at?->toIso8601String(),
                'author' => $post->author ? ['@type' => 'Person', 'name' => $post->author->name] : null,
                'articleSection' => $post->category?->name,
                'wordCount' => str_word_count(strip_tags((string) $post->content)),
            ]))
            ->breadcrumbs(array_filter([
                'Home' => url('/'),
                'Blog' => route('blog.index'),
                $post->category?->name => $post->category?->url(),
                $post->title => $post->url(),
            ]));

        return view('theme::blog.show', [
            'post' => $post,
            'related' => $this->relatedPosts($post),
            'comments' => $post->allow_comments ? $post->approvedComments()->with('replies')->get() : collect(),
        ]);
    }

    public function category(string $slug): View
    {
        $category = Category::blog()->active()->where('slug', $slug)->firstOrFail();

        $posts = $category->posts()
            ->published()
            ->with('author')
            ->orderByDesc('published_at')
            ->paginate((int) setting('posts_per_page', 12));

        seo()->forModel($category)->breadcrumbs([
            'Home' => url('/'),
            'Blog' => route('blog.index'),
            $category->name => $category->url(),
        ]);

        return view('theme::blog.category', [
            'category' => $category,
            'posts' => $posts,
            'categories' => $this->sidebarCategories(),
        ]);
    }

    public function tag(string $slug): View
    {
        $tag = Tag::where('slug', $slug)->firstOrFail();

        $posts = $tag->posts()
            ->published()
            ->with(['category', 'author'])
            ->orderByDesc('published_at')
            ->paginate((int) setting('posts_per_page', 12));

        seo()->title('Posts tagged '.$tag->name)
            ->description("Everything tagged {$tag->name}.")
            ->schema('CollectionPage');

        return view('theme::blog.tag', [
            'tag' => $tag,
            'posts' => $posts,
            'categories' => $this->sidebarCategories(),
        ]);
    }

    public function comment(Request $request, string $slug): RedirectResponse
    {
        $post = Post::published()->where('slug', $slug)->firstOrFail();

        if (! $post->allow_comments) {
            return back()->with('error', 'Comments are closed on this post.');
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:3000'],
            'parent_id' => ['nullable', 'exists:comments,id'],
            'author_name' => ['required_without:user_id', 'nullable', 'string', 'max:120'],
            'author_email' => ['required_without:user_id', 'nullable', 'email', 'max:190'],
            // Honeypot: a real visitor never sees this field, so anything in
            // it is a bot. Rejected silently rather than with an error.
            'website' => ['nullable', 'size:0'],
        ]);

        $post->comments()->create([
            'user_id' => $request->user()?->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'author_name' => $request->user()?->name ?? $validated['author_name'],
            'author_email' => $request->user()?->email ?? $validated['author_email'],
            'body' => $validated['body'],
            // Everything is held for moderation. An open comment form on a
            // CodeCanyon site is a spam magnet otherwise.
            'status' => 'pending',
            'ip_address' => $request->ip(),
        ]);

        return back()->with('status', 'Thanks. Your comment will appear once it has been approved.');
    }

    private function sidebarCategories()
    {
        return Category::blog()
            ->active()
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->get();
    }

    private function relatedPosts(Post $post)
    {
        return Post::published()
            ->where('id', '!=', $post->id)
            ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();
    }
}
