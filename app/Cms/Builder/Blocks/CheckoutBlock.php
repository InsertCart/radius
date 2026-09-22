<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;
use App\Cms\Payments\PaymentManager;
use App\Cms\Shop\AddressBook;
use App\Cms\Shop\CartService;
use App\Cms\Shop\CheckoutFields;
use App\Cms\Shop\Countries;

/**
 * The working checkout: contact details, addresses, payment method and the
 * place-order button.
 *
 * Same reasoning as the cart widget - the function is the widget, so an admin
 * can redesign the page around it without any risk of building a checkout
 * that cannot take money.
 */
class CheckoutBlock extends Block
{
    public static function type(): string
    {
        return 'checkout';
    }

    public static function name(): string
    {
        return 'Checkout';
    }

    public static function icon(): string
    {
        return 'credit-card';
    }

    public static function category(): string
    {
        return 'shop';
    }

    public static function order(): int
    {
        return 11;
    }

    public static function requiresModule(): ?string
    {
        return 'shop';
    }

    public static function keywords(): array
    {
        return ['pay', 'order', 'payment', 'billing'];
    }

    public static function controls(): array
    {
        return [
            Control::notice('about', 'This is the working checkout')
                ->help('Addresses, payment method and the place-order button. Keep one of these on your checkout page or customers will not be able to pay.'),

            Control::toggle('show_notes', 'Ask for order notes')->default(true),
            Control::toggle('show_summary', 'Show the order summary')->default(true),
            Control::text('button_text', 'Place-order button label')->default('Place order'),

            Control::choose('layout', 'Layout', [
                'two-column' => ['label' => 'Two columns', 'icon' => 'grid'],
                'stacked' => ['label' => 'Stacked', 'icon' => 'align-bottom'],
            ])->default('two-column'),

            // Style ------------------------------------------------------
            Control::color('button_bg', 'Place-order button')
                ->tab(Control::TAB_STYLE)
                ->default('var(--cb-color-primary, #2563eb)')
                ->selector('{{WRAPPER}} .cb-checkout__submit', 'background-color'),

            Control::dimensions('field_radius', 'Field corners')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-checkout input, {{WRAPPER}} .cb-checkout select, {{WRAPPER}} .cb-checkout textarea', 'border-radius'),

            Control::dimensions('summary_radius', 'Summary corners')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-checkout__summary', 'border-radius'),
        ];
    }

    public function data(array $settings, array $context = []): array
    {
        $cart = app(CartService::class);
        $user = auth()->user();
        $remembers = (bool) setting('shop_save_addresses', true);

        return [
            'summary' => $cart->summary(),
            'items' => $cart->cart()->items,
            'gateways' => app(PaymentManager::class)->availableFor(),
            'user' => $user,
            'isEmpty' => $cart->isEmpty(),
            // The same list and prefill the theme's own checkout uses, so a
            // builder-made checkout page behaves identically.
            'countries' => Countries::selling(),
            'billing' => $remembers ? app(AddressBook::class)->prefill($user, 'billing') : [],
            'shipping' => $remembers ? app(AddressBook::class)->prefill($user, 'shipping') : [],
            // Settings -> Checkout: which fields to draw and which to insist on.
            'checkoutFields' => app(CheckoutFields::class),
            'canSaveAddress' => $remembers && $user !== null && $user->addresses()->exists(),
        ];
    }
}
