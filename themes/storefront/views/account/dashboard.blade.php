@extends('theme::account.layout')
@section('heading', 'Welcome back, '.$user->name)

@section('account')
    <div class="sf-panel">
        <p class="sf-panel__title">Your details</p>

        <dl>
            <div class="sf-line"><dt>Name</dt><dd>{{ $user->name }}</dd></div>
            <div class="sf-line"><dt>Email</dt><dd>{{ $user->email }}</dd></div>
            @if ($user->phone)
                <div class="sf-line"><dt>Phone</dt><dd>{{ $user->phone }}</dd></div>
            @endif
            <div class="sf-line">
                <dt>Two-factor auth</dt>
                <dd class="{{ $user->hasTwoFactorEnabled() ? 'sf-ok' : 'sf-warn' }}">
                    {{ $user->hasTwoFactorEnabled() ? 'Enabled' : 'Not set up' }}
                </dd>
            </div>
        </dl>

        @unless ($user->hasTwoFactorEnabled())
            <p class="sf-mt">
                <a href="{{ route('two-factor.setup') }}" class="sf-btn sf-btn--ghost sf-btn--sm">
                    Turn on two-factor authentication
                </a>
            </p>
        @endunless
    </div>

    @module('shop')
        <div class="sf-panel">
            <div class="sf-section__head">
                <p class="sf-panel__title">Recent orders</p>
                <div class="sf-section__tools">
                    <a href="{{ route('account.orders') }}" class="sf-small sf-b">View all</a>
                </div>
            </div>

            @if ($recentOrders->isEmpty())
                <p class="sf-muted sf-small">You have not placed any orders yet.</p>
            @else
                <ul>
                    @foreach ($recentOrders as $order)
                        <li class="sf-line">
                            <span>
                                <a href="{{ route('account.orders.show', $order) }}" class="sf-mono sf-b">{{ $order->order_number }}</a>
                                <span class="sf-small sf-muted"> &middot; {{ format_date($order->created_at) }}</span>
                            </span>
                            <span class="sf-right">
                                <span class="sf-b">{{ money($order->grand_total, $order->currency) }}</span>
                                <span class="sf-small sf-muted sf-cap"> &middot; {{ $order->status }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endmodule
@endsection
