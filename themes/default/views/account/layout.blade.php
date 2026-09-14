@extends('theme::layout')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-14">
        <h1 class="mb-8 text-3xl font-bold tracking-tight text-slate-900">@yield('heading', 'Your account')</h1>

        <div class="grid gap-8 lg:grid-cols-4">
            <nav class="lg:col-span-1">
                <ul class="space-y-1 text-sm">
                    @php
                        $links = array_filter([
                            'account.dashboard' => 'Overview',
                            'account.orders' => modules()->enabled('shop') ? 'Orders' : null,
                            'account.profile' => 'Profile',
                            'two-factor.setup' => 'Security',
                        ]);
                    @endphp

                    @foreach ($links as $route => $label)
                        <li>
                            <a href="{{ route($route) }}"
                               @class([
                                   'block rounded-lg px-3 py-2 transition',
                                   'bg-slate-900 text-white' => request()->routeIs($route),
                                   'text-slate-600 hover:bg-slate-100' => ! request()->routeIs($route),
                               ])>{{ $label }}</a>
                        </li>
                    @endforeach

                    <li class="pt-2">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="w-full rounded-lg px-3 py-2 text-left text-rose-600 hover:bg-rose-50">Sign out</button>
                        </form>
                    </li>
                </ul>
            </nav>

            <div class="lg:col-span-3">@yield('account')</div>
        </div>
    </div>
@endsection
