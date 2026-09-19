@extends('theme::layout')

@section('content')
    @region('checkout')

    <div class="mx-auto max-w-5xl px-4 py-14">
        <h1 class="mb-8 text-3xl font-bold tracking-tight text-slate-900">Checkout</h1>

        @if ($gateways->isEmpty())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                No payment method is available at the moment. Please contact us to complete your order.
            </div>
        @endif

        <form method="POST" action="{{ route('checkout.store') }}">
            @csrf

            <div class="grid gap-10 lg:grid-cols-3">
                <div class="space-y-8 lg:col-span-2">
                    <section>
                        <h2 class="mb-4 text-lg font-semibold text-slate-900">Contact</h2>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email address</label>
                                <input type="email" name="email" id="email" required
                                       value="{{ old('email', $user?->email) }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label for="phone" class="mb-1 block text-sm font-medium text-slate-700">Phone</label>
                                <input type="text" name="phone" id="phone"
                                       value="{{ old('phone', $billing['phone'] ?? $user?->phone) }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                        </div>
                        @guest
                            <p class="mt-2 text-xs text-slate-500">
                                Already have an account?
                                <a href="{{ route('login') }}" class="text-brand hover:underline">Sign in</a>
                                to check out faster.
                            </p>
                        @endguest
                    </section>

                    <section>
                        <h2 class="mb-4 text-lg font-semibold text-slate-900">Billing address</h2>

                        @if ($savedAddresses->count() > 1)
                            {{-- Picking another saved address reloads checkout
                                 with the fields already filled in. It posts
                                 through the GET form at the foot of the page,
                                 because forms cannot be nested. --}}
                            <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 px-4 py-3 text-sm">
                                <label for="saved-address" class="text-slate-600">Use a saved address</label>
                                <select id="saved-address" name="address" form="pick-address"
                                        class="max-w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm">
                                    @foreach ($savedAddresses as $saved)
                                        <option value="{{ $saved->id }}" @selected($chosenAddressId === $saved->id)>
                                            {{ $saved->title() }} - {{ $saved->singleLine() }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" form="pick-address"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-medium hover:bg-slate-100">
                                    Use this
                                </button>
                            </div>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Full name</label>
                                <input type="text" name="billing[name]" required autocomplete="name"
                                       value="{{ old('billing.name', $billing['name'] ?? $user?->name) }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Address</label>
                                <input type="text" name="billing[line1]" required autocomplete="address-line1"
                                       value="{{ old('billing.line1', $billing['line1'] ?? null) }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <input type="text" name="billing[line2]" placeholder="Apartment, suite (optional)"
                                       autocomplete="address-line2"
                                       value="{{ old('billing.line2', $billing['line2'] ?? null) }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">City</label>
                                <input type="text" name="billing[city]" required autocomplete="address-level2"
                                       value="{{ old('billing.city', $billing['city'] ?? null) }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">State / region</label>
                                <input type="text" name="billing[state]" autocomplete="address-level1"
                                       value="{{ old('billing.state', $billing['state'] ?? null) }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Postcode</label>
                                <input type="text" name="billing[postcode]" autocomplete="postal-code"
                                       value="{{ old('billing.postcode', $billing['postcode'] ?? null) }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Country</label>
                                @php $billingCountry = (string) old('billing.country', $billing['country'] ?? null); @endphp
                                <select name="billing[country]" required autocomplete="country"
                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                                    <option value="">Choose a country</option>
                                    @foreach ($countries as $code => $countryName)
                                        <option value="{{ $code }}" @selected($billingCountry === (string) $code)>{{ $countryName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        @if ($canSaveAddress && $savedAddresses->isNotEmpty())
                            <label class="mt-4 flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" name="save_address" value="1" class="h-4 w-4 rounded border-slate-300">
                                Save this address to my account
                            </label>
                        @endif
                    </section>

                    @if ($summary['requires_shipping'])
                        <section class="flex flex-wrap items-center">
                            {{-- The checkbox is the peer that opens the panel
                                 below it, so this works without any script. --}}
                            <input type="checkbox" name="ship_to_different" value="1" id="ship-elsewhere"
                                   @checked(old('ship_to_different'))
                                   class="peer h-4 w-4 rounded border-slate-300">
                            <label for="ship-elsewhere" class="ml-2 cursor-pointer text-sm text-slate-700">
                                Ship to a different address
                            </label>

                            <div class="mt-4 hidden w-full gap-4 sm:grid-cols-2 peer-checked:grid">
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Full name</label>
                                    <input type="text" name="shipping[name]"
                                           value="{{ old('shipping.name', $shipping['name'] ?? null) }}"
                                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Address</label>
                                    <input type="text" name="shipping[line1]"
                                           value="{{ old('shipping.line1', $shipping['line1'] ?? null) }}"
                                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">City</label>
                                    <input type="text" name="shipping[city]"
                                           value="{{ old('shipping.city', $shipping['city'] ?? null) }}"
                                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Country</label>
                                    @php $shippingCountry = (string) old('shipping.country', $shipping['country'] ?? null); @endphp
                                    <select name="shipping[country]"
                                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                                        <option value="">Choose a country</option>
                                        @foreach ($countries as $code => $countryName)
                                            <option value="{{ $code }}" @selected($shippingCountry === (string) $code)>{{ $countryName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </section>
                    @endif

                    <section>
                        <h2 class="mb-4 text-lg font-semibold text-slate-900">Payment method</h2>
                        <div class="space-y-2">
                            @foreach ($gateways as $slug => $gateway)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4 hover:border-slate-300 has-[:checked]:border-brand has-[:checked]:bg-slate-50">
                                    <input type="radio" name="payment_gateway" value="{{ $slug }}" required
                                           @checked($loop->first)
                                           class="h-4 w-4 border-slate-300">
                                    <span class="flex-1">
                                        <span class="block text-sm font-medium text-slate-900">{{ $gateway->name }}</span>
                                        @if ($gateway->mode === 'test')
                                            <span class="block text-xs text-amber-600">Test mode — no real money will move</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>

                    <section>
                        <label for="customer_note" class="mb-1 block text-sm font-medium text-slate-700">Order notes (optional)</label>
                        <textarea name="customer_note" id="customer_note" rows="3"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('customer_note') }}</textarea>
                    </section>
                </div>

                <div>
                    <div class="sticky top-24 rounded-2xl border border-slate-200 p-5">
                        <h2 class="font-semibold text-slate-900">Your order</h2>

                        <ul class="mt-4 space-y-3 border-b border-slate-100 pb-4">
                            @foreach ($items as $item)
                                <li class="flex justify-between gap-3 text-sm">
                                    <span class="min-w-0 text-slate-600">
                                        {{ $item->name() }}
                                        <span class="text-slate-400">&times;{{ $item->quantity }}</span>
                                    </span>
                                    <span class="shrink-0">{{ money($item->lineTotal()) }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd>{{ money($summary['subtotal']) }}</dd></div>
                            @if ($summary['discount'] > 0)
                                <div class="flex justify-between text-emerald-600">
                                    <dt>Discount</dt><dd>−{{ money($summary['discount']) }}</dd>
                                </div>
                            @endif
                            @if ($summary['requires_shipping'])
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">Shipping</dt>
                                    <dd>{{ $summary['shipping'] > 0 ? money($summary['shipping']) : 'Free' }}</dd>
                                </div>
                            @endif
                            @if ($summary['tax'] > 0)
                                <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd>{{ money($summary['tax']) }}</dd></div>
                            @endif
                            <div class="flex justify-between border-t border-slate-100 pt-3 text-base font-semibold">
                                <dt>Total</dt><dd>{{ money($summary['total']) }}</dd>
                            </div>
                        </dl>

                        <label class="mt-5 flex items-start gap-2 text-xs text-slate-600">
                            <input type="checkbox" name="terms" value="1" required class="mt-0.5 h-4 w-4 rounded border-slate-300">
                            <span>I agree to the @if ($termsUrl = terms_url())<a href="{{ $termsUrl }}" target="_blank" rel="noopener" class="underline">terms and conditions</a>@else terms and conditions @endif</span>
                        </label>

                        <button type="submit" @disabled($gateways->isEmpty())
                                class="mt-4 w-full rounded-lg px-4 py-3 text-sm font-semibold text-white btn-brand disabled:opacity-50">
                            Place order
                        </button>

                        <p class="mt-3 text-center text-xs text-slate-400">
                            Payment is handled by your chosen provider. We never see your card details.
                        </p>
                    </div>
                </div>
            </div>
        </form>

        {{-- Declared outside the checkout form, since forms cannot nest. --}}
        @if ($savedAddresses->count() > 1)
            <form method="GET" action="{{ route('checkout.index') }}" id="pick-address"></form>
        @endif
    </div>
    @endregion
@endsection
