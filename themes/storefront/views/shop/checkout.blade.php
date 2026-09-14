@extends('theme::layout')

@section('content')
    @region('checkout')

    <div class="sf-wrap">
        <p class="sf-steps"><b>Bag</b> <span>&rsaquo;</span> <b>Details</b> <span>&rsaquo;</span> Payment</p>

        <header class="sf-phead">
            <h1>Checkout</h1>
        </header>

        @if ($gateways->isEmpty())
            <div class="sf-flash sf-flash--bad" role="alert">
                <span>No payment method is available at the moment. Please contact us to complete your order.</span>
            </div>
        @endif

        <form method="POST" action="{{ route('checkout.store') }}">
            @csrf

            <div class="sf-split">
                <div>
                    <div class="sf-panel">
                        <p class="sf-panel__title">Contact</p>

                        <div class="sf-fields sf-fields--2">
                            <div>
                                <label for="email" class="sf-label">Email address</label>
                                <input type="email" name="email" id="email" required class="sf-input"
                                       value="{{ old('email', $user?->email) }}" autocomplete="email">
                            </div>
                            <div>
                                <label for="phone" class="sf-label">Phone</label>
                                <input type="text" name="phone" id="phone" class="sf-input"
                                       value="{{ old('phone', $user?->phone) }}" autocomplete="tel">
                            </div>
                        </div>

                        @guest
                            <p class="sf-help sf-mt">
                                Already have an account?
                                <a href="{{ route('login') }}" class="sf-b">Sign in</a> to check out faster.
                            </p>
                        @endguest
                    </div>

                    <div class="sf-panel">
                        <p class="sf-panel__title">Billing address</p>

                        <div class="sf-fields sf-fields--2">
                            <div class="sf-span2">
                                <label for="billing-name" class="sf-label">Full name</label>
                                <input type="text" name="billing[name]" id="billing-name" required class="sf-input"
                                       value="{{ old('billing.name', $user?->name) }}" autocomplete="name">
                            </div>
                            <div class="sf-span2">
                                <label for="billing-line1" class="sf-label">Address</label>
                                <input type="text" name="billing[line1]" id="billing-line1" required class="sf-input"
                                       value="{{ old('billing.line1') }}" autocomplete="address-line1">
                            </div>
                            <div class="sf-span2">
                                <label for="billing-line2" class="sf-label">Apartment, suite (optional)</label>
                                <input type="text" name="billing[line2]" id="billing-line2" class="sf-input"
                                       value="{{ old('billing.line2') }}" autocomplete="address-line2">
                            </div>
                            <div>
                                <label for="billing-city" class="sf-label">City</label>
                                <input type="text" name="billing[city]" id="billing-city" required class="sf-input"
                                       value="{{ old('billing.city') }}" autocomplete="address-level2">
                            </div>
                            <div>
                                <label for="billing-state" class="sf-label">State / region</label>
                                <input type="text" name="billing[state]" id="billing-state" class="sf-input"
                                       value="{{ old('billing.state') }}" autocomplete="address-level1">
                            </div>
                            <div>
                                <label for="billing-postcode" class="sf-label">Postcode</label>
                                <input type="text" name="billing[postcode]" id="billing-postcode" class="sf-input"
                                       value="{{ old('billing.postcode') }}" autocomplete="postal-code">
                            </div>
                            <div>
                                <label for="billing-country" class="sf-label">Country</label>
                                <input type="text" name="billing[country]" id="billing-country" required class="sf-input"
                                       value="{{ old('billing.country') }}" autocomplete="country-name">
                            </div>
                        </div>
                    </div>

                    @if ($summary['requires_shipping'])
                        <div class="sf-panel">
                            <label class="sf-check">
                                <input type="checkbox" name="ship_to_different" value="1" data-acc-btn
                                       aria-expanded="false" aria-controls="sf-shipping-fields">
                                <span>Deliver to a different address</span>
                            </label>

                            {{-- The panel is collapsed by script. With no script
                                 there is nothing to open it, so keep it visible. --}}
                            <noscript><style>#sf-shipping-fields { display: block; }</style></noscript>

                            <div class="sf-acc__panel" id="sf-shipping-fields">
                                <div class="sf-fields sf-fields--2 sf-mt">
                                    <div class="sf-span2">
                                        <label for="shipping-name" class="sf-label">Full name</label>
                                        <input type="text" name="shipping[name]" id="shipping-name" class="sf-input"
                                               value="{{ old('shipping.name') }}">
                                    </div>
                                    <div class="sf-span2">
                                        <label for="shipping-line1" class="sf-label">Address</label>
                                        <input type="text" name="shipping[line1]" id="shipping-line1" class="sf-input"
                                               value="{{ old('shipping.line1') }}">
                                    </div>
                                    <div>
                                        <label for="shipping-city" class="sf-label">City</label>
                                        <input type="text" name="shipping[city]" id="shipping-city" class="sf-input"
                                               value="{{ old('shipping.city') }}">
                                    </div>
                                    <div>
                                        <label for="shipping-country" class="sf-label">Country</label>
                                        <input type="text" name="shipping[country]" id="shipping-country" class="sf-input"
                                               value="{{ old('shipping.country') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="sf-panel">
                        <p class="sf-panel__title">Payment method</p>

                        <div class="sf-pay">
                            @foreach ($gateways as $slug => $gateway)
                                <label>
                                    <input type="radio" name="payment_gateway" value="{{ $slug }}" required @checked($loop->first)>
                                    <span>
                                        {{ $gateway->name }}
                                        @if ($gateway->mode === 'test')
                                            <small>Test mode — no real money will move</small>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="sf-panel">
                        <label for="customer_note" class="sf-label">Order notes (optional)</label>
                        <textarea name="customer_note" id="customer_note" rows="3" class="sf-textarea">{{ old('customer_note') }}</textarea>
                    </div>
                </div>

                <div>
                    <div class="sf-panel sf-panel--sticky">
                        <p class="sf-panel__title">Your order</p>

                        <ul>
                            @foreach ($items as $item)
                                <li class="sf-line">
                                    <span>{{ $item->name() }} <span class="sf-muted">&times;{{ $item->quantity }}</span></span>
                                    <span>{{ money($item->lineTotal()) }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <dl class="sf-mt">
                            <div class="sf-line"><dt>Subtotal</dt><dd>{{ money($summary['subtotal']) }}</dd></div>
                            @if ($summary['discount'] > 0)
                                <div class="sf-line sf-line--save"><dt>Discount</dt><dd>&minus;{{ money($summary['discount']) }}</dd></div>
                            @endif
                            @if ($summary['requires_shipping'])
                                <div class="sf-line">
                                    <dt>Delivery</dt>
                                    <dd>{{ $summary['shipping'] > 0 ? money($summary['shipping']) : 'Free' }}</dd>
                                </div>
                            @endif
                            @if ($summary['tax'] > 0)
                                <div class="sf-line"><dt>Tax</dt><dd>{{ money($summary['tax']) }}</dd></div>
                            @endif
                            <div class="sf-line sf-line--total"><dt>Total</dt><dd>{{ money($summary['total']) }}</dd></div>
                        </dl>

                        <label class="sf-check sf-mt">
                            <input type="checkbox" name="terms" value="1" required>
                            <span>I agree to the terms and conditions</span>
                        </label>

                        <button type="submit" @disabled($gateways->isEmpty())
                                class="sf-btn sf-btn--primary sf-btn--block sf-btn--lg sf-mt">
                            Place order
                        </button>

                        <p class="sf-help sf-center sf-mt">
                            Payment is handled by your chosen provider. We never see your card details.
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>
    @endregion
@endsection
