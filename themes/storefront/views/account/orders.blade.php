@extends('theme::account.layout')
@section('heading', 'Your orders')

@section('account')
    @if ($orders->isEmpty())
        <div class="sf-empty">
            <p>You have not placed any orders yet.</p>
            <p class="sf-mt"><a href="{{ route('shop.index') }}" class="sf-btn sf-btn--primary">Start shopping</a></p>
        </div>
    @else
        <div class="sf-scroll-x">
            <table class="sf-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="sf-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('account.orders.show', $order) }}" class="sf-mono sf-b">{{ $order->order_number }}</a>
                                <p class="sf-small sf-muted">{{ $order->items_count }} items</p>
                            </td>
                            <td class="sf-muted">{{ format_date($order->created_at) }}</td>
                            <td>
                                <span class="sf-cap">{{ $order->status }}</span>
                                <p class="sf-small sf-muted sf-cap">{{ str_replace('_', ' ', $order->payment_status) }}</p>
                            </td>
                            <td class="sf-right sf-b">{{ money($order->grand_total, $order->currency) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $orders->links('theme::partials.pagination') }}
    @endif
@endsection
