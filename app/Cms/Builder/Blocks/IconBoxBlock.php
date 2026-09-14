<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class IconBoxBlock extends Block
{
    public static function type(): string
    {
        return 'icon-box';
    }

    public static function name(): string
    {
        return 'Icon box';
    }

    public static function icon(): string
    {
        return 'icon-box';
    }

    public static function category(): string
    {
        return 'content';
    }

    public static function order(): int
    {
        return 1;
    }

    public static function keywords(): array
    {
        return ['feature', 'service', 'card'];
    }

    public static function controls(): array
    {
        return [
            Control::icon('icon', 'Icon')->default('star'),
            Control::text('title', 'Title')->default('Feature title'),
            Control::textarea('description', 'Description')
                ->default('Explain this feature in a sentence or two.'),
            Control::link('link', 'Link'),

            Control::choose('position', 'Icon position', [
                'top' => ['label' => 'Top', 'icon' => 'align-top'],
                'left' => ['label' => 'Left', 'icon' => 'align-left'],
                'right' => ['label' => 'Right', 'icon' => 'align-right'],
            ])->default('top'),

            Control::choose('align', 'Text alignment', [
                'left' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'right' => ['label' => 'Right', 'icon' => 'align-right'],
            ])->default('center')->responsive()
                ->selector('{{WRAPPER}} .cb-icon-box', 'text-align'),

            // Style ------------------------------------------------------
            Control::slider('icon_size', 'Icon size')
                ->min(16)->max(120)->units(['px'])
                ->default(['size' => 44, 'unit' => 'px'])
                ->tab(Control::TAB_STYLE)->section('Icon')
                ->selector('{{WRAPPER}} .cb-icon-box__icon svg', 'width')
                ->selector('{{WRAPPER}} .cb-icon-box__icon svg', 'height'),

            Control::color('icon_color', 'Icon colour')
                ->tab(Control::TAB_STYLE)->section('Icon')
                ->default('var(--cb-color-primary, #2563eb)')
                ->selector('{{WRAPPER}} .cb-icon-box__icon svg', 'color'),

            Control::color('title_color', 'Title colour')
                ->tab(Control::TAB_STYLE)->section('Title')
                ->selector('{{WRAPPER}} .cb-icon-box__title', 'color'),

            Control::typography('title_typography', 'Title typography')
                ->tab(Control::TAB_STYLE)->section('Title')
                ->selector('{{WRAPPER}} .cb-icon-box__title', 'typography'),

            Control::color('text_color', 'Description colour')
                ->tab(Control::TAB_STYLE)->section('Description')
                ->selector('{{WRAPPER}} .cb-icon-box__text', 'color'),

            Control::typography('text_typography', 'Description typography')
                ->tab(Control::TAB_STYLE)->section('Description')
                ->selector('{{WRAPPER}} .cb-icon-box__text', 'typography'),
        ];
    }
}
