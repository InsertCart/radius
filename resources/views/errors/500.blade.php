<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Something went wrong</title>
    @include('partials.favicon')

    @vite('resources/css/app.css')
</head>
<body class="grid h-full place-items-center bg-white px-4 text-center">
    <div class="max-w-md">
        <p class="text-6xl font-bold text-slate-200">500</p>
        <h1 class="mt-2 text-xl font-semibold text-slate-900">Something went wrong</h1>
        <p class="mt-2 text-slate-600">
            The problem has been logged. Please try again in a moment.
        </p>
        <a href="{{ url('/') }}" class="mt-6 inline-block rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
            Back to the homepage
        </a>
    </div>
</body>
</html>
