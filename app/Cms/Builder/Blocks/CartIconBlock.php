<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;
use App\Cms\Shop\CartService;

class CartIconBlock extends Block
{
    public static function type(): string
    {
        return 'cart-icon';
    }

    public static function name(): string
    {
        return 'Cart icon';
    }

    public static function icon(): string
    {
        return 'cart';
    }

    public static function category(): string
    {
        return 'site';
    }

    public static function order(): int
    {
        return 4;
    }

    public static function requiresModule(): ?string
    {
        return 'shop';
    }

    public static function controls(): array
    {
        return [
            Control::toggle('show_count', 'Show item count')->default(true),
            Control::toggle('show_total', 'Show cart total'),

            Control::slider('size', 'Icon size')
                ->min(14)->max(60)->units(['px'])
                ->default(['size' => 22, 'unit' => 'px'])
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-cart-icon svg', 'width')
                ->selector('{{WRAPPER}} .cb-cart-icon svg', 'height'),

            Control::color('color', 'Colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-cart-icon', 'color'),

            Control::color('badge_bg', 'Badge background')
                ->tab(Control::TAB_STYLE)
                ->default('var(--cb-color-primary, #2563eb)')
                ->selector('{{WRAPPER}} .cb-cart-icon__badge', 'background-color'),
        ];
    }

    public function data(array $settings, array $context = []): array
    {
        // The editor previews a header without a real visitor session, so a
        // sample count keeps the badge visible while designing.
        if ($context['editing'] ?? false) {
            return ['count' => 2, 'total' => 0];
        }

        $cart = app(CartService::class);

        return [
            'count' => $cart->itemCount(),
            'total' => $cart->subtotal(),
        ];
    }
}
