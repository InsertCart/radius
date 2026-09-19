@extends('theme::account.layout')
@section('heading', 'Order '.$order->order_number)

@section('account')
    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">Placed {{ format_date($order->created_at, 'd M Y') }}</p>
                    <p class="mt-1 capitalize">
                        <span class="text-slate-700">{{ $order->status }}</span>
                        <span class="text-slate-400">&middot; {{ str_replace('_', ' ', $order->payment_status) }}</span>
                    </p>
                </div>
                @if ($order->tracking_number)
                    <div class="text-right">
                        <p class="text-xs text-slate-500">Tracking</p>
                        <p class="font-mono text-sm">{{ $order->tracking_number }}</p>
                    </div>
                @endif
            </div>

            <ul class="mt-6 divide-y divide-slate-100">
                @foreach ($order->items as $item)
                    <li class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-sm text-slate-800">{{ $item->name }}</p>
                            <p class="text-xs text-slate-400">
                                Qty {{ $item->quantity }} &middot; {{ money($item->unit_price, $order->currency) }} each
                            </p>
                            @if ($item->isDownloadable())
                                @if ($item->canDownload())
                                    <a href="{{ route('account.orders.download', ['order' => $order->order_number, 'item' => $item->id]) }}"
                                       class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium text-brand hover:underline">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                                        </svg>
                                        Download {{ $item->digital_name ?: 'file' }}
                                    </a>

                                    {{-- Said up front, so nobody discovers the limit by hitting it. --}}
                                    @if ($remaining = $item->downloadsRemaining())
                                        <p class="text-[11px] text-slate-400">
                                            {{ $remaining }} {{ Str::plural('download', $remaining) }} remaining
                                            @if ($expires = $item->downloadsExpireAt())
                                                &middot; available until {{ $expires->format('j M Y') }}
                                            @endif
                                        </p>
                                    @elseif ($expires = $item->downloadsExpireAt())
                                        <p class="text-[11px] text-slate-400">Available until {{ $expires->format('j M Y') }}</p>
                                    @endif
                                @else
                                    <p class="mt-1 text-xs text-slate-400">{{ $item->downloadRefusalReason() }}</p>
                                @endif
                            @endif
                        </div>
                        <p class="shrink-0 font-medium">{{ money($item->line_total, $order->currency) }}</p>
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

        @if ($order->shipping_address)
            <div class="rounded-2xl border border-slate-200 p-6">
                <h2 class="font-semibold text-slate-900">Shipping address</h2>
                <address class="mt-2 text-sm not-italic leading-relaxed text-slate-600">
                    {{ format_address($order->shipping_address) }}
                </address>
            </div>
        @endif

        <a href="{{ route('account.orders') }}" class="inline-block text-sm text-slate-500 hover:text-slate-900">
            &larr; Back to your orders
        </a>
    </div>
@endsection
