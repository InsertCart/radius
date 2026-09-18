@extends('admin.layout')
@section('title', 'Media library')
@section('subtitle', 'Every image and document on your site, in one place')

@section('content')
    @unless ($canProcessImages)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>Thumbnails are switched off.</strong>
            Your web server's PHP does not have the GD extension loaded, so images are stored
            as uploaded without resized copies. Enable <code class="rounded bg-amber-100 px-1">extension=gd</code>
            in php.ini and restart the web server.
        </div>
    @endunless

    @if (cdn()->enabled() && auth()->user()->isAdmin() && modules()->enabled('cdn'))
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
            <span class="text-slate-600">
                Files here are served through <strong class="text-slate-900">{{ cdn()->providerName() }}</strong>.
            </span>
            <a href="{{ route('admin.cdn.index') }}" class="font-medium text-indigo-600 hover:underline">
                Media storage settings
            </a>
        </div>
    @endif

    @if ($missingAlt > 0)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
            <span class="text-slate-600">
                <strong class="text-slate-900">{{ $missingAlt }} {{ Str::plural('image', $missingAlt) }}</strong>
                {{ $missingAlt === 1 ? 'has' : 'have' }} no alt text. Alt text describes an image to screen
                readers and search engines.
            </span>
            <a href="{{ route('admin.media.index', ['alt' => 1]) }}" class="font-medium text-indigo-600 hover:underline">
                Show them
            </a>
        </div>
    @endif

    <x-admin.card bodyClass="">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <form method="GET" class="flex flex-1 flex-wrap gap-2">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by name or alt text"
                       class="min-w-[12rem] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All files</option>
                    <option value="image" @selected(($filters['type'] ?? '') === 'image')>Images</option>
                    <option value="file" @selected(($filters['type'] ?? '') === 'file')>Documents</option>
                </select>
                <label class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="checkbox" name="alt" value="1" @checked($filters['alt'] ?? false)
                           class="h-4 w-4 rounded border-slate-300 text-indigo-600">
                    Missing alt text
                </label>
                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Filter</button>
            </form>

            <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data"
                  x-data x-ref="uploadForm">
                @csrf
                <input type="file" name="files[]" multiple class="hidden" x-ref="input"
                       accept="{{ collect($allowed)->map(fn ($e) => '.'.$e)->implode(',') }}"
                       @change="$refs.uploadForm.submit()">
                <button type="button" @click="$refs.input.click()"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Upload files
                </button>
            </form>
        </div>

        <p class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
            Accepted: {{ implode(', ', $allowed) }} &middot; up to {{ number_format($maxKb / 1024) }} MB each.
            Every file is checked before it is saved, and nothing in the uploads folder can ever be run as code.
        </p>

        @if ($files->isEmpty())
            <x-admin.empty message="{{ ($filters['alt'] ?? false) ? 'Every image has alt text. Nicely done.' : 'No files uploaded yet.' }}" />
        @else
            <div class="grid grid-cols-2 gap-4 p-4 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6">
                @foreach ($files as $file)
                    @php $needsAlt = $file->isImage() && blank($file->alt); @endphp

                    <div x-data="{ open: false }" class="group relative overflow-hidden rounded-xl border border-slate-200">
                        <button type="button" @click="open = true" class="block w-full text-left">
                            <div class="relative aspect-square bg-slate-100">
                                @if ($file->isImage())
                                    <img src="{{ $file->conversionUrl('thumb') }}" alt="{{ $file->alt }}"
                                         class="h-full w-full object-cover" loading="lazy">
                                @else
                                    <div class="grid h-full place-items-center">
                                        <div class="text-center">
                                            <svg class="mx-auto h-9 w-9 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                                            </svg>
                                            <span class="mt-1 block text-[11px] font-semibold uppercase text-slate-500">{{ $file->extension }}</span>
                                        </div>
                                    </div>
                                @endif

                                @if ($needsAlt)
                                    <span class="absolute bottom-1.5 right-1.5 rounded-full bg-amber-600/90 px-2 py-0.5 text-[10px] font-medium text-white">
                                        no alt
                                    </span>
                                @endif
                            </div>
                            <div class="p-2">
                                <p class="truncate text-xs font-medium text-slate-700">{{ $file->name }}</p>
                                <p class="text-[10px] text-slate-400">
                                    {{ $file->humanSize() }}
                                    @if ($file->width) &middot; {{ $file->width }}&times;{{ $file->height }} @endif
                                </p>
                            </div>
                        </button>

                        {{-- Details: name, alt text, copy link, delete. --}}
                        <div x-show="open" x-cloak @click.outside="open = false"
                             class="absolute inset-0 z-10 flex flex-col gap-2 overflow-y-auto bg-white p-3 text-xs">
                            <form method="POST" action="{{ route('admin.media.update', $file) }}" class="space-y-2">
                                @csrf @method('PATCH')

                                <label class="block">
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Name</span>
                                    <input type="text" name="name" value="{{ $file->name }}" required
                                           class="mt-0.5 w-full rounded border border-slate-300 px-2 py-1">
                                </label>

                                @if ($file->isImage())
                                    <label class="block">
                                        <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Alt text</span>
                                        <textarea name="alt" rows="2" maxlength="255"
                                                  placeholder="Describe the image"
                                                  class="mt-0.5 w-full rounded border px-2 py-1 {{ $needsAlt ? 'border-amber-400' : 'border-slate-300' }}">{{ $file->alt }}</textarea>
                                    </label>
                                @endif

                                <button class="w-full rounded bg-indigo-600 px-2 py-1.5 font-medium text-white">Save</button>
                            </form>

                            <input type="text" readonly value="{{ $file->url }}" onclick="this.select()"
                                   title="Click to select the link"
                                   class="w-full rounded border border-slate-200 bg-slate-50 px-2 py-1 font-mono text-[10px]">

                            <div class="mt-auto flex gap-1">
                                <a href="{{ $file->url }}" target="_blank" rel="noopener"
                                   class="flex-1 rounded border border-slate-300 px-2 py-1 text-center">Open</a>
                                <form method="POST" action="{{ route('admin.media.destroy', $file) }}"
                                      onsubmit="return confirm('Delete this file permanently? Anywhere it is used will show a broken image.')" class="flex-1">
                                    @csrf @method('DELETE')
                                    <button class="w-full rounded border border-rose-300 px-2 py-1 text-rose-600">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-slate-100 p-4">{{ $files->links() }}</div>
        @endif
    </x-admin.card>
@endsection
