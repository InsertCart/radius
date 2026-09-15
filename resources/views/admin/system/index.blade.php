@extends('admin.layout')
@section('title', 'System')

@section('content')
    @if ($warnings)
        <x-admin.card title="Before you go live" class="mb-6">
            <ul class="space-y-2">
                @foreach ($warnings as $warning)
                    <li class="flex gap-2 text-sm text-amber-700">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                        {{ $warning }}
                    </li>
                @endforeach
            </ul>
        </x-admin.card>
    @endif

    {{-- Security: what the server will actually hand out, established by a
         real request rather than inferred from the folder layout. --}}
    <x-admin.card title="Security" class="mb-6">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.system.security-check') }}">
                @csrf
                <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-50">
                    {{ $exposure['checked_at'] ? 'Check again' : 'Run the check' }}
                </button>
            </form>
        </x-slot:actions>

        @if ($exposure['status'] === 'exposed')
            <div class="rounded-xl border border-rose-300 bg-rose-50 p-4">
                <p class="text-sm font-semibold text-rose-900">
                    Private files are readable over the web right now
                </p>
                <ul class="mt-2 space-y-1 text-sm text-rose-800">
                    @foreach ($exposure['readable'] as $file => $meaning)
                        <li><code class="rounded bg-rose-100 px-1">{{ $file }}</code> &mdash; gives away {{ $meaning }}</li>
                    @endforeach
                </ul>
                <p class="mt-3 text-sm text-rose-900">{{ $remediation['summary'] }}</p>
                @if ($remediation['snippet'])
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-rose-950/90 p-3 text-xs text-rose-50">{{ $remediation['snippet'] }}</pre>
                @endif
                <p class="mt-3 text-xs text-rose-800">
                    Once this is fixed, change your database password and run
                    <code class="rounded bg-rose-100 px-1">php artisan key:generate</code>
                    &mdash; assume anything that was readable has been read.
                </p>
            </div>
        @elseif ($exposure['status'] === 'protected')
            <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <div class="text-sm text-emerald-900">
                    <p class="font-semibold">Your private files are not reachable over the web</p>
                    <p class="mt-0.5">
                        This server was asked for {{ count($exposure['checked']) }} files that should never be
                        public &mdash; including <code class="rounded bg-emerald-100 px-1">.env</code> &mdash;
                        and refused every one.
                        @if ($servedFromProjectRoot)
                            The site is served from the project folder, which is fine as long as this keeps
                            passing. Re-run the check after any hosting change.
                        @endif
                    </p>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <p class="font-semibold text-slate-800">Not checked yet</p>
                <p class="mt-0.5">{{ $exposure['reason'] }}</p>
            </div>
        @endif

        {{-- Shown whatever the probe says. A correct document root keeps .env
             private on nginx too, so the check can pass while the rules that
             stop an uploaded file being executed are still missing. --}}
        @unless ($readsHtaccess)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-semibold">{{ $webServer }} ignores .htaccess files</p>
                <p class="mt-1">
                    {{ $remediation['summary'] }}
                </p>
                @if ($remediation['snippet'])
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-amber-950/90 p-3 text-xs text-amber-50">{{ $remediation['snippet'] }}</pre>
                @endif
                <p class="mt-2 text-xs">
                    Uploads are still checked before they are stored, SVGs are still cleaned, and paid
                    downloads are still served only through a paid order &mdash; none of that depends on
                    the web server. What is missing is the second layer.
                </p>
            </div>
        @endunless

        @if ($exposure['checked_at'])
            <p class="mt-3 text-xs text-slate-400">
                Last checked {{ \Illuminate\Support\Carbon::parse($exposure['checked_at'])->diffForHumans() }}.
                @foreach ($exposure['checked'] as $file => $code)
                    <span class="mr-2 whitespace-nowrap">{{ $file }}: {{ $code ?? 'unreachable' }}</span>
                @endforeach
            </p>
        @endif
    </x-admin.card>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-admin.card title="Environment">
            <dl class="divide-y divide-slate-100 text-sm">
                @foreach ($environment as $label => $value)
                    <div class="flex justify-between gap-4 py-2 first:pt-0">
                        <dt class="text-slate-500">{{ $label }}</dt>
                        <dd class="truncate font-medium text-slate-800">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-admin.card>

        <div class="space-y-6">
            <x-admin.card title="Storage">
                <dl class="divide-y divide-slate-100 text-sm">
                    @foreach ($storage as $label => $info)
                        <div class="flex items-center justify-between gap-4 py-2 first:pt-0">
                            <dt>
                                <span class="text-slate-700">{{ $label }}</span>
                                @if (! $info['writable'])
                                    <span class="ml-1 text-xs text-rose-600">not writable</span>
                                @endif
                            </dt>
                            <dd class="text-slate-500">{{ number_format($info['size'] / 1048576, 1) }} MB</dd>
                        </div>
                    @endforeach
                </dl>
            </x-admin.card>

            <x-admin.card title="Maintenance">
                <div class="space-y-3">
                    <form method="POST" action="{{ route('admin.tools.cache.clear') }}">
                        @csrf
                        <button class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                            Clear all caches
                        </button>
                        <p class="mt-1 text-xs text-slate-500">Do this after editing settings or toggling modules.</p>
                    </form>

                    <form method="POST" action="{{ route('admin.tools.cache.optimize') }}">
                        @csrf
                        <button class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                            Optimise for production
                        </button>
                        <p class="mt-1 text-xs text-slate-500">
                            Caches config, routes and views. Clear the caches again before changing
                            modules, since caching routes freezes which ones exist.
                        </p>
                    </form>

                    <form method="POST" action="{{ route('admin.tools.storage.link') }}">
                        @csrf
                        <button class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                            Create the storage link
                        </button>
                        <p class="mt-1 text-xs text-slate-500">Needed for uploaded files to be reachable.</p>
                    </form>
                </div>
            </x-admin.card>

            <x-admin.card title="Diagnostics">
                <div class="flex gap-2">
                    <a href="{{ route('admin.system.activity') }}"
                       class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-medium hover:bg-slate-50">
                        Activity log
                    </a>
                    <a href="{{ route('admin.system.logs') }}"
                       class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-medium hover:bg-slate-50">
                        Error log
                    </a>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
