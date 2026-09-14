@extends('admin.layout')
@section('title', 'Payment gateways')
@section('subtitle', 'Shop currency: '.$currency)

@section('content')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($gateways as $slug => $gateway)
            @php $supported = in_array('*', $gateway['currencies'], true) || in_array($currency, $gateway['currencies'], true); @endphp

            <div @class([
                'rounded-2xl border bg-white p-5 shadow-sm',
                'border-emerald-200 ring-1 ring-emerald-100' => $gateway['enabled'],
                'border-slate-200' => ! $gateway['enabled'],
            ])>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ $gateway['name'] }}</h3>
                        <p class="mt-0.5 text-xs capitalize text-slate-500">{{ str_replace('_', ' ', $gateway['flow']) }} flow</p>
                    </div>
                    @if ($gateway['enabled'])
                        <x-admin.badge color="green">Live</x-admin.badge>
                    @elseif ($gateway['configured'])
                        <x-admin.badge color="amber">Ready</x-admin.badge>
                    @else
                        <x-admin.badge color="gray">Not set up</x-admin.badge>
                    @endif
                </div>

                <dl class="mt-4 space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Mode</dt>
                        <dd class="{{ $gateway['mode'] === 'live' ? 'text-emerald-600' : 'text-amber-600' }}">
                            {{ ucfirst($gateway['mode']) }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Currency</dt>
                        <dd class="{{ $supported ? 'text-slate-600' : 'text-rose-600' }}">
                            {{ $supported ? 'Supports '.$currency : $currency.' not supported' }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Refunds</dt>
                        <dd class="text-slate-600">{{ $gateway['supports_refund'] ? 'From the admin panel' : 'Provider dashboard only' }}</dd>
                    </div>
                </dl>

                <div class="mt-4 flex gap-2 border-t border-slate-100 pt-4">
                    <a href="{{ route('admin.payments.edit', $slug) }}"
                       class="flex-1 rounded-lg bg-slate-900 px-3 py-2 text-center text-xs font-medium text-white hover:bg-slate-800">
                        Configure
                    </a>
                    @if ($gateway['model'])
                        <form method="POST" action="{{ route('admin.payments.toggle', $slug) }}">
                            @csrf @method('PATCH')
                            <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium hover:bg-slate-50">
                                {{ $gateway['enabled'] ? 'Turn off' : 'Turn on' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endsection
