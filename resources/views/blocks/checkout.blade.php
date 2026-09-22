{{-- The working checkout. Posts to the same controller the theme's own
     checkout does, so the order, stock and payment logic is unchanged. --}}

@if ($isEmpty && ! $editing)
    <div class="cb-checkout cb-checkout--empty">
        <p>Your cart is empty, so there is nothing to check out.</p>
        <a class="cb-button cb-button--md" href="{{ safe_route('shop.index', [], '#') }}">Browse the shop</a>
    </div>
@else
    <div class="cb-checkout cb-checkout--{{ $settings['layout'] ?? 'two-column' }}">
        @if ($gateways->isEmpty())
            <p class="cb-checkout__warning">
                No payment method is available yet. Set one up under Shop &rarr; Payment gateways.
            </p>
        @endif

        <form method="POST" action="{{ safe_route('checkout.store', [], '#') }}">
            @csrf

            {{-- Which fields to ask for, and which to insist on, is set under
                 Settings -> Checkout; the server enforces the same choice. --}}
            @php
                $addressFields = [
                    'line1' => ['Address', 'address-line1'],
                    'line2' => ['Apartment, suite', 'address-line2'],
                    'city' => ['City', 'address-level2'],
                    'state' => ['State / region', 'address-level1'],
                    'postcode' => ['Postcode', 'postal-code'],
                    'country' => ['Country', 'country'],
                ];
            @endphp

            <div class="cb-checkout__main">
                <section class="cb-checkout__section">
                    <h3>Contact</h3>
                    <div class="cb-checkout__row">
                        <label>
                            <span>Email address</span>
                            <input type="email" name="email" required autocomplete="email" value="{{ old('email', $user?->email) }}" @disabled($editing)>
                        </label>
                        @if ($checkoutFields->shows('phone'))
                            <label>
                                <span>Phone @unless ($checkoutFields->requires('phone'))<small>(optional)</small>@endunless</span>
                                <input type="text" name="phone" autocomplete="tel" @required($checkoutFields->requires('phone'))
                                       value="{{ old('phone', $billing['phone'] ?? $user?->phone) }}" @disabled($editing)>
                            </label>
                        @endif
                    </div>
                </section>

                <section class="cb-checkout__section">
                    <h3>{{ $checkoutFields->asksForAddress() ? 'Billing address' : 'Your details' }}</h3>
                    <div class="cb-checkout__row">
                        <label class="cb-checkout__wide">
                            <span>Full name</span>
                            <input type="text" name="billing[name]" required autocomplete="name" value="{{ old('billing.name', $billing['name'] ?? $user?->name) }}" @disabled($editing)>
                        </label>

                        @foreach ($addressFields as $field => [$label, $autocomplete])
                            @continue (! $checkoutFields->shows($field))
                            <label @class(['cb-checkout__wide' => in_array($field, ['line1', 'line2'], true)])>
                                <span>{{ $label }} @unless ($checkoutFields->requires($field))<small>(optional)</small>@endunless</span>
                                @if ($field === 'country')
                                    @php $billingCountry = (string) old('billing.country', $billing['country'] ?? null); @endphp
                                    <select name="billing[country]" autocomplete="country" @required($checkoutFields->requires('country')) @disabled($editing)>
                                        <option value="">Choose a country</option>
                                        @foreach ($countries as $code => $countryName)
                                            <option value="{{ $code }}" @selected($billingCountry === (string) $code)>{{ $countryName }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" name="billing[{{ $field }}]" autocomplete="{{ $autocomplete }}" @required($checkoutFields->requires($field))
                                           value="{{ old('billing.'.$field, $billing[$field] ?? null) }}" @disabled($editing)>
                                @endif
                            </label>
                        @endforeach
                    </div>

                    @if ($canSaveAddress)
                        <label class="cb-checkout__check">
                            <input type="checkbox" name="save_address" value="1" @disabled($editing)>
                            <span>Save this address to my account</span>
                        </label>
                    @endif
                </section>

                @if ($summary['requires_shipping'] && $checkoutFields->asksForAddress())
                    <section class="cb-checkout__section cb-checkout__shipto">
                        <label class="cb-checkout__check">
                            <input type="checkbox" name="ship_to_different" value="1" @checked(old('ship_to_different')) @disabled($editing)>
                            <span>Ship to a different address</span>
                        </label>

                        {{-- Opened by the checkbox above in CSS, so it needs no
                             script. No required attributes in here: a hidden
                             required field would stop the form, so the server
                             insists on them only once the box is ticked. --}}
                        <div class="cb-checkout__row cb-checkout__shipping">
                            <label class="cb-checkout__wide">
                                <span>Full name</span>
                                <input type="text" name="shipping[name]" autocomplete="shipping name"
                                       value="{{ old('shipping.name', $shipping['name'] ?? null) }}" @disabled($editing)>
                            </label>

                            @foreach ($addressFields as $field => [$label, $autocomplete])
                                @continue (! $checkoutFields->shows($field))
                                <label @class(['cb-checkout__wide' => in_array($field, ['line1', 'line2'], true)])>
                                    <span>{{ $label }} @unless ($checkoutFields->requires($field))<small>(optional)</small>@endunless</span>
                                    @if ($field === 'country')
                                        @php $shippingCountry = (string) old('shipping.country', $shipping['country'] ?? null); @endphp
                                        <select name="shipping[country]" autocomplete="shipping country" @disabled($editing)>
                                            <option value="">Choose a country</option>
                                            @foreach ($countries as $code => $countryName)
                                                <option value="{{ $code }}" @selected($shippingCountry === (string) $code)>{{ $countryName }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" name="shipping[{{ $field }}]" autocomplete="shipping {{ $autocomplete }}"
                                               value="{{ old('shipping.'.$field, $shipping[$field] ?? null) }}" @disabled($editing)>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="cb-checkout__section">
                    <h3>Payment method</h3>
                    <div class="cb-checkout__methods">
                        @foreach ($gateways as $slug => $gateway)
                            <label class="cb-checkout__method">
                                <input type="radio" name="payment_gateway" value="{{ $slug }}"
                                       required @checked($loop->first) @disabled($editing)>
                                <span>
                                    <strong>{{ $gateway->name }}</strong>
                                    @if ($gateway->mode === 'test')
                                        <small>Test mode &mdash; no real money will move</small>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>

                {{-- The widget's "Ask for order notes" switch can drop the field
                     while it is optional. Once the shop makes notes required
                     it always shows: without it no order could be placed. --}}
                @if (! empty($settings['show_notes']) && $checkoutFields->shows('customer_note') || $checkoutFields->requires('customer_note'))
                    <section class="cb-checkout__section">
                        <label>
                            <span>Order notes @unless ($checkoutFields->requires('customer_note'))<small>(optional)</small>@endunless</span>
                            <textarea name="customer_note" rows="3" @required($checkoutFields->requires('customer_note')) @disabled($editing)>{{ old('customer_note') }}</textarea>
                        </label>
                    </section>
                @endif
            </div>

            @if (! empty($settings['show_summary']))
                <aside class="cb-checkout__summary">
                    <h3>Your order</h3>

                    <ul>
                        @foreach ($items as $item)
                            <li>
                                <span>{{ $item->name() }} &times;{{ $item->quantity }}</span>
                                <span>{{ money($item->lineTotal()) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <dl>
                        <div><dt>Subtotal</dt><dd>{{ money($summary['subtotal']) }}</dd></div>
                        @if ($summary['discount'] > 0)
                            <div><dt>Discount</dt><dd>&minus;{{ money($summary['discount']) }}</dd></div>
                        @endif
                        @if ($summary['requires_shipping'])
                            <div><dt>Shipping</dt><dd>{{ $summary['shipping'] > 0 ? money($summary['shipping']) : 'Free' }}</dd></div>
                        @endif
                        @if ($summary['tax'] > 0)
                            <div><dt>Tax</dt><dd>{{ money($summary['tax']) }}</dd></div>
                        @endif
                        <div class="cb-checkout__total"><dt>Total</dt><dd>{{ money($summary['total']) }}</dd></div>
                    </dl>

                    <label class="cb-checkout__check">
                        <input type="checkbox" name="terms" value="1" required @disabled($editing)>
                        <span>I agree to the @if ($termsUrl = terms_url())<a href="{{ $termsUrl }}" target="_blank" rel="noopener" class="underline">terms and conditions</a>@else terms and conditions @endif</span>
                    </label>

                    <button type="submit" class="cb-button cb-button--md cb-button--full cb-checkout__submit"
                            @disabled($editing || $gateways->isEmpty())>
                        {{ $settings['button_text'] ?? 'Place order' }}
                    </button>

                    <p class="cb-checkout__note">
                        Payment is handled by your chosen provider. We never see your card details.
                    </p>
                </aside>
            @endif
        </form>
    </div>
@endif
