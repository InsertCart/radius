<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;
use App\Models\Product;

/** Other products from the same categories, topped up from the rest of the shop. */
class ProductRelatedBlock extends ProductBlock
{
    public static function type(): string
    {
        return 'product-related';
    }

    public static function name(): string
    {
        return 'Related products';
    }

    public static function icon(): string
    {
        return 'grid';
    }

    public static function order(): int
    {
        return 80;
    }

    public static function keywords(): array
    {
        return ['you may also like', 'similar', 'upsell'];
    }

    public static function controls(): array
    {
        return [
            Control::text('heading', 'Heading')->default('You may also like'),
            Control::number('count', 'How many')->default(4)->min(1)->max(12),
            Control::toggle('show_price', 'Show price')->default(true),
            Control::toggle('show_rating', 'Show rating')->default(true),
            Control::toggle('show_cart_button', 'Show add to cart button')->default(false),

            Control::color('heading_color', 'Heading colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-related__heading', 'color'),
        ];
    }

    protected function productData(Product $product, array $settings, array $context): array
    {
        $count = max(1, min(12, (int) ($settings['count'] ?? 4)));
        $categoryIds = $product->categories->pluck('id');

        $related = Product::published()
            ->whereKeyNot($product->id)
            ->when($categoryIds->isNotEmpty(), fn ($q) => $q->whereHas('categories',
                fn ($c) => $c->whereIn('categories.id', $categoryIds)))
            ->orderByDesc('is_featured')
            ->latest()
            ->limit($count)
            ->get();

        if ($related->count() < $count) {
            $related = $related->concat(
                Product::published()
                    ->whereKeyNot($product->id)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->latest()
                    ->limit($count - $related->count())
                    ->get()
            );
        }

        return ['related' => $related];
    }
}
