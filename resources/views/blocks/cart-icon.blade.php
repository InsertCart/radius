<a class="cb-cart-icon" href="{{ safe_route('cart.index', [], '#') }}" aria-label="Cart, {{ $count }} items">
    <x-cb-icon name="cart" />

    @if (! empty($settings['show_count']) && $count > 0)
        <span class="cb-cart-icon__badge">{{ $count }}</span>
    @endif

    @if (! empty($settings['show_total']))
        <span class="cb-cart-icon__total">{{ money($total) }}</span>
    @endif
</a>
