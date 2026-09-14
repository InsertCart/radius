@extends('admin.layout')
@section('title', 'Order '.$order->order_number)
@section('subtitle', format_date($order->created_at, 'd M Y, H:i'))

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-admin.card title="Items" bodyClass="">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-900">{{ $item->name }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ $item->sku ?: '—' }}
                                        @if ($item->options)
                                            &middot; {{ collect($item->options)->map(fn ($v, $k) => "$k: $v")->implode(', ') }}
                                        @endif
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-center text-slate-600">&times;{{ $item->quantity }}</td>
                                <td class="px-5 py-3 text-right text-slate-600">{{ money($item->unit_price, $order->currency) }}</td>
                                <td class="px-5 py-3 text-right font-medium">{{ money($item->line_total, $order->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-slate-200 text-sm">
                        <tr><td colspan="3" class="px-5 pt-3 text-right text-slate-500">Subtotal</td>
                            <td class="px-5 pt-3 text-right">{{ money($order->subtotal, $order->currency) }}</td></tr>
                        @if ($order->discount_total > 0)
                            <tr><td colspan="3" class="px-5 py-1 text-right text-slate-500">
                                Discount @if ($order->coupon_code) ({{ $order->coupon_code }}) @endif
                            </td>
                            <td class="px-5 py-1 text-right text-emerald-600">−{{ money($order->discount_total, $order->currency) }}</td></tr>
                        @endif
                        @if ($order->shipping_total > 0)
                            <tr><td colspan="3" class="px-5 py-1 text-right text-slate-500">Shipping</td>
                                <td class="px-5 py-1 text-right">{{ money($order->shipping_total, $order->currency) }}</td></tr>
                        @endif
                        @if ($order->tax_total > 0)
                            <tr><td colspan="3" class="px-5 py-1 text-right text-slate-500">Tax</td>
                                <td class="px-5 py-1 text-right">{{ money($order->tax_total, $order->currency) }}</td></tr>
                        @endif
                        <tr class="text-base font-semibold">
                            <td colspan="3" class="px-5 py-3 text-right">Total</td>
                            <td class="px-5 py-3 text-right">{{ money($order->grand_total, $order->currency) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </x-admin.card>

            <x-admin.card title="Payment history" description="Every attempt against the gateway, successful or not." bodyClass="">
                @if ($order->transactions->isEmpty())
                    <x-admin.empty message="No payment attempts recorded." />
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-2.5 font-medium">When</th>
                                <th class="px-5 py-2.5 font-medium">Gateway</th>
                                <th class="px-5 py-2.5 font-medium">Type</th>
                                <th class="px-5 py-2.5 font-medium">Reference</th>
                                <th class="px-5 py-2.5 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($order->transactions as $transaction)
                                <tr>
                                    <td class="px-5 py-3 text-xs text-slate-500">{{ format_date($transaction->created_at, 'd M, H:i') }}</td>
                                    <td class="px-5 py-3">{{ $transaction->gateway }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ ucfirst($transaction->type) }}</td>
                                    <td class="px-5 py-3 font-mono text-xs text-slate-500">{{ $transaction->gateway_reference ?: '—' }}</td>
                                    <td class="px-5 py-3">
                                        <x-admin.badge :color="$transaction->statusColor()">{{ ucfirst($transaction->status) }}</x-admin.badge>
                                        @if ($transaction->message)
                                            <p class="mt-0.5 max-w-xs truncate text-xs text-slate-400" title="{{ $transaction->message }}">
                                                {{ $transaction->message }}
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-admin.card>
        </div>

        <div class="space-y-6">
            <x-admin.card title="Status">
                <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="space-y-4">
                    @csrf @method('PATCH')

                    <x-form.field label="Order status" name="status">
                        <x-form.select name="status" :value="$order->status"
                                       :options="collect($statuses)->mapWithKeys(fn ($s) => [$s => ucfirst($s)])->all()" />
                    </x-form.field>

                    <x-form.field label="Tracking number" name="tracking_number">
                        <x-form.input name="tracking_number" :value="$order->tracking_number" />
                    </x-form.field>

                    <x-form.field label="Internal note" name="note" help="Only ever shown here.">
                        <x-form.textarea name="note" rows="2" />
                    </x-form.field>

                    <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Update order
                    </button>
                </form>
            </x-admin.card>

            <x-admin.card title="Payment">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Status</dt>
                        <dd><x-admin.badge :color="$order->paymentStatusColor()">{{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</x-admin.badge></dd>
                    </div>
                    <div class="flex justify-between"><dt class="text-slate-500">Gateway</dt><dd>{{ $order->payment_gateway ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Transaction</dt>
                        <dd class="truncate font-mono text-xs">{{ $order->transaction_id ?: '—' }}</dd>
                    </div>
                    @if ($order->paid_at)
                        <div class="flex justify-between"><dt class="text-slate-500">Paid</dt><dd>{{ format_date($order->paid_at, 'd M Y, H:i') }}</dd></div>
                    @endif
                </dl>

                <div class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                    @unless ($order->isPaid())
                        <form method="POST" action="{{ route('admin.orders.paid', $order) }}"
                              onsubmit="return confirm('Mark this order as paid? Do this only once you have confirmed the money arrived.')">
                            @csrf @method('PATCH')
                            <input type="text" name="transaction_id" placeholder="Reference (optional)"
                                   class="mb-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <button class="w-full rounded-lg border border-emerald-300 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50">
                                Mark as paid
                            </button>
                        </form>
                    @endunless

                    @if ($canReconcile)
                        <form method="POST" action="{{ route('admin.orders.reconcile', $order) }}">
                            @csrf
                            <button class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                                Check Wise for a matching transfer
                            </button>
                        </form>
                    @endif

                    @if ($canRefund)
                        <form method="POST" action="{{ route('admin.orders.refund', $order) }}"
                              onsubmit="return confirm('Send this refund through the payment gateway?')">
                            @csrf
                            <input type="number" step="0.01" name="amount" placeholder="Full amount"
                                   max="{{ from_minor_units($order->grand_total) }}"
                                   class="mb-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <button class="w-full rounded-lg border border-amber-300 px-4 py-2 text-sm font-medium text-amber-700 hover:bg-amber-50">
                                Refund
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('admin.orders.invoice', $order) }}" target="_blank" rel="noopener"
                       class="block w-full rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-medium hover:bg-slate-50">
                        View invoice
                    </a>
                </div>
            </x-admin.card>

            <x-admin.card title="Customer">
                <p class="text-sm font-medium text-slate-900">{{ $order->user?->name ?? data_get($order->billing_address, 'name', 'Guest') }}</p>
                <p class="text-sm text-slate-500">{{ $order->email }}</p>
                @if ($order->phone)
                    <p class="text-sm text-slate-500">{{ $order->phone }}</p>
                @endif

                @foreach (['Billing' => $order->billing_address, 'Shipping' => $order->shipping_address] as $label => $address)
                    @if ($address)
                        <div class="mt-4 border-t border-slate-100 pt-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                            <address class="mt-1 text-sm not-italic leading-relaxed text-slate-600">
                                {{ collect($address)->filter()->implode(', ') }}
                            </address>
                        </div>
                    @endif
                @endforeach

                @if ($order->customer_note)
                    <div class="mt-4 border-t border-slate-100 pt-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Customer note</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $order->customer_note }}</p>
                    </div>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
