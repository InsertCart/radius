<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Invoice {{ $order->order_number }}</title>
    @vite('resources/css/app.css')
    <style>@media print { .no-print { display: none } body { background: #fff } }</style>
</head>
<body class="bg-slate-100 p-6 text-slate-800">
    <div class="mx-auto max-w-2xl">
        <div class="no-print mb-4 flex justify-end gap-2">
            <button onclick="window.print()" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Print</button>
            <a href="{{ route('admin.orders.show', $order) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm">Back</a>
        </div>

        <div class="rounded-2xl bg-white p-8 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-xl font-bold">{{ setting('site_name', config('app.name')) }}</h1>
                    @if (setting('site_address'))
                        <p class="mt-1 whitespace-pre-line text-xs text-slate-500">{{ setting('site_address') }}</p>
                    @endif
                    <p class="text-xs text-slate-500">{{ setting('site_email') }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold uppercase tracking-wide text-slate-400">Invoice</p>
                    <p class="font-mono text-sm">{{ $order->order_number }}</p>
                    <p class="text-xs text-slate-500">{{ format_date($order->created_at) }}</p>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-2 gap-6 border-t border-slate-100 pt-6 text-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Billed to</p>
                    <p class="mt-1 font-medium">{{ data_get($order->billing_address, 'name', $order->email) }}</p>
                    <p class="text-slate-500">{{ collect($order->billing_address)->except('name')->filter()->implode(', ') }}</p>
                    <p class="text-slate-500">{{ $order->email }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Payment</p>
                    <p class="mt-1">{{ ucfirst($order->payment_gateway ?: 'Not recorded') }}</p>
                    <p class="text-slate-500">{{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</p>
                </div>
            </div>

            <table class="mt-8 w-full text-sm">
                <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="pb-2 font-medium">Description</th>
                        <th class="pb-2 text-center font-medium">Qty</th>
                        <th class="pb-2 text-right font-medium">Price</th>
                        <th class="pb-2 text-right font-medium">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="py-2.5">{{ $item->name }}</td>
                            <td class="py-2.5 text-center">{{ $item->quantity }}</td>
                            <td class="py-2.5 text-right">{{ money($item->unit_price, $order->currency) }}</td>
                            <td class="py-2.5 text-right">{{ money($item->line_total, $order->currency) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="3" class="pt-3 text-right text-slate-500">Subtotal</td>
                        <td class="pt-3 text-right">{{ money($order->subtotal, $order->currency) }}</td></tr>
                    @if ($order->discount_total > 0)
                        <tr><td colspan="3" class="py-1 text-right text-slate-500">Discount</td>
                            <td class="py-1 text-right">−{{ money($order->discount_total, $order->currency) }}</td></tr>
                    @endif
                    @if ($order->shipping_total > 0)
                        <tr><td colspan="3" class="py-1 text-right text-slate-500">Shipping</td>
                            <td class="py-1 text-right">{{ money($order->shipping_total, $order->currency) }}</td></tr>
                    @endif
                    @if ($order->tax_total > 0)
                        <tr><td colspan="3" class="py-1 text-right text-slate-500">Tax</td>
                            <td class="py-1 text-right">{{ money($order->tax_total, $order->currency) }}</td></tr>
                    @endif
                    <tr class="text-base font-bold">
                        <td colspan="3" class="border-t border-slate-200 pt-3 text-right">Total</td>
                        <td class="border-t border-slate-200 pt-3 text-right">{{ money($order->grand_total, $order->currency) }}</td>
                    </tr>
                </tfoot>
            </table>

            <p class="mt-10 text-center text-xs text-slate-400">Thank you for your business.</p>
        </div>
    </div>
</body>
</html>
