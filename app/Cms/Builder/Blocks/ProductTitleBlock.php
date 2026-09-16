<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class ProductTitleBlock extends ProductBlock
{
    public static function type(): string
    {
        return 'product-title';
    }

    public static function name(): string
    {
        return 'Product title';
    }

    public static function icon(): string
    {
        return 'heading';
    }

    public static function order(): int
    {
        return 20;
    }

    public static function controls(): array
    {
        return [
            Control::select('tag', 'HTML tag', ['h1' => 'H1', 'h2' => 'H2', 'div' => 'Div'])->default('h1'),
            Control::toggle('show_category', 'Show the category link')->default(true),

            Control::choose('align', 'Alignment', [
                'left' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'right' => ['label' => 'Right', 'icon' => 'align-right'],
            ])->default('left')->responsive()
                ->selector('{{WRAPPER}}', 'text-align'),

            Control::color('color', 'Title colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-ptitle', 'color'),

            Control::typography('typography', 'Title typography')
                ->tab(Control::TAB_STYLE)
                ->responsive()
                ->selector('{{WRAPPER}} .cb-ptitle', 'typography'),

            Control::color('category_color', 'Category colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-ptitle__category', 'color'),
        ];
    }
}
