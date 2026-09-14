@extends('theme::layout')

@section('content')
    <div class="sf-wrap">
        <nav class="sf-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <b>Your account</b>
        </nav>

        <header class="sf-phead">
            <h1>@yield('heading', 'Your account')</h1>
        </header>

        <div class="sf-account">
            <nav class="sf-account__nav" aria-label="Account">
                <ul>
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
                            <a href="{{ route($route) }}" class="{{ request()->routeIs($route) ? 'is-active' : '' }}">{{ $label }}</a>
                        </li>
                    @endforeach

                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="sf-signout">Sign out</button>
                        </form>
                    </li>
                </ul>
            </nav>

            <div>@yield('account')</div>
        </div>
    </div>
@endsection
