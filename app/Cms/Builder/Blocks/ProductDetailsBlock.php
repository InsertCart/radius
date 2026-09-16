<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

/** SKU, categories, weight and similar facts, as a definition list. */
class ProductDetailsBlock extends ProductBlock
{
    public static function type(): string
    {
        return 'product-details';
    }

    public static function name(): string
    {
        return 'Product details';
    }

    public static function icon(): string
    {
        return 'clipboard';
    }

    public static function order(): int
    {
        return 65;
    }

    public static function keywords(): array
    {
        return ['sku', 'weight', 'dimensions', 'specification'];
    }

    public static function controls(): array
    {
        return [
            Control::text('heading', 'Heading')->default('Product details'),
            Control::select('display', 'Display', [
                'open' => 'Collapsible, open',
                'closed' => 'Collapsible, closed',
                'plain' => 'Always shown',
            ])->default('closed'),

            Control::toggle('show_sku', 'SKU')->default(true)->section('Rows'),
            Control::toggle('show_categories', 'Categories')->default(true)->section('Rows'),
            Control::toggle('show_weight', 'Weight')->default(true)->section('Rows'),
            Control::toggle('show_dimensions', 'Dimensions')->default(true)->section('Rows'),
            Control::toggle('show_delivery', 'Delivery')->default(true)->section('Rows'),

            Control::color('heading_color', 'Heading colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-pinfo__title', 'color'),
            Control::color('text_color', 'Text colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-pinfo__body', 'color'),
        ];
    }
}
