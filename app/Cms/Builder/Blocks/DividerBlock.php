<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class DividerBlock extends Block
{
    public static function type(): string
    {
        return 'divider';
    }

    public static function name(): string
    {
        return 'Divider';
    }

    public static function icon(): string
    {
        return 'divider';
    }

    public static function category(): string
    {
        return 'layout';
    }

    public static function order(): int
    {
        return 2;
    }

    public static function keywords(): array
    {
        return ['line', 'separator', 'hr'];
    }

    public static function controls(): array
    {
        return [
            Control::select('style', 'Style', [
                'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double',
            ])->default('solid')
                ->selector('{{WRAPPER}} .cb-divider', 'border-top-style'),

            Control::slider('weight', 'Thickness')
                ->min(1)->max(20)->units(['px'])
                ->default(['size' => 1, 'unit' => 'px'])
                ->selector('{{WRAPPER}} .cb-divider', 'border-top-width'),

            Control::color('color', 'Colour')
                ->default('#e2e8f0')
                ->selector('{{WRAPPER}} .cb-divider', 'border-top-color'),

            Control::slider('width', 'Width')
                ->min(10)->max(100)->units(['%'])
                ->default(['size' => 100, 'unit' => '%'])
                ->responsive()
                ->selector('{{WRAPPER}} .cb-divider', 'width'),

            Control::choose('align', 'Alignment', [
                'flex-start' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'flex-end' => ['label' => 'Right', 'icon' => 'align-right'],
            ])->default('center')
                ->selector('{{WRAPPER}}', 'justify-content'),
        ];
    }
}
