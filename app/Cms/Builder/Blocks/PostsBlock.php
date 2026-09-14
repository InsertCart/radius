<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;
use App\Models\Category;
use App\Models\Post;

/**
 * A grid or list of blog posts, queried live rather than pasted in, so the
 * section keeps itself up to date as new posts are published.
 */
class PostsBlock extends Block
{
    public static function type(): string
    {
        return 'posts';
    }

    public static function name(): string
    {
        return 'Post grid';
    }

    public static function icon(): string
    {
        return 'grid';
    }

    public static function category(): string
    {
        return 'content';
    }

    public static function order(): int
    {
        return 10;
    }

    public static function requiresModule(): ?string
    {
        return 'blog';
    }

    public static function keywords(): array
    {
        return ['blog', 'articles', 'news', 'latest'];
    }

    public static function controls(): array
    {
        return [
            Control::select('layout', 'Layout', [
                'grid' => 'Grid', 'list' => 'List', 'minimal' => 'Minimal list',
            ])->default('grid'),

            Control::slider('columns', 'Columns')
                ->min(1)->max(4)->units([''])
                ->default(['size' => 3, 'unit' => ''])
                ->responsive()
                ->when('layout', 'grid')
                ->selector('{{WRAPPER}} .cb-posts--grid', 'grid-template-columns', 'repeat({{VALUE}}, minmax(0, 1fr))'),

            Control::number('limit', 'How many posts')->default(6)->min(1)->max(24),

            Control::source('category', 'Category', 'blog_categories')
                ->help('Leave empty to show posts from every category.'),

            Control::select('order', 'Order by', [
                'latest' => 'Newest first',
                'oldest' => 'Oldest first',
                'popular' => 'Most viewed',
                'featured' => 'Featured first',
            ])->default('latest'),

            Control::toggle('show_image', 'Show featured image')->default(true),
            Control::toggle('show_category', 'Show category')->default(true),
            Control::toggle('show_date', 'Show date')->default(true),
            Control::toggle('show_excerpt', 'Show excerpt')->default(true),
            Control::toggle('show_author', 'Show author'),

            Control::text('empty_text', 'Message when empty')
                ->default('No posts published yet.'),

            // Style ------------------------------------------------------
            Control::slider('gap', 'Gap between posts')
                ->min(0)->max(80)->units(['px'])
                ->default(['size' => 24, 'unit' => 'px'])
                ->responsive()
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-posts', 'gap'),

            Control::color('title_color', 'Title colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-post-card__title', 'color'),

            Control::typography('title_typography', 'Title typography')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-post-card__title', 'typography'),

            Control::dimensions('card_radius', 'Card corners')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-post-card', 'border-radius'),
        ];
    }

    public function data(array $settings, array $context = []): array
    {
        $query = Post::published()->with(['category', 'author']);

        if (filled($settings['category'] ?? null)) {
            $query->where('category_id', (int) $settings['category']);
        }

        match ($settings['order'] ?? 'latest') {
            'oldest' => $query->orderBy('published_at'),
            'popular' => $query->orderByDesc('views'),
            'featured' => $query->orderByDesc('is_featured')->orderByDesc('published_at'),
            default => $query->orderByDesc('published_at'),
        };

        return [
            'posts' => $query->limit((int) ($settings['limit'] ?? 6))->get(),
        ];
    }

    /** Categories offered by the "category" source control. */
    public static function sourceOptions(): array
    {
        return Category::blog()->orderBy('name')->pluck('name', 'id')->all();
    }
}
