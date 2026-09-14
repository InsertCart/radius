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
