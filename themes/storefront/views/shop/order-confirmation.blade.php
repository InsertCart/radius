@extends('theme::layout')

@section('content')
    <div class="sf-wrap sf-wrap--narrow">
        <div class="sf-done">
            @if ($order->isPaid())
                <span class="sf-done__mark sf-done__mark--ok">&check;</span>
                <h1>Thank you for your order</h1>
                <p class="sf-muted sf-mt">We have received your payment and will be in touch shortly.</p>
            @elseif ($order->payment_status === 'awaiting')
                <span class="sf-done__mark sf-done__mark--wait">!</span>
                <h1>Order received</h1>
                <p class="sf-muted sf-mt">We are waiting for your payment to arrive.</p>
            @else
                <span class="sf-done__mark sf-done__mark--wait">&hellip;</span>
                <h1>Order placed</h1>
                <p class="sf-muted sf-mt">This order has not been paid for yet.</p>
            @endif

            <p class="sf-done__ref">{{ $order->order_number }}</p>
        </div>

        @if ($instructions)
            <div class="sf-note sf-mt-lg">
                <p class="sf-b">How to pay</p>
                <p class="sf-pre sf-mt">{{ $instructions }}</p>
                <p class="sf-b sf-mt">Quote reference {{ $order->order_number }} so we can match your payment.</p>
            </div>
        @endif

        <div class="sf-panel sf-mt-lg">
            <p class="sf-panel__title">Order summary</p>

            <ul>
                @foreach ($order->items as $item)
                    <li class="sf-line">
                        <span>{{ $item->name }} <span class="sf-muted">&times;{{ $item->quantity }}</span></span>
                        <span>{{ money($item->line_total, $order->currency) }}</span>
                    </li>
                @endforeach
            </ul>

            <dl class="sf-mt">
                <div class="sf-line"><dt>Subtotal</dt><dd>{{ money($order->subtotal, $order->currency) }}</dd></div>
                @if ($order->discount_total > 0)
                    <div class="sf-line sf-line--save"><dt>Discount</dt><dd>&minus;{{ money($order->discount_total, $order->currency) }}</dd></div>
                @endif
                @if ($order->shipping_total > 0)
                    <div class="sf-line"><dt>Delivery</dt><dd>{{ money($order->shipping_total, $order->currency) }}</dd></div>
                @endif
                @if ($order->tax_total > 0)
                    <div class="sf-line"><dt>Tax</dt><dd>{{ money($order->tax_total, $order->currency) }}</dd></div>
                @endif
                <div class="sf-line sf-line--total"><dt>Total</dt><dd>{{ money($order->grand_total, $order->currency) }}</dd></div>
            </dl>
        </div>

        <div class="sf-row sf-row--center sf-mt-lg">
            @auth
                <a href="{{ route('account.orders') }}" class="sf-btn sf-btn--primary">View your orders</a>
            @endauth
            <a href="{{ route('shop.index') }}" class="sf-btn sf-btn--ghost">Continue shopping</a>
        </div>
    </div>
@endsection
