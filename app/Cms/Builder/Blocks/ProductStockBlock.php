<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class ProductStockBlock extends ProductBlock
{
    public static function type(): string
    {
        return 'product-stock';
    }

    public static function name(): string
    {
        return 'Stock status';
    }

    public static function icon(): string
    {
        return 'package';
    }

    public static function order(): int
    {
        return 45;
    }

    public static function keywords(): array
    {
        return ['availability', 'in stock', 'inventory'];
    }

    public static function controls(): array
    {
        return [
            Control::text('in_stock_text', 'In stock')->default('In stock, ready to ship'),
            Control::text('low_stock_text', 'Low stock')->default('Only :count left')
                ->help(':count is replaced with the number left.'),
            Control::text('out_of_stock_text', 'Out of stock')->default('Currently unavailable'),
            Control::text('digital_text', 'Digital products')->default('Instant download after payment'),

            Control::color('in_color', 'In stock colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-stock--in', 'color'),
            Control::color('low_color', 'Low stock colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-stock--low', 'color'),
            Control::color('out_color', 'Out of stock colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-stock--out', 'color'),
        ];
    }
}
