@extends('theme::layout')

@section('content')
    @region('cart')

    <div class="sf-wrap">
        <nav class="sf-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <b>Your bag</b>
        </nav>

        <header class="sf-phead">
            <h1>Your bag</h1>
            @if ($cart->items->isNotEmpty())
                <p>{{ $cart->items->count() }} {{ Str::plural('item', $cart->items->count()) }} ready to check out.</p>
            @endif
        </header>

        @if ($issues)
            <div class="sf-flash sf-flash--warn" role="alert">
                <ul>
                    @foreach ($issues as $issue)<li>{{ $issue }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if ($cart->items->isEmpty())
            <div class="sf-empty">
                <p>Your bag is empty.</p>
                <p class="sf-mt">
                    <a href="{{ route('shop.index') }}" class="sf-btn sf-btn--primary">Start shopping</a>
                </p>
            </div>
        @else
            <div class="sf-split">
                <div>
                    @foreach ($cart->items as $item)
                        <div class="sf-cartrow">
                            <span class="sf-cartrow__img">
                                @if ($item->imageUrl())
                                    <img src="{{ $item->imageUrl() }}" alt="" loading="lazy">
                                @endif
                            </span>

                            <div class="sf-cartrow__main">
                                <p class="sf-cartrow__name">
                                    <a href="{{ $item->product?->url() ?? '#' }}">{{ $item->name() }}</a>
                                </p>
                                <p class="sf-cartrow__unit">{{ money($item->unit_price) }} each</p>

                                @unless ($item->isAvailable())
                                    <p class="sf-bad sf-small sf-mt">No longer available in this quantity.</p>
                                @endunless

                                <div class="sf-cartrow__acts">
                                    <form method="POST" action="{{ route('cart.update', $item) }}">
                                        @csrf @method('PATCH')
                                        <span class="sf-qty" data-qty>
                                            <button type="button" data-qty-step="-1" aria-label="Decrease quantity">&minus;</button>
                                            <label for="sf-qty-{{ $item->id }}" class="sf-sr">Quantity</label>
                                            <input type="number" name="quantity" id="sf-qty-{{ $item->id }}"
                                                   value="{{ $item->quantity }}" min="0" max="99"
                                                   onchange="this.form.submit()">
                                            <button type="button" data-qty-step="1" aria-label="Increase quantity">+</button>
                                        </span>
                                    </form>

                                    <form method="POST" action="{{ route('cart.remove', $item) }}">
                                        @csrf @method('DELETE')
                                        <button class="sf-linkbtn">Remove</button>
                                    </form>
                                </div>
                            </div>

                            <p class="sf-cartrow__total">{{ money($item->lineTotal()) }}</p>
                        </div>
                    @endforeach

                    <p class="sf-mt">
                        <a href="{{ route('shop.index') }}" class="sf-small sf-muted">&larr; Continue shopping</a>
                    </p>
                </div>

                <div>
                    <div class="sf-panel sf-panel--sticky">
                        <p class="sf-panel__title">Order summary</p>

                        <dl>
                            <div class="sf-line"><dt>Subtotal</dt><dd>{{ money($summary['subtotal']) }}</dd></div>

                            @if ($summary['discount'] > 0)
                                <div class="sf-line sf-line--save">
                                    <dt>Discount ({{ $summary['coupon']?->code }})</dt>
                                    <dd>&minus;{{ money($summary['discount']) }}</dd>
                                </div>
                            @endif

                            @if ($summary['requires_shipping'])
                                <div class="sf-line">
                                    <dt>Delivery</dt>
                                    <dd>{{ $summary['shipping'] > 0 ? money($summary['shipping']) : 'Free' }}</dd>
                                </div>
                            @endif

                            @if ($summary['tax'] > 0)
                                <div class="sf-line">
                                    <dt>Tax{{ setting('shop_tax_inclusive') ? ' (included)' : '' }}</dt>
                                    <dd>{{ money($summary['tax']) }}</dd>
                                </div>
                            @endif

                            <div class="sf-line sf-line--total"><dt>Total</dt><dd>{{ money($summary['total']) }}</dd></div>
                        </dl>

                        @if ($summary['coupon'])
                            <form method="POST" action="{{ route('cart.coupon.remove') }}" class="sf-mt">
                                @csrf @method('DELETE')
                                <button class="sf-linkbtn">Remove coupon</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('cart.coupon') }}" class="sf-row sf-mt">
                                @csrf
                                <label for="sf-coupon" class="sf-sr">Coupon code</label>
                                <input type="text" name="code" id="sf-coupon" placeholder="Coupon code" class="sf-input sf-coupon">
                                <button class="sf-btn sf-btn--ghost">Apply</button>
                            </form>
                        @endif

                        <a href="{{ route('checkout.index') }}" class="sf-btn sf-btn--primary sf-btn--block sf-mt">
                            Proceed to checkout
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>
    @endregion
@endsection
