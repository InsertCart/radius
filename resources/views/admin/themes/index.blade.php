@extends('admin.layout')
@section('title', 'Themes')
@section('subtitle', 'Upload and activate the template your visitors see')

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($themes as $theme)
                    <div @class([
                        'overflow-hidden rounded-2xl border bg-white shadow-sm',
                        'border-indigo-300 ring-1 ring-indigo-100' => $theme->is_active,
                        'border-slate-200' => ! $theme->is_active,
                    ])>
                        <div class="aspect-video bg-slate-100">
                            @if ($theme->screenshotUrl())
                                <img src="{{ $theme->screenshotUrl() }}" alt="{{ $theme->name }} preview"
                                     class="h-full w-full object-cover" loading="lazy">
                            @else
                                <div class="grid h-full place-items-center text-xs text-slate-400">No preview image</div>
                            @endif
                        </div>

                        <div class="p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h3 class="truncate font-semibold text-slate-900">{{ $theme->name }}</h3>
                                    <p class="text-xs text-slate-500">
                                        v{{ $theme->version }}@if ($theme->author) &middot; {{ $theme->author }} @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-1">
                                    @isset($updates[$theme->slug])
                                        <x-admin.badge color="amber">Update {{ $updates[$theme->slug]->version }}</x-admin.badge>
                                    @endisset
                                    @if ($theme->is_active)
                                        <x-admin.badge color="green">Active</x-admin.badge>
                                    @endif
                                </div>
                            </div>

                            @if ($theme->description)
                                <p class="mt-2 line-clamp-2 text-sm text-slate-500">{{ $theme->description }}</p>
                            @endif

                            @if (! $theme->existsOnDisk())
                                <p class="mt-2 text-xs text-rose-600">The files for this theme are missing on disk.</p>
                            @endif

                            <div class="mt-4 flex flex-wrap gap-2">
                                @isset($updates[$theme->slug])
                                    <form method="POST" action="{{ route('admin.themes.marketplace.install', $updates[$theme->slug]->slug) }}"
                                          onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Updating…';">
                                        @csrf
                                        <button class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-600">
                                            Update to {{ $updates[$theme->slug]->version }}
                                        </button>
                                    </form>
                                @endisset

                                @unless ($theme->is_active)
                                    <form method="POST" action="{{ route('admin.themes.activate', $theme->slug) }}">
                                        @csrf
                                        <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">
                                            Activate
                                        </button>
                                    </form>

                                    @unless ($theme->isDefault())
                                        <form method="POST" action="{{ route('admin.themes.destroy', $theme->slug) }}"
                                              onsubmit="return confirm('Delete {{ $theme->name }}? Its files are removed permanently.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50">
                                                Delete
                                            </button>
                                        </form>
                                    @endunless
                                @endunless

                                @hook('admin.themes.actions', $theme)
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="space-y-6">
            @hook('admin.themes.sidebar')

            @if ($marketplaceEnabled)
                <x-admin.card title="Find a theme" description="Free themes from the theme directory, installed in one click">
                    <a href="{{ route('admin.themes.marketplace.index') }}"
                       class="block w-full rounded-lg bg-indigo-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-indigo-700">
                        Browse themes
                    </a>
                </x-admin.card>
            @endif

            <x-admin.card title="Upload a theme" description="A .zip archive containing theme.json and a views folder">
                <form method="POST" action="{{ route('admin.themes.upload') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <x-form.field label="Theme archive" name="theme" required>
                        <input type="file" name="theme" accept=".zip" required
                               class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                    </x-form.field>

                    <x-form.toggle name="overwrite" label="Replace an existing theme with the same name" />

                    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Upload and install
                    </button>
                </form>

                <div class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-xs text-slate-500">
                    <p><strong class="text-slate-700">Maximum size:</strong> {{ number_format($maxUploadKb / 1024) }} MB</p>
                    <p>
                        Uploaded archives are scanned before installation. Files outside the allowed
                        list are dropped, and a theme whose templates contain raw PHP is rejected
                        rather than installed.
                    </p>
                </div>
            </x-admin.card>

            <x-admin.card title="Theme structure">
<pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-[11px] leading-relaxed text-slate-100">my-theme/
├── theme.json
├── screenshot.png      1200 × 675, one copy only
├── assets/
│   ├── css/theme.css
│   └── js/theme.js
└── views/
    ├── layout.blade.php
    ├── home.blade.php
    ├── blog/
    ├── shop/
    └── pages/</pre>
                <p class="mt-3 text-xs text-slate-500">
                    A theme only has to override the views it wants to change &mdash; anything it
                    leaves out falls back to the default theme, so a partial theme still renders a
                    complete site.
                </p>

                <form method="POST" action="{{ route('admin.themes.sync') }}" class="mt-4">
                    @csrf
                    <button class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium hover:bg-slate-50">
                        Re-scan the themes folder
                    </button>
                </form>
            </x-admin.card>
        </div>
    </div>
@endsection
