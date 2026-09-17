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

            <div class="cb-checkout__main">
                <section class="cb-checkout__section">
                    <h3>Contact</h3>
                    <div class="cb-checkout__row">
                        <label>
                            <span>Email address</span>
                            <input type="email" name="email" required value="{{ old('email', $user?->email) }}" @disabled($editing)>
                        </label>
                        <label>
                            <span>Phone</span>
                            <input type="text" name="phone" value="{{ old('phone', $user?->phone) }}" @disabled($editing)>
                        </label>
                    </div>
                </section>

                <section class="cb-checkout__section">
                    <h3>Billing address</h3>
                    <div class="cb-checkout__row">
                        <label class="cb-checkout__wide">
                            <span>Full name</span>
                            <input type="text" name="billing[name]" required value="{{ old('billing.name', $user?->name) }}" @disabled($editing)>
                        </label>
                        <label class="cb-checkout__wide">
                            <span>Address</span>
                            <input type="text" name="billing[line1]" required value="{{ old('billing.line1') }}" @disabled($editing)>
                        </label>
                        <label>
                            <span>City</span>
                            <input type="text" name="billing[city]" required value="{{ old('billing.city') }}" @disabled($editing)>
                        </label>
                        <label>
                            <span>State / region</span>
                            <input type="text" name="billing[state]" value="{{ old('billing.state') }}" @disabled($editing)>
                        </label>
                        <label>
                            <span>Postcode</span>
                            <input type="text" name="billing[postcode]" value="{{ old('billing.postcode') }}" @disabled($editing)>
                        </label>
                        <label>
                            <span>Country</span>
                            <input type="text" name="billing[country]" required value="{{ old('billing.country') }}" @disabled($editing)>
                        </label>
                    </div>
                </section>

                @if ($summary['requires_shipping'])
                    <section class="cb-checkout__section">
                        <label class="cb-checkout__check">
                            <input type="checkbox" name="ship_to_different" value="1" @disabled($editing)>
                            <span>Ship to a different address</span>
                        </label>
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

                @if (! empty($settings['show_notes']))
                    <section class="cb-checkout__section">
                        <label>
                            <span>Order notes (optional)</span>
                            <textarea name="customer_note" rows="3" @disabled($editing)>{{ old('customer_note') }}</textarea>
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
