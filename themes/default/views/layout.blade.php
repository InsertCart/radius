<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="h-full scroll-smooth {{ setting('dark_mode') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Title, meta, Open Graph and JSON-LD, all from the SEO manager. --}}
    @seoHead

    @include('partials.favicon')

    @vite(['resources/css/app.css', 'resources/css/builder-front.css'])

    {{-- Stylesheets compiled from any layouts on this page. --}}
    @builderStyles

    <style>
        :root { --brand: {{ setting('primary_color', '#2563eb') }}; }
        .btn-brand { background: var(--brand); }
        .text-brand { color: var(--brand); }
        .border-brand { border-color: var(--brand); }
    </style>

    @if (setting('custom_css'))
        <style>{!! setting('custom_css') !!}</style>
    @endif

    @if (setting('header_scripts'))
        {!! setting('header_scripts') !!}
    @endif

    @if (setting('google_analytics_id'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ setting('google_analytics_id') }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ setting('google_analytics_id') }}');
        </script>
    @endif

    @stack('head')
</head>
<body class="flex min-h-full flex-col bg-white text-slate-800 antialiased">

@region('header')
    @include('theme::partials.header')
@endregion

<main class="flex-1">
    @include('theme::partials.flash')
    @yield('content')
</main>

@region('footer')
    @include('theme::partials.footer')
@endregion

@module('firebase')
    @if (app(\App\Cms\Firebase\FirebaseManager::class)->isEnabled())
        @include('theme::partials.push')
    @endif
@endmodule

@if (setting('custom_js'))
    <script>{!! setting('custom_js') !!}</script>
@endif

@builderScripts
@stack('scripts')
</body>
</html>
