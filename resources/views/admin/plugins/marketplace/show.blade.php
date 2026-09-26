@extends('admin.layout')
@section('title', $item->name)
@section('subtitle', 'From the plugin directory')

@section('content')
    <p class="mb-4 text-sm">
        <a href="{{ route('admin.plugins.marketplace.index') }}" class="font-medium text-indigo-600 hover:underline">&larr; All plugins</a>
    </p>

    @php
        $installed = $status['installed'];
        $canInstall = $item->isFree() && $item->isCompatible() && ! $status['blocked'] && (! $installed || $status['is_update']);
        $buyUrl = $item->purchaseUrl ?? $item->authorUrl;
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            @if ($remoteImages && $item->screenshot)
                <x-admin.card bodyClass="p-0">
                    <div class="aspect-video overflow-hidden rounded-2xl bg-slate-100">
                        <img src="{{ $item->screenshot }}" alt="{{ $item->name }}" class="h-full w-full object-cover" referrerpolicy="no-referrer">
                    </div>
                </x-admin.card>
            @endif

            <x-admin.card title="About this plugin">
                <p class="whitespace-pre-line text-sm text-slate-700">{{ $item->description ?? 'No description.' }}</p>
            </x-admin.card>
        </div>

        <div class="space-y-5">
            <x-admin.card>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Version</dt><dd class="font-medium text-slate-900">{{ $item->version }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Price</dt><dd class="font-medium text-slate-900">{{ $item->priceLabel() ?? 'Free' }}</dd></div>
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

                <div class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                    @if ($status['blocked'])
                        <p class="text-sm text-rose-600">{{ $status['blocked'] }}</p>
                    @elseif (! $item->isCompatible())
                        <p class="text-sm text-rose-600">This plugin needs version {{ $item->requires }} of the CMS or newer. Update the CMS first.</p>
                    @elseif ($installed && ! $status['is_update'])
                        <p class="text-sm text-emerald-700">Installed and up to date.</p>
                    @endif

                    @if ($canInstall)
                        <form method="POST" action="{{ route('admin.plugins.marketplace.install', $item->slug) }}"
                              onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Installing — do not close this page…';">
                            @csrf
                            <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                {{ $status['is_update'] ? 'Update to '.$item->version : 'Install' }}
                            </button>
                        </form>
                    @elseif (! $item->isFree() && ! $installed)
                        @if ($buyUrl)
                            <a href="{{ $buyUrl }}" target="_blank" rel="noopener noreferrer"
                               class="block w-full rounded-lg bg-indigo-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-indigo-700">
                                Buy {{ $item->priceLabel() ? 'for '.$item->priceLabel() : '' }}
                            </a>
                        @endif
                        <p class="text-xs text-slate-500">
                            After buying, upload the plugin's .zip on the
                            <a href="{{ route('admin.plugins.index') }}" class="text-indigo-600 hover:underline">Plugins</a> screen.
                        </p>
                    @endif

                    @if ($item->previewUrl)
                        <a href="{{ $item->previewUrl }}" target="_blank" rel="noopener noreferrer"
                           class="block w-full rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-medium hover:bg-slate-50">
                            Live demo
                        </a>
                    @endif
                </div>
            </x-admin.card>

            <x-admin.card title="Before installing">
                <p class="text-xs text-slate-500">
                    A plugin is PHP code and runs with the same access as the CMS itself. The download is
                    checked against its published SHA-256 checksum, extracted with the same rules as an
                    upload, and arrives switched off.
                </p>
            </x-admin.card>
        </div>
    </div>
@endsection
