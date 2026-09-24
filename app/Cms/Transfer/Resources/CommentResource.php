<?php

namespace App\Cms\Transfer\Resources;

use App\Cms\Transfer\ExportContext;
use App\Cms\Transfer\ImportContext;
use App\Cms\Transfer\ImportReport;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Post comments, including their threading.
 *
 * Each record carries the id it had on the old site purely so replies can find
 * their parent; nothing else uses it, and it is never written to this
 * database. A reply whose parent did not come across is imported as a
 * top-level comment rather than dropped - the words are still somebody's.
 *
 * Bodies are stored as text and escaped by the theme, so they are not run
 * through the HTML sanitiser: doing so would mangle a perfectly innocent
 * comment that happened to mention <div>.
 */
class CommentResource extends TransferResource
{
    /** Old id => new id, for this run only. */
    private array $map = [];

    public function key(): string
    {
        return 'comments';
    }

    public function label(): string
    {
        return 'Comments';
    }

    public function modelClass(): string
    {
        return Comment::class;
    }

    public function module(): ?string
    {
        return 'blog';
    }

    public function hint(): string
    {
        return 'Reader comments on posts, with their replies and moderation state.';
    }

    protected function baseQuery(): Builder
    {
        return Comment::query()->with('post')->whereHas('post')->orderBy('id');
    }

    public function beforeImport(ImportContext $context): void
    {
        $this->map = [];
    }

    public function record(Model $model, ExportContext $context): array
    {
        /** @var Comment $model */
        return array_filter([
            'key' => $model->id,
            'parent' => $model->parent_id,
            'post' => $model->post?->slug,
            'author_name' => $model->displayName(),
            'author_email' => $model->user?->email ?: $model->author_email,
            'body' => $model->body,
            'status' => $model->status,
        ] + $this->timestamps($model), fn ($value) => $value !== null);
    }

    public function import(array $record, ImportContext $context): string
    {
        $post = filled($record['post'] ?? null) ? Post::where('slug', $record['post'])->first() : null;

        if (! $post || blank($record['body'] ?? null)) {
            return ImportReport::SKIPPED;
        }

        $body = (string) $record['body'];

        // There is no natural key for a comment, so "the same comment" means
        // the same words by the same person under the same post. That is good
        // enough to make re-running an import safe, which is the point.
        $existing = Comment::where('post_id', $post->id)
            ->where('body', $body)
            ->where('author_email', $record['author_email'] ?? null)
            ->first();

        if ($existing) {
            $this->remember($record, $existing->id);

            return ImportReport::SKIPPED;
        }

        $comment = new Comment([
            'post_id' => $post->id,
            'parent_id' => $this->parent($record),
            'author_name' => $record['author_name'] ?? null,
            'author_email' => $record['author_email'] ?? null,
            'body' => $body,
            'status' => in_array($record['status'] ?? null, ['pending', 'approved', 'spam'], true)
                ? $record['status']
                : 'pending',
        ]);

        $this->applyTimestamps($comment, $record);
        $comment->save();

        $this->remember($record, $comment->id);

        return ImportReport::CREATED;
    }

    private function parent(array $record): ?int
    {
        $parent = $record['parent'] ?? null;

        return $parent !== null ? ($this->map[(string) $parent] ?? null) : null;
    }

    private function remember(array $record, int $id): void
    {
        if (isset($record['key'])) {
            $this->map[(string) $record['key']] = $id;
        }
    }

    public function csvColumns(): array
    {
        return [
            'post' => 'Post',
            'author_name' => 'Author',
            'author_email' => 'Email',
            'status' => 'Status',
            'body' => 'Comment',
            'created_at' => 'Posted',
        ];
    }
}
