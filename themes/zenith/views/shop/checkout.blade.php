@extends('theme::layout')

@section('content')
    @region('checkout')
    <div class="zn-wrap" style="padding-top: 30px; padding-bottom: 80px;">
        <nav class="zn-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <a href="{{ route('cart.index') }}">Bag</a>
            <span>/</span>
            <b>Acquisition Checkout</b>
        </nav>

        <header style="margin-bottom: 30px;">
            <h1 style="font-size: clamp(28px, 3.5vw, 38px); font-weight: 800; margin-bottom: 6px;">Secure Acquisition</h1>
            <p style="color: var(--zn-muted); margin: 0;">Complete your details to finalize your order.</p>
        </header>

        @if ($gateways->isEmpty())
            <div class="zn-flash zn-flash--error" role="alert">
                <span>No payment gateway is currently available. Please contact concierge support to finalize your acquisition.</span>
            </div>
        @endif

        <form method="POST" action="{{ route('checkout.store') }}">
            @csrf

            <div class="zn-cart-grid">
                <div style="display: flex; flex-direction: column; gap: 24px;">
                    {{-- Contact Panel --}}
                    <div style="background: var(--zn-surface); border: 1px solid var(--zn-border); border-radius: var(--zn-radius-xl); padding: 28px;">
                        <h3 style="font-size: 17px; font-weight: 800; margin-bottom: 18px;">1. Client Contact</h3>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div>
                                <label for="email" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase;">Email Address</label>
                                <input type="email" name="email" id="email" required autocomplete="email" value="{{ old('email', $user?->email) }}"
                                       style="width: 100%; height: 42px; padding: 0 14px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 14px; box-sizing: border-box;">
                            </div>
                            <div>
                                <label for="phone" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase;">Phone Number</label>
                                <input type="text" name="phone" id="phone" autocomplete="tel" value="{{ old('phone', $billing['phone'] ?? $user?->phone) }}"
                                       style="width: 100%; height: 42px; padding: 0 14px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 14px; box-sizing: border-box;">
                            </div>
                        </div>

                        @guest
                            <p style="font-size: 12.5px; color: var(--zn-muted); margin-top: 12px; margin-bottom: 0;">
                                Already a society member? <a href="{{ route('login') }}" style="color: var(--zn-accent); font-weight: 600;">Sign in</a> for express checkout.
                            </p>
                        @endguest
                    </div>

                    {{-- Billing Address Panel --}}
                    <div style="background: var(--zn-surface); border: 1px solid var(--zn-border); border-radius: var(--zn-radius-xl); padding: 28px;">
                        <h3 style="font-size: 17px; font-weight: 800; margin-bottom: 18px;">2. Billing Address</h3>

                        <div style="display: flex; flex-direction: column; gap: 14px;">
                            <div>
                                <label for="billing-name" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase;">Full Name</label>
                                <input type="text" name="billing[name]" id="billing-name" required autocomplete="name" value="{{ old('billing.name', $billing['name'] ?? $user?->name) }}"
                                       style="width: 100%; height: 42px; padding: 0 14px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 14px; box-sizing: border-box;">
                            </div>

                            <div>
                                <label for="billing-line1" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase;">Address</label>
                                <input type="text" name="billing[line1]" id="billing-line1" required autocomplete="address-line1" value="{{ old('billing.line1', $billing['line1'] ?? null) }}"
                                       style="width: 100%; height: 42px; padding: 0 14px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 14px; box-sizing: border-box;">
                            </div>

                            <div>
                                <input type="text" name="billing[line2]" placeholder="Suite, apartment, floor (optional)" autocomplete="address-line2" value="{{ old('billing.line2', $billing['line2'] ?? null) }}"
                                       style="width: 100%; height: 42px; padding: 0 14px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 14px; box-sizing: border-box;">
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                <div>
                                    <label for="billing-city" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase;">City</label>
                                    <input type="text" name="billing[city]" id="billing-city" required autocomplete="address-level2" value="{{ old('billing.city', $billing['city'] ?? null) }}"
                                           style="width: 100%; height: 42px; padding: 0 14px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 14px; box-sizing: border-box;">
                                </div>
                                <div>
                                    <label for="billing-postcode" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase;">Postal / ZIP Code</label>
                                    <input type="text" name="billing[postcode]" id="billing-postcode" autocomplete="postal-code" value="{{ old('billing.postcode', $billing['postcode'] ?? null) }}"
                                           style="width: 100%; height: 42px; padding: 0 14px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 14px; box-sizing: border-box;">
                                </div>
                            </div>

                            <div>
                                <label for="billing-country" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase;">Country</label>
                                @php $billingCountry = (string) old('billing.country', $billing['country'] ?? null); @endphp
                                <select name="billing[country]" id="billing-country" required autocomplete="country"
                                        style="width: 100%; height: 44px; padding: 0 14px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 14px; box-sizing: border-box;">
                                    <option value="">Select country</option>
                                    @foreach ($countries as $code => $countryName)
                                        <option value="{{ $code }}" @selected($billingCountry === (string) $code)>{{ $countryName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Payment Gateways Panel --}}
                    <div style="background: var(--zn-surface); border: 1px solid var(--zn-border); border-radius: var(--zn-radius-xl); padding: 28px;">
                        <h3 style="font-size: 17px; font-weight: 800; margin-bottom: 18px;">3. Payment Method</h3>

                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            @foreach ($gateways as $slug => $gateway)
                                <label style="display: flex; align-items: center; gap: 12px; padding: 14px 18px; border: 1px solid var(--zn-border); border-radius: var(--zn-radius-md); cursor: pointer; background: var(--zn-bg-alt);">
                                    <input type="radio" name="payment_gateway" value="{{ $slug }}" required @checked($loop->first)>
                                    <span style="font-weight: 600; font-size: 14px;">{{ $gateway->name }}</span>
                                    @if ($gateway->mode === 'test')
                                        <span class="zn-badge zn-badge--accent" style="margin-left: auto;">Test Mode</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Order Summary --}}
                <div>
                    <div class="zn-order-summary">
                        <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 20px;">Review Order</h3>

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
                                    <span style="color: var(--zn-muted);">Tax</span>
                                    <span style="font-weight: 700;">{{ money($summary['tax']) }}</span>
                                </div>
                            @endif

                            <div style="display: flex; justify-content: space-between; padding-top: 14px; border-top: 1px solid var(--zn-border); font-size: 18px; font-weight: 800;">
                                <span>Total</span>
                                <span>{{ money($summary['total']) }}</span>
                            </div>
                        </div>

                        <button type="submit" class="zn-btn zn-btn--primary zn-btn--lg zn-btn--block">
                            Authorize Acquisition
                            @include('theme::partials.icon', ['name' => 'arrow-right'])
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    @endregion
@endsection
