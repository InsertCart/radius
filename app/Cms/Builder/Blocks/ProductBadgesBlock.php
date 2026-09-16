<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

/** The row of reassurances near the buy box: genuine, secure, delivered. */
class ProductBadgesBlock extends ProductBlock
{
    public static function type(): string
    {
        return 'product-badges';
    }

    public static function name(): string
    {
        return 'Trust badges';
    }

    public static function icon(): string
    {
        return 'shield';
    }

    public static function order(): int
    {
        return 50;
    }

    public static function keywords(): array
    {
        return ['icons', 'features', 'guarantee', 'delivery', 'secure'];
    }

    public static function controls(): array
    {
        return [
            Control::repeater('items', 'Badges', [
                Control::icon('icon', 'Icon')->default('check'),
                Control::text('label', 'Label')->default('Badge'),
            ])->default([
                ['icon' => 'award', 'label' => 'Genuine products'],
                ['icon' => 'refresh', 'label' => 'Recyclable packaging'],
                ['icon' => 'shield', 'label' => 'Secure payments'],
                ['icon' => 'truck', 'label' => 'Tracked delivery'],
            ]),

            Control::select('columns', 'Per row', ['2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6'])
                ->default('4'),

            Control::color('ring_bg', 'Icon background')
                ->tab(Control::TAB_STYLE)
                ->default('var(--cb-color-accent, #f59e0b)')
                ->selector('{{WRAPPER}} .cb-badges__ring', 'background-color'),
            Control::color('icon_color', 'Icon colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-badges__ring', 'color'),
            Control::color('label_color', 'Label colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-badges__label', 'color'),
        ];
    }

    /** The badges are the same on every product, so no product is needed. */
    public function render(array $settings, array $context = []): string
    {
        return Block::render($settings, $context);
    }
}
