<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffd400">

    {{-- Title, meta, Open Graph and JSON-LD, all from the SEO manager. --}}
    @seoHead

    @include('partials.favicon')

    {{-- The CMS bundle supplies the reset plus the rich-text and builder block
         styles. The theme's own sheet loads after it and owns everything the
         storefront draws. --}}
    @vite(['resources/css/app.css', 'resources/css/builder-front.css'])
    @builderStyles

    {{-- theme_asset() versions the URL from the file itself, so a changed
         stylesheet reaches returning visitors without anyone bumping theme.json. --}}

    <link rel="stylesheet" href="{{ theme_asset('css/theme.css') }}">

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

    <script src="{{ theme_asset('js/theme.js') }}" defer></script>

    @stack('head')
</head>
<body class="sf">

@region('announcement')
    @include('theme::partials.announcement')
@endregion

@region('header')
    @include('theme::partials.header')
@endregion

<main class="sf-main">
    @include('theme::partials.flash')
    @yield('content')
</main>

@region('footer')
    @include('theme::partials.footer')
@endregion

@include('theme::partials.drawers')

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
