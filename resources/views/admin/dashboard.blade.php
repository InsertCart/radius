@extends('admin.layout')
@section('title', 'Dashboard')
@section('subtitle', 'A snapshot of what is happening on your site')

@section('content')
    @if ($needsAttention)
        <x-admin.card title="Needs your attention" class="mb-6">
            <ul class="space-y-2">
                @foreach ($needsAttention as $item)
                    <li>
                        <a href="{{ $item['url'] }}" class="flex items-center gap-2 text-sm text-indigo-600 hover:underline">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-admin.card>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $stat['label'] }}</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $stat['value'] }}</p>
                @if ($stat['hint'])
                    <p class="mt-1 text-xs text-slate-500">{{ $stat['hint'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        @module('shop')
            <x-admin.card title="Revenue" description="Paid orders over the last 30 days" class="lg:col-span-2">
                @if (array_sum($salesChart['values']) > 0)
                    <div class="flex h-40 items-end gap-1">
                        @php $peak = max($salesChart['values']) ?: 1; @endphp
                        @foreach ($salesChart['values'] as $index => $value)
                            <div class="group relative flex-1">
                                <div class="rounded-t bg-indigo-500/80 transition group-hover:bg-indigo-600"
                                     style="height: {{ max(2, round(($value / $peak) * 150)) }}px"></div>
                                <span class="pointer-events-none absolute bottom-full left-1/2 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-[10px] text-white group-hover:block">
                                    {{ $salesChart['labels'][$index] }}: {{ money((int) round($value * 100)) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex justify-between text-[10px] text-slate-400">
                        <span>{{ $salesChart['labels'][0] ?? '' }}</span>
                        <span>{{ end($salesChart['labels']) ?: '' }}</span>
                    </div>
                @else
                    <p class="py-10 text-center text-sm text-slate-500">No paid orders in the last 30 days.</p>
                @endif
            </x-admin.card>
        @endmodule

        <x-admin.card title="Recent activity" bodyClass="divide-y divide-slate-100">
            @forelse ($recentActivity as $log)
                <div class="px-5 py-3 first:pt-0 last:pb-0">
                    <p class="text-sm text-slate-700">{{ $log->description ?: $log->action }}</p>
                    <p class="text-xs text-slate-400">
                        {{ $log->user?->name ?? 'System' }} &middot; {{ $log->created_at->diffForHumans() }}
                    </p>
                </div>
            @empty
                <p class="px-5 py-6 text-center text-sm text-slate-500">Nothing logged yet.</p>
            @endforelse
        </x-admin.card>
    </div>

    @module('shop')
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-admin.card title="Latest orders" class="lg:col-span-2" bodyClass="">
                @if ($recentOrders->isEmpty())
                    <x-admin.empty message="No orders yet." />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-2.5 font-medium">Order</th>
                                    <th class="px-5 py-2.5 font-medium">Customer</th>
                                    <th class="px-5 py-2.5 font-medium">Status</th>
                                    <th class="px-5 py-2.5 text-right font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($recentOrders as $order)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-5 py-3">
                                            <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-indigo-600 hover:underline">
                                                {{ $order->order_number }}
                                            </a>
                                            <p class="text-xs text-slate-400">{{ $order->created_at->diffForHumans() }}</p>
                                        </td>
                                        <td class="px-5 py-3 text-slate-600">{{ $order->user?->name ?? $order->email }}</td>
                                        <td class="px-5 py-3">
                                            <x-admin.badge :color="$order->statusColor()">{{ ucfirst($order->status) }}</x-admin.badge>
                                        </td>
                                        <td class="px-5 py-3 text-right font-medium">{{ money($order->grand_total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>

            <x-admin.card title="Low stock" description="Products running out">
                @forelse ($lowStock as $product)
                    <div class="flex items-center justify-between border-b border-slate-100 py-2 last:border-0">
                        <a href="{{ route('admin.products.edit', $product) }}" class="truncate text-sm text-slate-700 hover:text-indigo-600">
                            {{ $product->name }}
                        </a>
                        <x-admin.badge :color="$product->stock > 0 ? 'amber' : 'red'">{{ $product->stock }} left</x-admin.badge>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">Stock levels are healthy.</p>
                @endforelse
            </x-admin.card>
        </div>
    @endmodule
@endsection
