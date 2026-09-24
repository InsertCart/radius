<?php

namespace App\Cms\Transfer\Resources;

use App\Cms\Support\HtmlSanitizer;
use App\Cms\Transfer\ExportContext;
use App\Cms\Transfer\ImportContext;
use App\Cms\Transfer\ImportReport;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Blog posts, with their category, tags, author and builder layout.
 *
 * A post carries its category and tags as names rather than ids, and they are
 * created on the way in if this site has not got them. Importing "the design
 * blog" into an empty site should produce a working blog, not eight hundred
 * uncategorised posts.
 */
class PostResource extends TransferResource
{
    public function __construct(private HtmlSanitizer $sanitizer) {}

    public function key(): string
    {
        return 'posts';
    }

    public function label(): string
    {
        return 'Posts';
    }

    public function modelClass(): string
    {
        return Post::class;
    }

    public function module(): ?string
    {
        return 'blog';
    }

    public function hint(): string
    {
        return 'Blog posts with their category, tags, author and featured image.';
    }

    protected function hasStatus(): bool
    {
        return true;
    }

    /** A date range on posts means when they were published, not filed. */
    protected function dateColumn(): ?string
    {
        return 'published_at';
    }

    protected function baseQuery(): Builder
    {
        return Post::query()->with(['category', 'author', 'tags']);
    }

    public function record(Model $model, ExportContext $context): array
    {
        /** @var Post $model */
        return array_filter([
            'title' => $model->title,
            'slug' => $model->slug,
            'excerpt' => $model->excerpt,
            'content' => $model->rawContent(),
            'featured_image' => $context->file($model->featured_image),
            'status' => $model->status,
            'published_at' => $model->published_at?->toIso8601String(),
            'is_featured' => (bool) $model->is_featured,
            'allow_comments' => (bool) $model->allow_comments,
            'category' => $model->category ? [
                'slug' => $model->category->slug,
                'name' => $model->category->name,
            ] : null,
            'tags' => $model->tags->map(fn (Tag $tag) => ['slug' => $tag->slug, 'name' => $tag->name])->all(),
            'author' => $model->author ? [
                'name' => $model->author->name,
                'email' => $model->author->email,
            ] : null,
            'seo' => $this->seo($model, $context),
            'layout' => $this->layout($model, $context),
        ] + $this->timestamps($model), fn ($value) => $value !== null);
    }

    public function import(array $record, ImportContext $context): string
    {
        $slug = $record['slug'] ?? null;

        if (blank($slug) && blank($record['title'] ?? null)) {
            return ImportReport::SKIPPED;
        }

        $slug = $slug ?: Str::slug($record['title']);
        $existing = Post::where('slug', $slug)->first();

        if ($existing && ! $context->options->updatesExisting()) {
            return ImportReport::SKIPPED;
        }

        $post = $existing ?: new Post(['slug' => $slug]);

        $post->fill(array_merge([
            'title' => $record['title'] ?? $slug,
            'excerpt' => $record['excerpt'] ?? null,
            'content' => $this->sanitizer->clean($context->rewrite($record['content'] ?? null)),
            'featured_image' => $context->mediaPath($record['featured_image'] ?? null),
            'status' => $context->options->statusFor($record['status'] ?? null) ?? 'draft',
            'published_at' => $this->date($record['published_at'] ?? null),
            'is_featured' => (bool) ($record['is_featured'] ?? false),
            'allow_comments' => (bool) ($record['allow_comments'] ?? true),
            'category_id' => $this->category($record['category'] ?? null),
        ], $this->seoAttributes($record, $context)));

        $post->author_id = $context->author($record['author'] ?? null) ?? $post->author_id;

        $this->applyTimestamps($post, $record);
        $post->save();

        $post->tags()->sync($this->tags($record['tags'] ?? []));

        $this->applyLayout($post, $record, $context);

        return $this->outcome(! $existing);
    }

    /** The blog category this post belongs in, created if the site has not got it. */
    private function category(mixed $category): ?int
    {
        // Accepts both shapes: the object this exporter writes, and a bare
        // slug from a file somebody assembled by hand.
        $slug = is_array($category) ? ($category['slug'] ?? null) : $category;
        $name = is_array($category) ? ($category['name'] ?? null) : null;

        if (blank($slug) && blank($name)) {
            return null;
        }

        $slug = $slug ? Str::slug($slug) : Str::slug($name);

        $model = Category::firstOrCreate(
            ['type' => Category::TYPE_BLOG, 'slug' => $slug],
            ['name' => $name ?: Str::headline($slug)]
        );

        return $model->id;
    }

    /** @return int[] */
    private function tags(array $tags): array
    {
        $ids = [];

        foreach ($tags as $tag) {
            $slug = is_array($tag) ? ($tag['slug'] ?? null) : $tag;
            $name = is_array($tag) ? ($tag['name'] ?? null) : $tag;

            if (blank($slug) && blank($name)) {
                continue;
            }

            $slug = $slug ? Str::slug($slug) : Str::slug($name);

            $ids[] = Tag::firstOrCreate(['slug' => $slug], ['name' => $name ?: Str::headline($slug)])->id;
        }

        return array_values(array_unique($ids));
    }

    public function csvColumns(): array
    {
        return [
            'title' => 'Title',
            'slug' => 'Slug',
            'status' => 'Status',
            'published_at' => 'Published',
            'category' => 'Category',
            'tags' => 'Tags',
            'author' => 'Author',
            'excerpt' => 'Excerpt',
            'featured_image' => 'Featured image',
            'is_featured' => 'Featured',
        ];
    }

    public function csvRow(array $record): array
    {
        // The nested pieces are flattened rather than dropped, because a
        // spreadsheet of posts with no category is not much of a report.
        $record['category'] = $record['category']['name'] ?? null;
        $record['tags'] = array_map(fn ($tag) => $tag['name'] ?? '', (array) ($record['tags'] ?? []));
        $record['author'] = $record['author']['name'] ?? null;

        return parent::csvRow($record);
    }
}
