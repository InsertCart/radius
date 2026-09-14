<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;
use App\Models\Category;
use App\Models\Product;

class ProductsBlock extends Block
{
    public static function type(): string
    {
        return 'products';
    }

    public static function name(): string
    {
        return 'Product grid';
    }

    public static function icon(): string
    {
        return 'bag';
    }

    public static function category(): string
    {
        return 'shop';
    }

    public static function order(): int
    {
        return 1;
    }

    public static function requiresModule(): ?string
    {
        return 'shop';
    }

    public static function keywords(): array
    {
        return ['shop', 'store', 'catalogue', 'items'];
    }

    public static function controls(): array
    {
        return [
            Control::slider('columns', 'Columns')
                ->min(1)->max(5)->units([''])
                ->default(['size' => 4, 'unit' => ''])
                ->responsive()
                ->selector('{{WRAPPER}} .cb-products', 'grid-template-columns', 'repeat({{VALUE}}, minmax(0, 1fr))'),

            Control::number('limit', 'How many products')->default(8)->min(1)->max(24),

            Control::source('category', 'Category', 'shop_categories')
                ->help('Leave empty to show products from the whole shop.'),

            Control::select('order', 'Order by', [
                'latest' => 'Newest first',
                'price_asc' => 'Price: low to high',
                'price_desc' => 'Price: high to low',
                'popular' => 'Best selling',
                'rating' => 'Best rated',
                'featured' => 'Featured first',
            ])->default('featured'),

            Control::toggle('in_stock_only', 'Only show items in stock'),
            Control::toggle('show_price', 'Show price')->default(true),
            Control::toggle('show_rating', 'Show rating')->default(true),
            Control::toggle('show_cart_button', 'Show "add to cart"')->default(true),

            Control::text('empty_text', 'Message when empty')
                ->default('No products to show yet.'),

            Control::slider('gap', 'Gap between products')
                ->min(0)->max(80)->units(['px'])
                ->default(['size' => 20, 'unit' => 'px'])
                ->responsive()
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-products', 'gap'),

            Control::color('price_color', 'Price colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-product-card__price', 'color'),
        ];
    }

    public function data(array $settings, array $context = []): array
    {
        $query = Product::published();

        if (filled($settings['category'] ?? null)) {
            $query->whereHas('categories', fn ($c) => $c->where('categories.id', (int) $settings['category']));
        }

        if (! empty($settings['in_stock_only'])) {
            $query->inStock();
        }

        match ($settings['order'] ?? 'featured') {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'popular' => $query->orderByDesc('sold_count'),
            'rating' => $query->orderByDesc('rating'),
            'latest' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('is_featured')->orderByDesc('created_at'),
        };

        return [
            'products' => $query->limit((int) ($settings['limit'] ?? 8))->get(),
        ];
    }

    public static function sourceOptions(): array
    {
        return Category::shop()->orderBy('name')->pluck('name', 'id')->all();
    }
}
