@extends('theme::account.layout')
@section('heading', 'Order '.$order->order_number)

@section('account')
    <div class="sf-panel">
        <div class="sf-section__head">
            <div>
                <p class="sf-small sf-muted">Placed {{ format_date($order->created_at, 'd M Y') }}</p>
                <p class="sf-cap sf-mt">
                    {{ $order->status }}
                    <span class="sf-muted">&middot; {{ str_replace('_', ' ', $order->payment_status) }}</span>
                </p>
            </div>

            @if ($order->tracking_number)
                <div class="sf-right">
                    <p class="sf-small sf-muted">Tracking</p>
                    <p class="sf-mono">{{ $order->tracking_number }}</p>
                </div>
            @endif
        </div>

        <ul>
            @foreach ($order->items as $item)
                <li class="sf-line">
                    <span>
                        {{ $item->name }}
                        <span class="sf-small sf-muted">&middot; qty {{ $item->quantity }} &middot; {{ money($item->unit_price, $order->currency) }} each</span>

                        @if ($item->isDownloadable())
                            @if ($item->canDownload())
                                <a href="{{ route('account.orders.download', ['order' => $order->order_number, 'item' => $item->id]) }}"
                                   class="sf-btn sf-btn--ghost sf-btn--sm sf-mt">
                                    @include('theme::partials.icon', ['name' => 'download'])
                                    Download {{ $item->digital_name ?: 'file' }}
                                </a>

                                @if ($remaining = $item->downloadsRemaining())
                                    <span class="sf-help">
                                        {{ $remaining }} {{ Str::plural('download', $remaining) }} remaining
                                        @if ($expires = $item->downloadsExpireAt())
                                            &middot; available until {{ $expires->format('j M Y') }}
                                        @endif
                                    </span>
                                @elseif ($expires = $item->downloadsExpireAt())
                                    <span class="sf-help">Available until {{ $expires->format('j M Y') }}</span>
                                @endif
                            @else
                                <span class="sf-help">{{ $item->downloadRefusalReason() }}</span>
                            @endif
                        @endif
                    </span>
                    <span class="sf-b">{{ money($item->line_total, $order->currency) }}</span>
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

    @if ($order->shipping_address)
        <div class="sf-panel">
            <p class="sf-panel__title">Delivery address</p>
            <address class="sf-address sf-muted sf-small">
                {{ format_address($order->shipping_address) }}
            </address>
        </div>
    @endif

    <p class="sf-mt">
        <a href="{{ route('account.orders') }}" class="sf-small sf-muted">&larr; Back to your orders</a>
    </p>
@endsection
