<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class ButtonBlock extends Block
{
    public static function type(): string
    {
        return 'button';
    }

    public static function name(): string
    {
        return 'Button';
    }

    public static function icon(): string
    {
        return 'button';
    }

    public static function order(): int
    {
        return 3;
    }

    public static function keywords(): array
    {
        return ['link', 'cta', 'call to action'];
    }

    public static function controls(): array
    {
        return [
            Control::text('text', 'Label')->default('Click here'),
            Control::link('link', 'Link'),
            Control::icon('icon', 'Icon'),

            Control::choose('icon_position', 'Icon position', [
                'before' => ['label' => 'Before', 'icon' => 'align-left'],
                'after' => ['label' => 'After', 'icon' => 'align-right'],
            ])->default('after')->when('icon', '!empty'),

            Control::select('size', 'Size', [
                'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra large',
            ])->default('md'),

            Control::toggle('full_width', 'Full width'),

            Control::choose('align', 'Alignment', [
                'flex-start' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'flex-end' => ['label' => 'Right', 'icon' => 'align-right'],
            ])->default('flex-start')->responsive()
                ->selector('{{WRAPPER}}', 'justify-content'),

            // Style ------------------------------------------------------
            Control::color('bg_color', 'Background')
                ->tab(Control::TAB_STYLE)->section('Normal')
                ->default('var(--cb-color-primary, #2563eb)')
                ->selector('{{WRAPPER}} .cb-button', 'background-color'),

            Control::color('text_color', 'Text colour')
                ->tab(Control::TAB_STYLE)->section('Normal')
                ->default('#ffffff')
                ->selector('{{WRAPPER}} .cb-button', 'color'),

            Control::typography('typography', 'Typography')
                ->tab(Control::TAB_STYLE)->section('Normal')
                ->selector('{{WRAPPER}} .cb-button', 'typography'),

            Control::dimensions('padding', 'Padding')
                ->tab(Control::TAB_STYLE)->section('Normal')
                ->responsive()
                ->selector('{{WRAPPER}} .cb-button', 'padding'),

            Control::dimensions('radius', 'Rounded corners')
                ->tab(Control::TAB_STYLE)->section('Normal')
                ->selector('{{WRAPPER}} .cb-button', 'border-radius'),

            Control::border('border', 'Border')
                ->tab(Control::TAB_STYLE)->section('Normal')
                ->selector('{{WRAPPER}} .cb-button', 'border'),

            Control::shadow('shadow', 'Shadow')
                ->tab(Control::TAB_STYLE)->section('Normal')
                ->selector('{{WRAPPER}} .cb-button', 'box-shadow'),

            Control::color('hover_bg', 'Background on hover')
                ->tab(Control::TAB_STYLE)->section('Hover')
                ->selector('{{WRAPPER}} .cb-button:hover', 'background-color'),

            Control::color('hover_text', 'Text on hover')
                ->tab(Control::TAB_STYLE)->section('Hover')
                ->selector('{{WRAPPER}} .cb-button:hover', 'color'),
        ];
    }
}
