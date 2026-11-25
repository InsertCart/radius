<?php

namespace App\Cms\Mcp;

use App\Cms\Support\HtmlSanitizer;
use App\Models\McpToken;
use App\Models\Post;
use BadMethodCallException;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PostTools
{
    private const SUMMARY = ['id', 'title', 'slug', 'status', 'published_at', 'updated_at'];

    public function __construct(private HtmlSanitizer $sanitizer) {}

    public function definitions(bool $canWrite): array
    {
        $id = ['type' => 'integer', 'minimum' => 1];
        $fields = [
            'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190],
            'excerpt' => ['type' => 'string', 'maxLength' => 1000],
            'content' => ['type' => 'string', 'description' => 'Post body as HTML.'],
            'status' => ['type' => 'string', 'enum' => ['draft', 'published']],
        ];
        $tools = [
            $this->tool('list_posts', 'Find blog posts, newest ID first. Supply before_id from the previous response to continue.', [
                'search' => ['type' => 'string', 'maxLength' => 190],
                'before_id' => $id,
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ]),
            $this->tool('get_post', 'Read the post body, excerpt and publication details by ID.', ['id' => $id], ['id']),
            $this->tool('analyze_posts', 'Get lifetime views and post counts by status, or pass an ID for views, reading time and SEO metadata for one post.', ['id' => $id]),
        ];
        if ($canWrite) {
            $tools[] = $this->tool('create_post', 'Create a draft by default. Set status to published to publish it.', $fields, ['title']);
            $tools[] = $this->tool('edit_post', 'Update only the supplied fields of an existing post. Omitted fields keep their values.', ['id' => $id] + $fields, ['id']);
        }

        return $tools;
    }

    private function tool(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'inputSchema' => [
                'type' => 'object', 'properties' => $properties,
                'required' => $required, 'additionalProperties' => false,
            ],
        ];
    }

    public function call(string $name, array $args, McpToken $token): array
    {
        $tools = array_column($this->definitions($token->can_write), null, 'name');
        if (! isset($tools[$name])) {
            throw new BadMethodCallException('Unknown or unavailable tool.');
        }
        $this->validateArguments($tools[$name]['inputSchema'], $args);

        return match ($name) {
            'list_posts' => $this->listPosts($args),
            'get_post' => $this->post($args['id'])->only([...self::SUMMARY, 'excerpt', 'content']),
            'analyze_posts' => $this->analyze($args['id'] ?? null),
            'create_post', 'edit_post' => $this->save($args, $token),
        };
    }

    private function validateArguments(array $schema, array $args): void
    {
        foreach ($schema['required'] as $key) {
            if (! array_key_exists($key, $args)) {
                throw new DomainException("Missing field: {$key}.");
            }
        }
        foreach ($args as $key => $value) {
            $rule = $schema['properties'][$key] ?? null;
            if ($rule === null) {
                throw new DomainException("Unknown field: {$key}.");
            }
            if ($rule['type'] === 'integer') {
                $valid = is_int($value) && $value >= $rule['minimum'] && $value <= ($rule['maximum'] ?? PHP_INT_MAX);
            } else {
                $valid = is_string($value)
                    && mb_strlen(trim($value)) >= ($rule['minLength'] ?? 0)
                    && mb_strlen($value) <= ($rule['maxLength'] ?? PHP_INT_MAX)
                    && (! isset($rule['enum']) || in_array($value, $rule['enum'], true));
            }
            if (! $valid) {
                throw new DomainException("Invalid value for {$key}. Check the tool's input schema.");
            }
        }
    }

    private function listPosts(array $args): array
    {
        $limit = $args['limit'] ?? 20;
        $posts = Post::query()->search($args['search'] ?? null)
            ->when(isset($args['before_id']), fn ($query) => $query->where('id', '<', $args['before_id']))
            ->orderByDesc('id')->limit($limit + 1)->get(self::SUMMARY);
        $page = $posts->take($limit);

        return ['posts' => $page->values()->toArray(), 'before_id' => $posts->count() > $limit ? $page->last()->id : null];
    }

    private function post(int $id): Post
    {
        return Post::find($id) ?? throw new DomainException('Post not found.');
    }

    private function analyze(?int $id): array
    {
        if ($id !== null) {
            return $this->post($id)->only([...self::SUMMARY, 'views', 'reading_minutes', 'meta_title', 'meta_description', 'noindex']);
        }
        $groups = Post::query()->select('status')
            ->selectRaw('COUNT(*) AS posts, SUM(views) AS views')
            ->groupBy('status')->orderBy('status')->get()->map(fn ($group) => [
                'status' => $group->status, 'posts' => (int) $group->posts, 'views' => (int) $group->views,
            ]);

        return ['posts' => $groups->sum('posts'), 'views' => $groups->sum('views'), 'by_status' => $groups->all()];
    }

    private function save(array $args, McpToken $token): array
    {
        $fields = Arr::except($args, 'id');
        if ($fields === []) {
            throw new DomainException('Supply at least one field to edit.');
        }
        if (array_key_exists('content', $fields)) {
            $fields['content'] = $this->sanitizer->clean($fields['content']);
        }

        return DB::transaction(function () use ($args, $fields, $token) {
            if (isset($args['id'])) {
                $post = Post::lockForUpdate()->find($args['id']) ?? throw new DomainException('Post not found.');
                $action = 'updated';
            } else {
                $post = new Post(['author_id' => $token->user_id, 'status' => 'draft']);
                $action = 'created';
            }
            // Publishing an old scheduled draft should make it visible immediately.
            if (($fields['status'] ?? null) === 'published' && $post->published_at?->isFuture()) {
                $post->published_at = now();
            }
            $post->fill($fields)->save();
            activity("post.{$action}", "MCP client {$token->name} {$action} the post.", $post, ['mcp_token_id' => $token->id]);

            return $post->only(self::SUMMARY);
        });
    }
}
