<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class ProductPriceBlock extends ProductBlock
{
    public static function type(): string
    {
        return 'product-price';
    }

    public static function name(): string
    {
        return 'Price';
    }

    public static function icon(): string
    {
        return 'tag';
    }

    public static function order(): int
    {
        return 30;
    }

    public static function keywords(): array
    {
        return ['sale', 'discount', 'cost'];
    }

    public static function controls(): array
    {
        return [
            Control::toggle('show_saving', 'Show the saving on sale items')->default(true),
            Control::text('saving_text', 'Saving label')->default(':amount off')
                ->help(':amount is replaced with the saving, :percent with the percentage.'),
            Control::toggle('show_tax_note', 'Show the tax note')->default(true),

            Control::color('color', 'Price colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-price__now', 'color'),

            Control::typography('typography', 'Price typography')
                ->tab(Control::TAB_STYLE)
                ->responsive()
                ->selector('{{WRAPPER}} .cb-price__now', 'typography'),

            Control::color('saving_bg', 'Saving badge')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-price__saving', 'background-color'),
        ];
    }
}
