<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') &middot; {{ setting('site_name', config('app.name')) }}</title>

    @include('partials.favicon')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid h-full place-items-center bg-slate-100 px-4 py-10">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <a href="{{ url('/') }}" class="inline-block">
                <img src="{{ site_logo_url() }}" alt="{{ setting('site_name', config('app.name')) }}"
                     class="mx-auto h-14 w-auto">
            </a>
            <p class="mt-1 text-sm text-slate-500">@yield('subtitle')</p>
        </div>

        @foreach (['status' => 'emerald', 'warning' => 'amber', 'error' => 'rose'] as $key => $color)
            @if (session($key))
                <div class="mb-4 rounded-xl border border-{{ $color }}-200 bg-{{ $color }}-50 px-4 py-3 text-sm text-{{ $color }}-800">
                    {{ session($key) }}
                </div>
            @endif
        @endforeach

        @yield('content')
    </div>
</body>
</html>
