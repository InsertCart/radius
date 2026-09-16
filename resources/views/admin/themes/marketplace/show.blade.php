@extends('admin.layout')
@section('title', $item->name)
@section('subtitle', 'From the theme directory')

@section('content')
    <p class="mb-4 text-sm">
        <a href="{{ route('admin.themes.marketplace.index') }}" class="font-medium text-indigo-600 hover:underline">&larr; All themes</a>
    </p>

    @php
        $installed = $status['installed'];
        $canInstall = $item->isCompatible() && ! $status['blocked'] && (! $installed || $status['is_update']);
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <x-admin.card bodyClass="p-0">
                <div class="aspect-video overflow-hidden rounded-t-2xl bg-slate-100">
                    @if ($remoteImages && $item->screenshot)
                        <img src="{{ $item->screenshot }}" alt="{{ $item->name }} preview"
                             class="h-full w-full object-cover" referrerpolicy="no-referrer">
                    @else
                        <div class="grid h-full place-items-center text-xs text-slate-400">No preview image</div>
                    @endif
                </div>

                @if ($remoteImages && count($item->screenshots) > 1)
                    <div class="grid grid-cols-3 gap-2 p-3 sm:grid-cols-4">
                        @foreach ($item->screenshots as $shot)
                            <a href="{{ $shot }}" target="_blank" rel="noopener noreferrer" class="block aspect-video overflow-hidden rounded-lg bg-slate-100">
                                <img src="{{ $shot }}" alt="" class="h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer">
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-admin.card>

            @if ($item->description)
                <x-admin.card title="About this theme">
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $item->description }}</p>
                </x-admin.card>
            @endif
        </div>

        <div class="space-y-5">
            <x-admin.card>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Version</dt><dd class="font-medium text-slate-900">{{ $item->version }}</dd></div>
                    @if ($item->author)
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Author</dt>
                            <dd class="text-right font-medium text-slate-900">
                                @if ($item->authorUrl)
                                    <a href="{{ $item->authorUrl }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ $item->author }}</a>
                                @else
                                    {{ $item->author }}
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($item->updatedAt)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Last updated</dt><dd class="text-slate-900">{{ $item->updatedAt }}</dd></div>
                    @endif
                    @if ($item->requires)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Requires CMS</dt><dd class="text-slate-900">{{ $item->requires }}+</dd></div>
                    @endif
                    @if ($item->tested)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Tested up to</dt><dd class="text-slate-900">{{ $item->tested }}</dd></div>
                    @endif
                    @if ($item->humanSize())
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Download</dt><dd class="text-slate-900">{{ $item->humanSize() }}</dd></div>
                    @endif
                    @if ($item->license)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Licence</dt><dd class="text-slate-900">{{ $item->license }}</dd></div>
                    @endif
                    @if ($installed)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Installed here</dt><dd class="text-slate-900">{{ $installed->version }}</dd></div>
                    @endif
                </dl>

                @if ($item->supports)
                    <div class="mt-4 flex flex-wrap gap-1.5 border-t border-slate-100 pt-4">
                        @foreach ($item->supports as $feature)
                            <x-admin.badge>{{ $feature }}</x-admin.badge>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                    @if ($status['blocked'])
                        <p class="text-sm text-rose-600">{{ $status['blocked'] }}</p>
                    @elseif (! $item->isCompatible())
                        <p class="text-sm text-rose-600">This theme needs version {{ $item->requires }} of the CMS or newer. Update the CMS first.</p>
                    @elseif ($installed && ! $status['is_update'])
                        <p class="text-sm text-emerald-700">Installed and up to date.</p>
                    @endif

                    @if ($canInstall)
                        <form method="POST" action="{{ route('admin.themes.marketplace.install', $item->slug) }}"
                              onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Installing — do not close this page…';">
                            @csrf
                            <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                {{ $status['is_update'] ? 'Update to '.$item->version : 'Install' }}
                            </button>
                        </form>
                        @if ($installed?->is_active)
                            <p class="text-xs text-slate-500">This is your active theme. Visitors will see the new version as soon as the update finishes.</p>
                        @endif
                    @endif

                    @if ($item->previewUrl)
                        <a href="{{ $item->previewUrl }}" target="_blank" rel="noopener noreferrer"
                           class="block w-full rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-medium hover:bg-slate-50">
                            Live preview
                        </a>
                    @endif
                </div>
            </x-admin.card>

            @if ($canInstall)
                <x-admin.card title="Before installing">
                    <ul class="space-y-2 text-sm">
                        @foreach ($checks as $check)
                            <li class="flex gap-2.5">
                                @if ($check['passed'])
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @else
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 {{ $check['fatal'] ? 'text-rose-600' : 'text-amber-500' }}" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M12 8v5m0 3.5h.01M10.3 3.9L2.4 17a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                                    </svg>
                                @endif
                                <span>
                                    <span class="font-medium text-slate-800">{{ $check['label'] }}</span>
                                    @if ($check['detail'])
                                        <span class="block text-slate-500">{{ $check['detail'] }}</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-500">
                        The download is checked against its published checksum, then scanned exactly as an uploaded
                        theme is. A theme whose templates contain executable PHP is refused.
                    </p>
                </x-admin.card>
            @endif
        </div>
    </div>
@endsection
