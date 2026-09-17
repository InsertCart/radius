<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Preview' }}</title>
    @vite(['resources/css/app.css', 'resources/css/builder-front.css'])
    @builderStyles
    @foreach ($themeAssets['styles'] ?? [] as $stylesheet)
        <link rel="stylesheet" href="{{ $stylesheet }}">
    @endforeach
    @stack('head')
</head>
<body class="cb-bare {{ $themeAssets['body_class'] ?? '' }}">
    @yield('content')
    @foreach ($themeAssets['scripts'] ?? [] as $script)
        <script src="{{ $script }}" defer></script>
    @endforeach
    @builderScripts
    @stack('scripts')
</body>
</html>
