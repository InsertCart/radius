<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

/**
 * The buy box: variant choice, quantity, Add to cart and Buy now.
 *
 * The product page cannot work without it, so the editor warns when a
 * template is missing it.
 */
class ProductAddToCartBlock extends ProductBlock
{
    public static function type(): string
    {
        return 'product-add-to-cart';
    }

    public static function name(): string
    {
        return 'Add to cart';
    }

    public static function icon(): string
    {
        return 'cart';
    }

    public static function order(): int
    {
        return 40;
    }

    public static function keywords(): array
    {
        return ['buy', 'buy now', 'basket', 'quantity', 'variant'];
    }

    public static function controls(): array
    {
        return [
            Control::notice('about', 'This is how shoppers buy')
                ->help('Keep one of these on the product page, or nobody can add the product to their cart.'),

            Control::toggle('show_quantity', 'Show the quantity picker')->default(true),
            Control::text('cart_label', 'Add to cart label')->default('Add to cart'),
            Control::icon('cart_icon', 'Add to cart icon')->default('bag'),

            Control::toggle('show_buy_now', 'Show a Buy now button')->default(true),
            Control::text('buy_now_label', 'Buy now label')->default('Buy now'),

            Control::text('variant_label', 'Option picker label')->default('Choose an option'),
            Control::text('sold_out_label', 'Out of stock label')->default('Out of stock'),

            Control::select('layout', 'Buttons', [
                'row' => 'Side by side',
                'stack' => 'Stacked',
            ])->default('row'),

            // Style ------------------------------------------------------
            Control::color('cart_bg', 'Add to cart background')
                ->tab(Control::TAB_STYLE)
                ->section('Add to cart')
                ->selector('{{WRAPPER}} .cb-buy__cart', 'background-color'),
            Control::color('cart_color', 'Add to cart text')
                ->tab(Control::TAB_STYLE)
                ->section('Add to cart')
                ->selector('{{WRAPPER}} .cb-buy__cart', 'color'),

            Control::color('now_bg', 'Buy now background')
                ->tab(Control::TAB_STYLE)
                ->section('Buy now')
                ->selector('{{WRAPPER}} .cb-buy__now', 'background-color'),
            Control::color('now_color', 'Buy now text')
                ->tab(Control::TAB_STYLE)
                ->section('Buy now')
                ->selector('{{WRAPPER}} .cb-buy__now', 'color'),
            Control::color('now_border', 'Buy now border')
                ->tab(Control::TAB_STYLE)
                ->section('Buy now')
                ->selector('{{WRAPPER}} .cb-buy__now', 'border-color'),

            Control::dimensions('radius', 'Button corners')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-buy__btn', 'border-radius'),
        ];
    }
}
