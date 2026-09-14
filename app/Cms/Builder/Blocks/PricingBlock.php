<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class PricingBlock extends Block
{
    public static function type(): string
    {
        return 'pricing';
    }

    public static function name(): string
    {
        return 'Pricing table';
    }

    public static function icon(): string
    {
        return 'pricing';
    }

    public static function category(): string
    {
        return 'content';
    }

    public static function order(): int
    {
        return 40;
    }

    public static function keywords(): array
    {
        return ['plan', 'package', 'tier', 'price'];
    }

    public static function controls(): array
    {
        return [
            Control::text('plan', 'Plan name')->default('Professional'),
            Control::text('price', 'Price')->default('49'),
            Control::text('currency', 'Currency symbol')->default('$'),
            Control::text('period', 'Billing period')->default('per month'),
            Control::textarea('description', 'Short description'),

            Control::repeater('features', 'Features', [
                Control::text('text', 'Feature')->default('Feature included'),
                Control::toggle('excluded', 'Show as not included'),
            ])->default([
                ['text' => 'Everything in Starter', 'excluded' => false],
                ['text' => 'Priority support', 'excluded' => false],
                ['text' => 'Custom integrations', 'excluded' => true],
            ]),

            Control::text('button_text', 'Button label')->default('Get started'),
            Control::link('button_link', 'Button link'),

            Control::toggle('featured', 'Highlight this plan')
                ->help('Adds a badge and a stronger border, for the plan you want people to pick.'),
            Control::text('badge_text', 'Badge text')->default('Most popular')->when('featured', true),

            // Style ------------------------------------------------------
            Control::color('bg_color', 'Background')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-pricing', 'background-color'),

            Control::border('border', 'Border')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-pricing', 'border'),

            Control::dimensions('radius', 'Rounded corners')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-pricing', 'border-radius'),

            Control::color('price_color', 'Price colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-pricing__price', 'color'),

            Control::shadow('shadow', 'Shadow')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-pricing', 'box-shadow'),
        ];
    }
}
