<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in &middot; {{ setting('site_name', config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid h-full place-items-center bg-slate-100 px-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-indigo-600 text-lg font-bold text-white">
                {{ strtoupper(substr(setting('site_name', 'C'), 0, 1)) }}
            </span>
            <h1 class="mt-3 text-lg font-semibold text-slate-900">{{ setting('site_name', config('app.name')) }}</h1>
            <p class="text-sm text-slate-500">Sign in to the admin panel</p>
        </div>

        <form method="POST" action="{{ route('admin.login.attempt') }}"
              class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            @if ($errors->any())
                <div class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <x-form.field label="Email address" name="email" required>
                <x-form.input name="email" type="email" autocomplete="username" autofocus required />
            </x-form.field>

            <x-form.field label="Password" name="password" required>
                <x-form.input name="password" type="password" autocomplete="current-password" required />
            </x-form.field>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600">
                Keep me signed in
            </label>

            <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                Sign in
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-slate-400">
            <a href="{{ url('/') }}" class="hover:text-slate-600">&larr; Back to the site</a>
        </p>
    </div>
</body>
</html>
