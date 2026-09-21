@extends('theme::layout')

@section('content')
    @region('cart')
    <div class="zn-wrap">
        <nav class="zn-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <b>Your Shopping Bag</b>
        </nav>

        <header style="margin-bottom: 30px;">
            <h1 style="font-size: clamp(28px, 3.5vw, 38px); font-weight: 800; margin-bottom: 6px;">Shopping Bag</h1>
            @if ($cart->items->isNotEmpty())
                <p style="color: var(--zn-muted); margin: 0;">{{ $cart->items->count() }} {{ Str::plural('piece', $cart->items->count()) }} reserved.</p>
            @endif
        </header>

        @if ($issues)
            <div class="zn-flash zn-flash--error" role="alert">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach ($issues as $issue) <li>{{ $issue }}</li> @endforeach
                </ul>
            </div>
        @endif

        @if ($cart->items->isEmpty())
            <div style="text-align: center; padding: 70px 20px; background: var(--zn-surface); border: 1px solid var(--zn-border); border-radius: var(--zn-radius-xl); margin-bottom: 60px;">
                <h3 style="font-size: 20px; font-weight: 700; margin-bottom: 8px;">Your shopping bag is empty</h3>
                <p style="color: var(--zn-muted); font-size: 14px; margin-bottom: 24px;">Explore our curated collection to add your preferred pieces.</p>
                <a href="{{ route('shop.index') }}" class="zn-btn zn-btn--primary zn-btn--lg">Explore Collection</a>
            </div>
        @else
            <div class="zn-cart-grid">
                {{-- Cart Items Table --}}
                <div style="background: var(--zn-surface); border: 1px solid var(--zn-border); border-radius: var(--zn-radius-xl); padding: 24px;">
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        @foreach ($cart->items as $item)
                            <div style="display: flex; gap: 18px; align-items: center; padding-bottom: 20px; border-bottom: 1px solid var(--zn-border);">
                                <div style="width: 80px; height: 80px; border-radius: var(--zn-radius-md); overflow: hidden; background: var(--zn-bg-alt); flex-shrink: 0;">
                                    @if ($item->imageUrl())
                                        <img src="{{ $item->imageUrl() }}" alt="{{ $item->name() }}" style="width: 100%; height: 100%; object-fit: cover;">
                                    @endif
                                </div>

                                <div style="flex: 1; min-width: 0;">
                                    <h4 style="font-size: 16px; font-weight: 700; margin: 0 0 4px;">
                                        <a href="{{ $item->product?->url() ?? '#' }}">{{ $item->name() }}</a>
                                    </h4>
                                    <div style="font-size: 13.5px; color: var(--zn-muted); margin-bottom: 10px;">
                                        {{ money($item->unit_price) }} each
                                    </div>

                                    <div style="display: flex; align-items: center; gap: 14px;">
                                        <form method="POST" action="{{ route('cart.update', $item) }}">
                                            @csrf @method('PATCH')
                                            <div style="display: flex; align-items: center; border: 1px solid var(--zn-border); border-radius: var(--zn-radius-sm); overflow: hidden; height: 32px;">
                                                <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="99" onchange="this.form.submit()"
                                                       style="width: 50px; text-align: center; border: none; background: transparent; color: var(--zn-text); font-weight: 700; font-size: 13px;">
                                            </div>
                                        </form>

                                        <form method="POST" action="{{ route('cart.remove', $item) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" style="background: none; border: none; font-size: 12.5px; color: var(--zn-rose); cursor: pointer; text-decoration: underline;">Remove</button>
                                        </form>
                                    </div>
                                </div>

                                <div style="font-family: var(--zn-font-heading); font-size: 17px; font-weight: 700; color: var(--zn-text); text-align: right;">
                                    {{ money($item->lineTotal()) }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div style="margin-top: 24px;">
                        <a href="{{ route('shop.index') }}" style="font-size: 13.5px; font-weight: 600; color: var(--zn-accent); display: inline-flex; align-items: center; gap: 6px;">
                            &larr; Continue Exploring Collection
                        </a>
                    </div>
                </div>

                {{-- Order Summary Card --}}
                <div>
                    <div class="zn-order-summary">
                        <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 20px;">Order Summary</h3>

                        <div style="display: flex; flex-direction: column; gap: 12px; font-size: 14px; margin-bottom: 24px;">
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--zn-muted);">Subtotal</span>
                                <span style="font-weight: 700;">{{ money($summary['subtotal']) }}</span>
                            </div>

                            @if ($summary['discount'] > 0)
                                <div style="display: flex; justify-content: space-between; color: var(--zn-emerald);">
                                    <span>Discount ({{ $summary['coupon']?->code }})</span>
                                    <span>&minus;{{ money($summary['discount']) }}</span>
                                </div>
                            @endif

                            @if ($summary['requires_shipping'])
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--zn-muted);">Delivery</span>
                                    <span style="font-weight: 700;">{{ $summary['shipping'] > 0 ? money($summary['shipping']) : 'Complimentary' }}</span>
                                </div>
                            @endif

                            @if ($summary['tax'] > 0)
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: var(--zn-muted);">Tax{{ setting('shop_tax_inclusive') ? ' (included)' : '' }}</span>
                                    <span style="font-weight: 700;">{{ money($summary['tax']) }}</span>
                                </div>
                            @endif

                            <div style="display: flex; justify-content: space-between; padding-top: 14px; border-top: 1px solid var(--zn-border); font-size: 18px; font-weight: 800;">
                                <span>Total</span>
                                <span>{{ money($summary['total']) }}</span>
                            </div>
                        </div>

                        {{-- Coupon Form --}}
                        @if ($summary['coupon'])
                            <form method="POST" action="{{ route('cart.coupon.remove') }}" style="margin-bottom: 18px;">
                                @csrf @method('DELETE')
                                <button type="submit" style="background: none; border: none; color: var(--zn-rose); font-size: 12.5px; cursor: pointer; text-decoration: underline;">Remove coupon code</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('cart.coupon') }}" style="display: flex; gap: 8px; margin-bottom: 24px;">
                                @csrf
                                <input type="text" name="code" placeholder="Privilege Code"
                                       style="flex: 1; height: 42px; padding: 0 14px; border-radius: var(--zn-radius-pill); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 13px;">
                                <button type="submit" class="zn-btn zn-btn--ghost zn-btn--sm">Apply</button>
                            </form>
                        @endif

                        <a href="{{ route('checkout.index') }}" class="zn-btn zn-btn--primary zn-btn--lg zn-btn--block">
                            Proceed to Acquisition
                            @include('theme::partials.icon', ['name' => 'arrow-right'])
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>
    @endregion
@endsection
