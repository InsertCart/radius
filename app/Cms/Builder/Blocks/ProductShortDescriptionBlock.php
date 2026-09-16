<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class ProductShortDescriptionBlock extends ProductBlock
{
    public static function type(): string
    {
        return 'product-short-description';
    }

    public static function name(): string
    {
        return 'Short description';
    }

    public static function icon(): string
    {
        return 'text';
    }

    public static function order(): int
    {
        return 35;
    }

    public static function controls(): array
    {
        return [
            Control::color('color', 'Text colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-pshort', 'color'),

            Control::typography('typography', 'Typography')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-pshort', 'typography'),
        ];
    }
}
