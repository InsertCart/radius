@extends('theme::layout')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-14">
        <div class="text-center">
            @if ($order->isPaid())
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-100 text-2xl text-emerald-700">&check;</span>
                <h1 class="mt-4 text-2xl font-bold text-slate-900">Thank you for your order</h1>
                <p class="mt-2 text-slate-600">We have received your payment and will be in touch shortly.</p>
            @elseif ($order->payment_status === 'awaiting')
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-amber-100 text-2xl text-amber-700">!</span>
                <h1 class="mt-4 text-2xl font-bold text-slate-900">Order received</h1>
                <p class="mt-2 text-slate-600">We are waiting for your payment to arrive.</p>
            @else
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-2xl text-slate-500">…</span>
                <h1 class="mt-4 text-2xl font-bold text-slate-900">Order placed</h1>
                <p class="mt-2 text-slate-600">This order has not been paid for yet.</p>
            @endif

            <p class="mt-4 font-mono text-sm text-slate-500">{{ $order->order_number }}</p>
        </div>

        @if ($instructions)
            <div class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <h2 class="text-sm font-semibold text-amber-900">How to pay</h2>
                <p class="mt-2 whitespace-pre-line text-sm text-amber-800">{{ $instructions }}</p>
                <p class="mt-3 text-sm font-medium text-amber-900">
                    Quote reference {{ $order->order_number }} so we can match your payment.
                </p>
            </div>
        @endif

        <div class="mt-8 rounded-2xl border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-900">Order summary</h2>

            <ul class="mt-4 divide-y divide-slate-100">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-3 py-3 text-sm">
                        <span class="text-slate-600">{{ $item->name }} <span class="text-slate-400">&times;{{ $item->quantity }}</span></span>
                        <span class="shrink-0">{{ money($item->line_total, $order->currency) }}</span>
                    </li>
                @endforeach
            </ul>

            <dl class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd>{{ money($order->subtotal, $order->currency) }}</dd></div>
                @if ($order->discount_total > 0)
                    <div class="flex justify-between text-emerald-600"><dt>Discount</dt><dd>−{{ money($order->discount_total, $order->currency) }}</dd></div>
                @endif
                @if ($order->shipping_total > 0)
                    <div class="flex justify-between"><dt class="text-slate-500">Shipping</dt><dd>{{ money($order->shipping_total, $order->currency) }}</dd></div>
                @endif
                @if ($order->tax_total > 0)
                    <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd>{{ money($order->tax_total, $order->currency) }}</dd></div>
                @endif
                <div class="flex justify-between border-t border-slate-100 pt-3 text-base font-semibold">
                    <dt>Total</dt><dd>{{ money($order->grand_total, $order->currency) }}</dd>
                </div>
            </dl>
        </div>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            @auth
                <a href="{{ route('account.orders') }}" class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white btn-brand">
                    View your orders
                </a>
            @endauth
            <a href="{{ route('shop.index') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium hover:bg-slate-50">
                Continue shopping
            </a>
        </div>
    </div>
@endsection
