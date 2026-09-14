@extends('theme::account.layout')
@section('heading', 'Welcome back, '.$user->name)

@section('account')
    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 p-6">
            <h2 class="font-semibold text-slate-900">Your details</h2>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Name</dt><dd>{{ $user->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd>{{ $user->email }}</dd></div>
                @if ($user->phone)
                    <div class="flex justify-between"><dt class="text-slate-500">Phone</dt><dd>{{ $user->phone }}</dd></div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-slate-500">Two-factor auth</dt>
                    <dd class="{{ $user->hasTwoFactorEnabled() ? 'text-emerald-600' : 'text-amber-600' }}">
                        {{ $user->hasTwoFactorEnabled() ? 'Enabled' : 'Not set up' }}
                    </dd>
                </div>
            </dl>

            @unless ($user->hasTwoFactorEnabled())
                <a href="{{ route('two-factor.setup') }}" class="mt-4 inline-block text-sm text-brand hover:underline">
                    Turn on two-factor authentication &rarr;
                </a>
            @endunless
        </div>

        @module('shop')
            <div class="rounded-2xl border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-slate-900">Recent orders</h2>
                    <a href="{{ route('account.orders') }}" class="text-sm text-brand hover:underline">View all</a>
                </div>

                @if ($recentOrders->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">You have not placed any orders yet.</p>
                @else
                    <ul class="mt-4 divide-y divide-slate-100">
                        @foreach ($recentOrders as $order)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <a href="{{ route('account.orders.show', $order) }}" class="font-mono text-sm text-brand hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                    <p class="text-xs text-slate-400">{{ format_date($order->created_at) }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium">{{ money($order->grand_total, $order->currency) }}</p>
                                    <p class="text-xs capitalize text-slate-400">{{ $order->status }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endmodule
    </div>
@endsection
