<?php

namespace App\Http\Controllers\Api\V1;

use App\Cms\Api\Resource;
use App\Cms\Support\HtmlSanitizer;
use App\Http\Controllers\Api\ApiController;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The blog, read the same way the website reads it: only published posts,
 * through the same search engine the site is configured to use, with the
 * same page size.
 */
class PostController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $posts = search()
            ->constrain(Post::published(), 'post', $request->string('q')->toString(), rank: true)
            ->with(['category', 'author'])
            ->when($request->filled('category'), fn ($query) => $query->whereHas(
                'category',
                fn ($q) => $q->where('slug', $request->string('category')->toString())
            ))
            ->when($request->filled('tag'), fn ($query) => $query->whereHas(
                'tags',
                fn ($q) => $q->where('slug', $request->string('tag')->toString())
            ))
            ->when($request->boolean('featured'), fn ($query) => $query->featured())
            ->orderByDesc('published_at')
            ->paginate($this->perPage($request));

        return $this->page(Resource::paginated($posts, fn (Post $post) => Resource::post($post)));
    }

    public function storeDraft(Request $request, HtmlSanitizer $sanitizer): JsonResponse
    {
        $client = $this->client($request);
        if (! $client->can_create_drafts) {
            return $this->fail('This app needs draft creation access.', 403, 'draft_access_required');
        }

        $fields = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string', 'max:1000000'],
            'status' => ['sometimes', Rule::in(['draft'])],
            'published_at' => ['prohibited'],
        ]);
        $fields['status'] = 'draft';
        $fields['content'] = $sanitizer->clean($fields['content'] ?? null);

        $post = DB::transaction(function () use ($fields, $client) {
            $post = Post::create($fields);
            activity('post.created', "API app {$client->name} created a draft.", $post, ['api_client_id' => $client->id]);

            return $post;
        });

        return $this->data($post->only(['id', 'title', 'slug', 'status', 'published_at']), status: 201);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $post = Post::published()
            ->with(['category', 'author', 'tags'])
            ->where('slug', $slug)
            ->first();

        if (! $post) {
            return $this->fail('No such post.', 404, 'not_found');
        }

        // The same lock-free counter the web page uses. A read from an app is
        // a read.
        Post::whereKey($post->id)->increment('views');

        return $this->data(Resource::post($post, full: true) + [
            'related' => Post::published()
                ->where('id', '!=', $post->id)
                ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
                ->orderByDesc('published_at')
                ->limit(3)
                ->get()
                ->map(fn (Post $related) => Resource::post($related))
                ->all(),
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::blog()
            ->active()
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $category) => Resource::category($category) + [
                'post_count' => (int) $category->posts_count,
            ]);

        return $this->data($categories->all());
    }

    public function tags(): JsonResponse
    {
        $tags = Tag::orderBy('name')->get()->map(fn (Tag $tag) => Resource::tag($tag));

        return $this->data($tags->all());
    }

    // Comments --------------------------------------------------------------

    public function comments(Request $request, string $slug): JsonResponse
    {
        $post = Post::published()->where('slug', $slug)->first();

        if (! $post) {
            return $this->fail('No such post.', 404, 'not_found');
        }

        $comments = $post->approvedComments()
            ->with('replies')
            ->get()
            ->map(fn (Comment $comment) => Resource::comment($comment) + [
                'replies' => $comment->replies
                    ->where('status', 'approved')
                    ->map(fn (Comment $reply) => Resource::comment($reply))
                    ->values()
                    ->all(),
            ]);

        return $this->data($comments->values()->all());
    }

    public function storeComment(Request $request, string $slug): JsonResponse
    {
        $post = Post::published()->where('slug', $slug)->first();

        if (! $post) {
            return $this->fail('No such post.', 404, 'not_found');
        }

        if (! $post->allow_comments) {
            return $this->fail('Comments are closed on this post.', 403, 'comments_closed');
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:3000'],
            'parent_id' => ['nullable', 'exists:comments,id'],
            'author_name' => [Rule::requiredIf(! $request->user()), 'nullable', 'string', 'max:120'],
            'author_email' => [Rule::requiredIf(! $request->user()), 'nullable', 'email', 'max:190'],
        ]);

        $post->comments()->create([
            'user_id' => $request->user()?->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'author_name' => $request->user()?->name ?? $validated['author_name'],
            'author_email' => $request->user()?->email ?? $validated['author_email'],
            'body' => $validated['body'],
            // Held for moderation, exactly as on the website. An API that
            // published comments straight away would be a spam pipe.
            'status' => 'pending',
            'ip_address' => $request->ip(),
        ]);

        return $this->message('Thanks. Your comment will appear once it has been approved.', status: 201);
    }
}
