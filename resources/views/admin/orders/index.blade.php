@extends('admin.layout')
@section('title', 'Orders')

@section('content')
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Total revenue</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ money($totals['revenue']) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Awaiting action</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $totals['pending'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-slate-500">Unpaid</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $totals['unpaid'] }}</p>
        </div>
    </div>

    <x-admin.card bodyClass="">
        <form method="GET" class="flex flex-wrap gap-2 border-b border-slate-100 p-4">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Order number, email or transaction"
                   class="min-w-[14rem] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any status</option>
                @foreach ($statuses as $value)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ ucfirst($value) }}</option>
                @endforeach
            </select>
            <select name="payment_status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any payment</option>
                @foreach ($paymentStatuses as $value)
                    <option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>
                        {{ ucfirst(str_replace('_', ' ', $value)) }}
                    </option>
                @endforeach
            </select>
            <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Filter</button>
        </form>

        @if ($orders->isEmpty())
            <x-admin.empty message="No orders match this filter." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Order</th>
                            <th class="px-4 py-2.5 font-medium">Customer</th>
                            <th class="px-4 py-2.5 font-medium">Payment</th>
                            <th class="px-4 py-2.5 font-medium">Status</th>
                            <th class="px-4 py-2.5 text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($orders as $order)
                            <tr class="cursor-pointer hover:bg-slate-50"
                                onclick="window.location='{{ route('admin.orders.show', $order) }}'">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-indigo-600">{{ $order->order_number }}</span>
                                    <p class="text-xs text-slate-400">{{ format_date($order->created_at, 'd M Y, H:i') }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-slate-700">{{ $order->user?->name ?? data_get($order->billing_address, 'name', 'Guest') }}</p>
                                    <p class="text-xs text-slate-400">{{ $order->email }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <x-admin.badge :color="$order->paymentStatusColor()">
                                        {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                                    </x-admin.badge>
                                    <p class="mt-0.5 text-xs text-slate-400">{{ $order->payment_gateway ?: '—' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <x-admin.badge :color="$order->statusColor()">{{ ucfirst($order->status) }}</x-admin.badge>
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ money($order->grand_total, $order->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $orders->links() }}</div>
        @endif
    </x-admin.card>
@endsection
