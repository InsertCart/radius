<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;
use App\Cms\Shop\CartService;

/**
 * The working cart: line items, quantities, coupon field and totals.
 *
 * This is what makes the cart page safe to redesign. Rather than replacing the
 * page and hoping the admin rebuilds the functionality, the function itself is
 * a widget they arrange around. Everything it needs it fetches for itself, so
 * it works wherever it is dropped.
 */
class CartBlock extends Block
{
    public static function type(): string
    {
        return 'cart';
    }

    public static function name(): string
    {
        return 'Cart';
    }

    public static function icon(): string
    {
        return 'cart';
    }

    public static function category(): string
    {
        return 'shop';
    }

    public static function order(): int
    {
        return 10;
    }

    public static function requiresModule(): ?string
    {
        return 'shop';
    }

    public static function keywords(): array
    {
        return ['basket', 'items', 'totals', 'coupon'];
    }

    public static function controls(): array
    {
        return [
            Control::notice('about', 'This is the working cart')
                ->help('Line items, quantities, coupons and totals. Keep one of these on your cart page or shoppers will not be able to review their basket.'),

            Control::toggle('show_images', 'Show product images')->default(true),
            Control::toggle('show_coupon', 'Show the coupon field')->default(true),
            Control::toggle('show_continue', 'Show "continue shopping"')->default(true),

            Control::text('empty_text', 'Message when the cart is empty')
                ->default('Your cart is empty.'),
            Control::text('empty_button', 'Empty-cart button label')
                ->default('Start shopping'),
            Control::text('checkout_text', 'Checkout button label')
                ->default('Proceed to checkout'),

            // Style ------------------------------------------------------
            Control::color('total_color', 'Total colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-cart__total', 'color'),

            Control::color('button_bg', 'Checkout button')
                ->tab(Control::TAB_STYLE)
                ->default('var(--cb-color-primary, #2563eb)')
                ->selector('{{WRAPPER}} .cb-cart__checkout', 'background-color'),

            Control::dimensions('radius', 'Summary corners')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-cart__summary', 'border-radius'),
        ];
    }

    public function data(array $settings, array $context = []): array
    {
        $cart = app(CartService::class);

        // The editor has no shopper session, so a sample basket keeps the
        // widget visible while it is being designed.
        if (($context['editing'] ?? false) && $cart->isEmpty()) {
            return [
                'cart' => $cart->cart(),
                'summary' => $cart->summary(),
                'issues' => [],
                'sample' => true,
            ];
        }

        return [
            'cart' => $cart->cart(),
            'summary' => $cart->summary(),
            'issues' => $cart->validationIssues(),
            'sample' => false,
        ];
    }
}
