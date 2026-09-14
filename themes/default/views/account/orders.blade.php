@extends('theme::account.layout')
@section('heading', 'Your orders')

@section('account')
    @if ($orders->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 py-16 text-center">
            <p class="text-slate-500">You have not placed any orders yet.</p>
            <a href="{{ route('shop.index') }}" class="mt-4 inline-block rounded-lg px-5 py-2.5 text-sm font-semibold text-white btn-brand">
                Start shopping
            </a>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-slate-200">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Order</th>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($orders as $order)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('account.orders.show', $order) }}" class="font-mono text-brand hover:underline">
                                    {{ $order->order_number }}
                                </a>
                                <p class="text-xs text-slate-400">{{ $order->items_count }} items</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ format_date($order->created_at) }}</td>
                            <td class="px-4 py-3">
                                <span class="capitalize text-slate-700">{{ $order->status }}</span>
                                <p class="text-xs capitalize text-slate-400">{{ str_replace('_', ' ', $order->payment_status) }}</p>
                            </td>
                            <td class="px-4 py-3 text-right font-medium">{{ money($order->grand_total, $order->currency) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $orders->links('theme::partials.pagination') }}
    @endif
@endsection
