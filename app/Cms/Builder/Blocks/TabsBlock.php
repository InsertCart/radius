<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class TabsBlock extends Block
{
    public static function type(): string
    {
        return 'tabs';
    }

    public static function name(): string
    {
        return 'Tabs';
    }

    public static function icon(): string
    {
        return 'tabs';
    }

    public static function category(): string
    {
        return 'content';
    }

    public static function order(): int
    {
        return 21;
    }

    public static function controls(): array
    {
        return [
            Control::repeater('items', 'Tabs', [
                Control::text('title', 'Tab label')->default('Tab'),
                Control::richtext('content', 'Content')->default('<p>Tab content.</p>'),
                Control::icon('icon', 'Icon'),
            ])->default([
                ['title' => 'First tab', 'content' => '<p>Content of the first tab.</p>'],
                ['title' => 'Second tab', 'content' => '<p>Content of the second tab.</p>'],
            ]),

            Control::choose('orientation', 'Layout', [
                'horizontal' => ['label' => 'Horizontal', 'icon' => 'align-right'],
                'vertical' => ['label' => 'Vertical', 'icon' => 'align-bottom'],
            ])->default('horizontal'),

            Control::color('tab_color', 'Tab label colour')
                ->tab(Control::TAB_STYLE)->section('Tabs')
                ->selector('{{WRAPPER}} .cb-tabs__tab', 'color'),

            Control::color('active_color', 'Active tab colour')
                ->tab(Control::TAB_STYLE)->section('Tabs')
                ->default('var(--cb-color-primary, #2563eb)')
                ->selector('{{WRAPPER}} .cb-tabs__tab[aria-selected="true"]', 'color')
                ->selector('{{WRAPPER}} .cb-tabs__tab[aria-selected="true"]', 'border-color'),

            Control::typography('tab_typography', 'Tab typography')
                ->tab(Control::TAB_STYLE)->section('Tabs')
                ->selector('{{WRAPPER}} .cb-tabs__tab', 'typography'),

            Control::dimensions('panel_padding', 'Content padding')
                ->tab(Control::TAB_STYLE)->section('Content')
                ->selector('{{WRAPPER}} .cb-tabs__panel', 'padding'),
        ];
    }
}
