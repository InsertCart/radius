<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class IconBlock extends Block
{
    public static function type(): string
    {
        return 'icon';
    }

    public static function name(): string
    {
        return 'Icon';
    }

    public static function icon(): string
    {
        return 'star';
    }

    public static function category(): string
    {
        return 'basic';
    }

    public static function order(): int
    {
        return 5;
    }

    public static function controls(): array
    {
        return [
            Control::icon('icon', 'Icon')->default('star'),
            Control::link('link', 'Link'),

            Control::select('shape', 'Shape', [
                'none' => 'None', 'circle' => 'Circle', 'square' => 'Rounded square',
            ])->default('none'),

            Control::choose('align', 'Alignment', [
                'flex-start' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'flex-end' => ['label' => 'Right', 'icon' => 'align-right'],
            ])->default('center')->responsive()
                ->selector('{{WRAPPER}}', 'justify-content'),

            Control::slider('size', 'Size')
                ->min(12)->max(200)->units(['px'])
                ->default(['size' => 40, 'unit' => 'px'])
                ->responsive()
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-icon svg', 'width')
                ->selector('{{WRAPPER}} .cb-icon svg', 'height'),

            Control::color('color', 'Colour')
                ->tab(Control::TAB_STYLE)
                ->default('var(--cb-color-primary, #2563eb)')
                ->selector('{{WRAPPER}} .cb-icon svg', 'color'),

            Control::color('bg_color', 'Background')
                ->tab(Control::TAB_STYLE)
                ->when('shape', ['circle', 'square'])
                ->selector('{{WRAPPER}} .cb-icon', 'background-color'),

            Control::slider('padding', 'Padding')
                ->min(0)->max(80)->units(['px'])
                ->default(['size' => 16, 'unit' => 'px'])
                ->when('shape', ['circle', 'square'])
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-icon', 'padding'),
        ];
    }
}
