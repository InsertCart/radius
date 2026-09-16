<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class ProductBreadcrumbsBlock extends ProductBlock
{
    public static function type(): string
    {
        return 'product-breadcrumbs';
    }

    public static function name(): string
    {
        return 'Breadcrumbs';
    }

    public static function icon(): string
    {
        return 'chevron-right';
    }

    public static function order(): int
    {
        return 5;
    }

    public static function controls(): array
    {
        return [
            Control::text('home_label', 'Home label')->default('Home'),
            Control::text('shop_label', 'Shop label')->default('Shop'),

            Control::color('color', 'Link colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-breadcrumbs', 'color'),
        ];
    }
}
