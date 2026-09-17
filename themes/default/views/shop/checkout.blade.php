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

        <form method="POST" action="{{ route('checkout.store') }}" x-data="{ shipToDifferent: false }">
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
                                <input type="text" name="phone" id="phone" value="{{ old('phone', $user?->phone) }}"
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
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Full name</label>
                                <input type="text" name="billing[name]" required value="{{ old('billing.name', $user?->name) }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Address</label>
                                <input type="text" name="billing[line1]" required value="{{ old('billing.line1') }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <input type="text" name="billing[line2]" placeholder="Apartment, suite (optional)"
                                       value="{{ old('billing.line2') }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">City</label>
                                <input type="text" name="billing[city]" required value="{{ old('billing.city') }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">State / region</label>
                                <input type="text" name="billing[state]" value="{{ old('billing.state') }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Postcode</label>
                                <input type="text" name="billing[postcode]" value="{{ old('billing.postcode') }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Country</label>
                                <input type="text" name="billing[country]" required value="{{ old('billing.country') }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </div>
                        </div>
                    </section>

                    @if ($summary['requires_shipping'])
                        <section>
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="ship_to_different" value="1" x-model="shipToDifferent"
                                       class="h-4 w-4 rounded border-slate-300">
                                Ship to a different address
                            </label>

                            <div x-show="shipToDifferent" x-cloak class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Full name</label>
                                    <input type="text" name="shipping[name]" value="{{ old('shipping.name') }}"
                                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Address</label>
                                    <input type="text" name="shipping[line1]" value="{{ old('shipping.line1') }}"
                                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">City</label>
                                    <input type="text" name="shipping[city]" value="{{ old('shipping.city') }}"
                                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700">Country</label>
                                    <input type="text" name="shipping[country]" value="{{ old('shipping.country') }}"
                                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
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
    </div>
    @endregion
@endsection
