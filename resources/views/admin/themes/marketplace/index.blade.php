@extends('admin.layout')
@section('title', 'Browse themes')
@section('subtitle', 'Free themes from the theme directory, installed in one click')

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.themes.marketplace.index') }}" class="flex flex-1 flex-wrap items-center gap-2">
            <input type="search" name="q" value="{{ $query }}" placeholder="Search themes"
                   class="w-full max-w-xs rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            @if ($tag)
                <input type="hidden" name="tag" value="{{ $tag }}">
            @endif
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Search</button>
            @if ($query || $tag)
                <a href="{{ route('admin.themes.marketplace.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Clear</a>
            @endif
        </form>

        <div class="flex items-center gap-3">
            @if ($lastChecked)
                <span class="text-xs text-slate-400">
                    Updated {{ \Illuminate\Support\Carbon::parse($lastChecked)->diffForHumans() }}
                </span>
            @endif
            <form method="POST" action="{{ route('admin.themes.marketplace.refresh') }}">
                @csrf
                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50">Refresh</button>
            </form>
            <a href="{{ route('admin.themes.index') }}" class="text-sm font-medium text-indigo-600 hover:underline">Installed themes</a>
        </div>
    </div>

    @if ($tags)
        <div class="mb-5 flex flex-wrap gap-1.5">
            @foreach ($tags as $name => $count)
                <a href="{{ route('admin.themes.marketplace.index', array_filter(['q' => $query, 'tag' => $tag === $name ? null : $name])) }}"
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
                <p class="text-sm font-medium text-slate-900">The theme directory is not available right now.</p>
                <p class="mt-1 text-sm text-slate-500">{{ $error ?? 'Try again in a few minutes.' }}</p>
                <p class="mt-3 text-xs text-slate-400">
                    You can still install a theme by uploading its .zip on the
                    <a href="{{ route('admin.themes.index') }}" class="text-indigo-600 hover:underline">Themes</a> screen.
                </p>
            </div>
        </x-admin.card>
    @elseif ($items === [])
        <x-admin.card>
            <x-admin.empty :message="$query || $tag ? 'No themes match that search.' : 'The theme directory has no themes yet.'" />
        </x-admin.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($items as $item)
                @php
                    $local = $installed->get($item->slug);
                    $fromDirectory = $local?->isFromMarketplace() && $local->source_slug === $item->slug;
                @endphp
                <div class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <a href="{{ route('admin.themes.marketplace.show', $item->slug) }}" class="block aspect-video bg-slate-100">
                        @if ($remoteImages && $item->screenshot)
                            <img src="{{ $item->screenshot }}" alt="{{ $item->name }} preview"
                                 class="h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer">
                        @else
                            <div class="grid h-full place-items-center text-xs text-slate-400">No preview image</div>
                        @endif
                    </a>

                    <div class="flex flex-1 flex-col p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-slate-900">
                                    <a href="{{ route('admin.themes.marketplace.show', $item->slug) }}" class="hover:text-indigo-600">{{ $item->name }}</a>
                                </h3>
                                <p class="text-xs text-slate-500">
                                    v{{ $item->version }}@if ($item->author) &middot; {{ $item->author }} @endif
                                </p>
                            </div>
                            @if ($fromDirectory && $item->isNewerThan((string) $local->version))
                                <x-admin.badge color="amber">Update</x-admin.badge>
                            @elseif ($local)
                                <x-admin.badge color="green">Installed</x-admin.badge>
                            @endif
                        </div>

                        @if ($item->description)
                            <p class="mt-2 line-clamp-2 text-sm text-slate-500">{{ $item->description }}</p>
                        @endif

                        @unless ($item->isCompatible())
                            <p class="mt-2 text-xs text-rose-600">Needs version {{ $item->requires }} of the CMS or newer.</p>
                        @endunless

                        <div class="mt-auto flex gap-2 pt-4">
                            @if ($item->isCompatible() && (! $local || ($fromDirectory && $item->isNewerThan((string) $local->version))))
                                <form method="POST" action="{{ route('admin.themes.marketplace.install', $item->slug) }}"
                                      onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Installing…';">
                                    @csrf
                                    <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">
                                        {{ $local ? 'Update' : 'Install' }}
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('admin.themes.marketplace.show', $item->slug) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">Details</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <p class="mt-6 text-xs text-slate-400">
        Every theme is checked against its published checksum and scanned exactly as an uploaded theme is before it
        is installed. Browsing the directory sends no information about this site.
    </p>
@endsection
