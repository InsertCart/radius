<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Preview' }}</title>
    @vite('resources/css/builder-front.css')
    @stack('head')
</head>
<body class="cb-bare">
    @yield('content')
    @stack('scripts')
</body>
</html>
