{{-- The working cart. Self-contained so it renders correctly wherever it is
     dropped, in any theme. --}}

@if ($cart->items->isEmpty())
    <div class="cb-cart cb-cart--empty">
        <p>{{ $settings['empty_text'] ?? 'Your cart is empty.' }}</p>

        @if ($editing)
            <p class="cb-cart__note">
                Your shoppers will see their basket here. It looks empty because yours is.
            </p>
        @endif

        <a class="cb-button cb-button--md" href="{{ safe_route('shop.index', [], '#') }}">
            {{ $settings['empty_button'] ?? 'Start shopping' }}
        </a>
    </div>
@else
    <div class="cb-cart">
        @if (! empty($issues))
            <div class="cb-cart__issues">
                <ul>
                    @foreach ($issues as $issue)<li>{{ $issue }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="cb-cart__grid">
            <ul class="cb-cart__items">
                @foreach ($cart->items as $item)
                    <li class="cb-cart__item">
                        @if (! empty($settings['show_images']))
                            <div class="cb-cart__media">
                                @if ($item->imageUrl())
                                    <img src="{{ $item->imageUrl() }}" alt="" loading="lazy">
                                @endif
                            </div>
                        @endif

                        <div class="cb-cart__details">
                            <p class="cb-cart__name">
                                <a href="{{ $item->product?->url() ?? '#' }}">{{ $item->name() }}</a>
                            </p>
                            <p class="cb-cart__unit">{{ money($item->unit_price) }} each</p>

                            @unless ($item->isAvailable())
                                <p class="cb-cart__warning">No longer available in this quantity.</p>
                            @endunless

                            <div class="cb-cart__controls">
                                {{-- Disabled while editing, so a stray click in the
                                     canvas cannot change a real basket. --}}
                                <form method="POST" action="{{ safe_route('cart.update', $item, '#') }}">
                                    @csrf @method('PATCH')
                                    <input type="number" name="quantity" value="{{ $item->quantity }}"
                                           min="0" max="999" aria-label="Quantity"
                                           @disabled($editing)
                                           onchange="this.form.submit()">
                                </form>

                                <form method="POST" action="{{ safe_route('cart.remove', $item, '#') }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" @disabled($editing)>Remove</button>
                                </form>
                            </div>
                        </div>

                        <p class="cb-cart__line">{{ money($item->lineTotal()) }}</p>
                    </li>
                @endforeach
            </ul>

            <div class="cb-cart__summary">
                <h3>Order summary</h3>

                <dl>
                    <div><dt>Subtotal</dt><dd>{{ money($summary['subtotal']) }}</dd></div>

                    @if ($summary['discount'] > 0)
                        <div class="cb-cart__discount">
                            <dt>Discount{{ $summary['coupon'] ? ' ('.$summary['coupon']->code.')' : '' }}</dt>
                            <dd>&minus;{{ money($summary['discount']) }}</dd>
                        </div>
                    @endif

                    @if ($summary['requires_shipping'])
                        <div>
                            <dt>Shipping</dt>
                            <dd>{{ $summary['shipping'] > 0 ? money($summary['shipping']) : 'Free' }}</dd>
                        </div>
                    @endif

                    @if ($summary['tax'] > 0)
                        <div><dt>Tax</dt><dd>{{ money($summary['tax']) }}</dd></div>
                    @endif

                    <div class="cb-cart__total"><dt>Total</dt><dd>{{ money($summary['total']) }}</dd></div>
                </dl>

                @if (! empty($settings['show_coupon']))
                    @if ($summary['coupon'])
                        <form method="POST" action="{{ safe_route('cart.coupon.remove', [], '#') }}" class="cb-cart__coupon">
                            @csrf @method('DELETE')
                            <button type="submit" @disabled($editing)>Remove coupon</button>
                        </form>
                    @else
                        <form method="POST" action="{{ safe_route('cart.coupon', [], '#') }}" class="cb-cart__coupon">
                            @csrf
                            <input type="text" name="code" placeholder="Coupon code" aria-label="Coupon code" @disabled($editing)>
                            <button type="submit" @disabled($editing)>Apply</button>
                        </form>
                    @endif
                @endif

                <a class="cb-button cb-button--md cb-button--full cb-cart__checkout"
                   href="{{ safe_route('checkout.index', [], '#') }}">
                    {{ $settings['checkout_text'] ?? 'Proceed to checkout' }}
                </a>

                @if (! empty($settings['show_continue']))
                    <a class="cb-cart__continue" href="{{ safe_route('shop.index', [], '#') }}">Continue shopping</a>
                @endif
            </div>
        </div>
    </div>
@endif
