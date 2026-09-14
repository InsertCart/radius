@extends('theme::layout')

@section('content')
    @region('cart')

    <div class="mx-auto max-w-5xl px-4 py-14">
        <h1 class="mb-8 text-3xl font-bold tracking-tight text-slate-900">Your cart</h1>

        @if ($issues)
            <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <ul class="list-inside list-disc">
                    @foreach ($issues as $issue)<li>{{ $issue }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if ($cart->items->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 py-20 text-center">
                <p class="text-slate-500">Your cart is empty.</p>
                <a href="{{ route('shop.index') }}" class="mt-4 inline-block rounded-lg px-5 py-2.5 text-sm font-semibold text-white btn-brand">
                    Start shopping
                </a>
            </div>
        @else
            <div class="grid gap-10 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <ul class="divide-y divide-slate-100">
                        @foreach ($cart->items as $item)
                            <li class="flex gap-4 py-5">
                                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                                    @if ($item->imageUrl())
                                        <img src="{{ $item->imageUrl() }}" alt="" class="h-full w-full object-cover">
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-900">
                                        <a href="{{ $item->product?->url() ?? '#' }}" class="hover:text-brand">{{ $item->name() }}</a>
                                    </p>
                                    <p class="mt-0.5 text-sm text-slate-500">{{ money($item->unit_price) }} each</p>

                                    @unless ($item->isAvailable())
                                        <p class="mt-1 text-xs text-rose-600">No longer available in this quantity.</p>
                                    @endunless

                                    <div class="mt-3 flex items-center gap-3">
                                        <form method="POST" action="{{ route('cart.update', $item) }}" class="flex items-center gap-2">
                                            @csrf @method('PATCH')
                                            <input type="number" name="quantity" value="{{ $item->quantity }}" min="0" max="99"
                                                   class="w-16 rounded-lg border border-slate-300 px-2 py-1 text-center text-sm"
                                                   onchange="this.form.submit()">
                                        </form>

                                        <form method="POST" action="{{ route('cart.remove', $item) }}">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-slate-500 hover:text-rose-600">Remove</button>
                                        </form>
                                    </div>
                                </div>

                                <p class="shrink-0 font-semibold text-slate-900">{{ money($item->lineTotal()) }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <div class="rounded-2xl border border-slate-200 p-5">
                        <h2 class="font-semibold text-slate-900">Order summary</h2>

                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd>{{ money($summary['subtotal']) }}</dd></div>

                            @if ($summary['discount'] > 0)
                                <div class="flex justify-between text-emerald-600">
                                    <dt>Discount ({{ $summary['coupon']?->code }})</dt>
                                    <dd>−{{ money($summary['discount']) }}</dd>
                                </div>
                            @endif

                            @if ($summary['requires_shipping'])
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">Shipping</dt>
                                    <dd>{{ $summary['shipping'] > 0 ? money($summary['shipping']) : 'Free' }}</dd>
                                </div>
                            @endif

                            @if ($summary['tax'] > 0)
                                <div class="flex justify-between">
                                    <dt class="text-slate-500">Tax{{ setting('shop_tax_inclusive') ? ' (included)' : '' }}</dt>
                                    <dd>{{ money($summary['tax']) }}</dd>
                                </div>
                            @endif

                            <div class="flex justify-between border-t border-slate-100 pt-3 text-base font-semibold">
                                <dt>Total</dt><dd>{{ money($summary['total']) }}</dd>
                            </div>
                        </dl>

                        @if ($summary['coupon'])
                            <form method="POST" action="{{ route('cart.coupon.remove') }}" class="mt-4">
                                @csrf @method('DELETE')
                                <button class="text-xs text-slate-500 hover:text-rose-600">Remove coupon</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('cart.coupon') }}" class="mt-4 flex gap-2">
                                @csrf
                                <input type="text" name="code" placeholder="Coupon code"
                                       class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <button class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Apply</button>
                            </form>
                        @endif

                        <a href="{{ route('checkout.index') }}"
                           class="mt-5 block rounded-lg px-4 py-3 text-center text-sm font-semibold text-white btn-brand">
                            Proceed to checkout
                        </a>

                        <a href="{{ route('shop.index') }}" class="mt-3 block text-center text-sm text-slate-500 hover:text-slate-900">
                            Continue shopping
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>
    @endregion
@endsection
