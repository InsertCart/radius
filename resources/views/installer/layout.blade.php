<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') &middot; Setup</title>

    @include('partials.favicon')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-100 py-10">
    <div class="mx-auto w-full max-w-3xl px-4">
        <header class="mb-8 text-center">
            {{-- brand_asset() and not site_logo_url(): the wizard runs before
                 there is a database to read a site logo out of, and this is the
                 product introducing itself rather than the buyer's site. --}}
            <img src="{{ brand_asset('logo') }}" alt="{{ config('cms.name') }}"
                 class="mx-auto mb-3 h-16 w-auto">
            <p class="text-sm text-slate-500">Version {{ cms_version() }} &middot; Setup wizard</p>
        </header>

        {{-- Step indicator --}}
        @php
            $steps = [
                'requirements' => 'Server check',
                'database' => 'Database',
                'site' => 'Site details',
                'account' => 'Admin account',
                'finish' => 'Done',
            ];
            $currentIndex = array_search($step ?? 'requirements', array_keys($steps), true);
        @endphp

        <ol class="mb-8 flex items-center justify-between gap-1">
            @foreach ($steps as $key => $label)
                @php $index = $loop->index; @endphp
                <li class="flex flex-1 flex-col items-center gap-1.5">
                    <span @class([
                        'grid h-8 w-8 place-items-center rounded-full text-xs font-semibold',
                        'bg-indigo-600 text-white' => $index <= $currentIndex,
                        'bg-slate-200 text-slate-500' => $index > $currentIndex,
                    ])>{{ $index + 1 }}</span>
                    <span class="hidden text-center text-[11px] text-slate-500 sm:block">{{ $label }}</span>
                </li>
            @endforeach
        </ol>

        @foreach (['status' => 'emerald', 'warning' => 'amber', 'error' => 'rose'] as $key => $color)
            @if (session($key))
                <div class="mb-4 rounded-xl border border-{{ $color }}-200 bg-{{ $color }}-50 px-4 py-3 text-sm text-{{ $color }}-800">
                    {{ session($key) }}
                </div>
            @endif
        @endforeach

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-semibold text-slate-900">@yield('title')</h2>
            @hasSection('description')
                <p class="mt-1 text-sm text-slate-500">@yield('description')</p>
            @endif

            <div class="mt-6">@yield('content')</div>
        </div>
    </div>
</body>
</html>
