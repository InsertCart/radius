<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Back soon &middot; {{ setting('site_name', config('app.name')) }}</title>
    @include('partials.favicon')

    @vite('resources/css/app.css')
</head>
<body class="grid h-full place-items-center bg-slate-100 px-4 text-center">
    <div class="max-w-md">
        <h1 class="text-2xl font-bold text-slate-900">{{ setting('site_name', config('app.name')) }}</h1>
        <p class="mt-3 whitespace-pre-line text-slate-600">{{ $message }}</p>
        <p class="mt-6 text-xs text-slate-400">
            Site owner? <a href="{{ route('admin.login') }}" class="hover:text-indigo-600">Sign in</a> to keep working.
        </p>
    </div>
</body>
</html>
