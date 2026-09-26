@extends('admin.layout')
@section('title', 'Browse plugins')
@section('subtitle', 'Add-ons from the plugin directory. Free plugins install in one click.')

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.plugins.marketplace.index') }}" class="flex flex-1 flex-wrap items-center gap-2">
            <input type="search" name="q" value="{{ $query }}" placeholder="Search plugins"
                   class="w-full max-w-xs rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @if ($tag)
                <input type="hidden" name="tag" value="{{ $tag }}">
            @endif
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Search</button>
            @if ($query || $tag)
                <a href="{{ route('admin.plugins.marketplace.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Clear</a>
            @endif
        </form>

        <div class="flex items-center gap-3">
            @if ($lastChecked)
                <span class="text-xs text-slate-400">Updated {{ \Illuminate\Support\Carbon::parse($lastChecked)->diffForHumans() }}</span>
            @endif
            <form method="POST" action="{{ route('admin.plugins.marketplace.refresh') }}">
                @csrf
                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50">Refresh</button>
            </form>
            <a href="{{ route('admin.plugins.index') }}" class="text-sm font-medium text-indigo-600 hover:underline">Installed plugins</a>
        </div>
    </div>

    @if ($tags)
        <div class="mb-5 flex flex-wrap gap-1.5">
            @foreach ($tags as $name => $count)
                <a href="{{ route('admin.plugins.marketplace.index', array_filter(['q' => $query, 'tag' => $tag === $name ? null : $name])) }}"
                   @class([
                       'rounded-full px-2.5 py-1 text-xs font-medium',
                       'bg-indigo-600 text-white' => $tag === $name,
                       'bg-slate-100 text-slate-600 hover:bg-slate-200' => $tag !== $name,
                   ])>
                    {{ $name }} <span class="opacity-60">{{ $count }}</span>
                </a>
            @endforeach
        </div>
    @endif

    @if (! $catalog)
        <x-admin.card>
            <div class="py-8 text-center">
                <p class="text-sm font-medium text-slate-900">The plugin directory is not available right now.</p>
                <p class="mt-1 text-sm text-slate-500">{{ $error ?? 'Try again in a few minutes.' }}</p>
                <p class="mt-3 text-xs text-slate-400">
                    You can still install a plugin by uploading its .zip on the
                    <a href="{{ route('admin.plugins.index') }}" class="text-indigo-600 hover:underline">Plugins</a> screen.
                </p>
            </div>
        </x-admin.card>
    @elseif ($items === [])
        <x-admin.card>
            <x-admin.empty :message="$query || $tag ? 'No plugins match that search.' : 'The plugin directory has no plugins yet.'" />
        </x-admin.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($items as $item)
                @php
                    $local = $installed->get($item->slug);
                    $fromDirectory = $local?->isFromMarketplace() && $local->source_slug === $item->slug;
                    $hasUpdate = $fromDirectory && $item->isNewerThan((string) $local->version);
                @endphp
                <div class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    @if ($remoteImages && $item->screenshot)
                        <a href="{{ route('admin.plugins.marketplace.show', $item->slug) }}" class="block aspect-video bg-slate-100">
                            <img src="{{ $item->screenshot }}" alt="{{ $item->name }}" class="h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer">
                        </a>
                    @endif

                    <div class="flex flex-1 flex-col p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-slate-900">
                                    <a href="{{ route('admin.plugins.marketplace.show', $item->slug) }}" class="hover:text-indigo-600">{{ $item->name }}</a>
                                </h3>
                                <p class="text-xs text-slate-500">
                                    v{{ $item->version }}@if ($item->author) &middot; {{ $item->author }} @endif
                                </p>
                            </div>
                            @if ($hasUpdate)
                                <x-admin.badge color="amber">Update</x-admin.badge>
                            @elseif ($local)
                                <x-admin.badge color="green">Installed</x-admin.badge>
                            @elseif ($item->priceLabel())
                                <x-admin.badge color="indigo">{{ $item->priceLabel() }}</x-admin.badge>
                            @else
                                <x-admin.badge>Free</x-admin.badge>
                            @endif
                        </div>

                        @if ($item->description)
                            <p class="mt-2 line-clamp-2 text-sm text-slate-500">{{ $item->description }}</p>
                        @endif

                        @unless ($item->isCompatible())
                            <p class="mt-2 text-xs text-rose-600">Needs version {{ $item->requires }} of the CMS or newer.</p>
                        @endunless

                        <div class="mt-auto flex gap-2 pt-4">
                            @if (! $item->isFree())
                                @if (! $local && ($item->purchaseUrl ?? $item->authorUrl))
                                    <a href="{{ $item->purchaseUrl ?? $item->authorUrl }}" target="_blank" rel="noopener noreferrer"
                                       class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">Buy</a>
                                @endif
                            @elseif ($item->isCompatible() && (! $local || $hasUpdate))
                                <form method="POST" action="{{ route('admin.plugins.marketplace.install', $item->slug) }}"
                                      onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Installing…';">
                                    @csrf
                                    <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">
                                        {{ $local ? 'Update' : 'Install' }}
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('admin.plugins.marketplace.show', $item->slug) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">Details</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <p class="mt-6 text-xs text-slate-400">
        A plugin is code that runs with the same access as the CMS. Every download is checked against its published
        SHA-256 checksum before it is installed, and arrives switched off. Browsing the directory sends no
        information about this site.
    </p>
@endsection
