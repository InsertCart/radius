@extends('admin.layout')
@section('title', 'Import & export')
@section('subtitle', 'Move content out of this site, or bring it in from another one')

@section('content')
    <div x-data="{ tab: '{{ $report ? 'import' : 'export' }}' }" class="space-y-6">

        {{-- Tabs. Export is the everyday one, so it leads. --}}
        <div class="flex gap-1 rounded-xl border border-slate-200 bg-white p-1 text-sm shadow-sm">
            @foreach (['export' => 'Export', 'import' => 'Import a Radius export', 'wordpress' => 'Import from WordPress'] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                        class="flex-1 rounded-lg px-4 py-2 font-medium transition">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($report)
            @include('admin.transfer.partials.report', ['report' => $report])
        @endif

        {{-- Export ------------------------------------------------------ --}}
        <div x-show="tab === 'export'" x-cloak>
            <form method="POST" action="{{ route('admin.transfer.export') }}">
                @csrf

                <x-admin.card title="What to export"
                              description="Tick what you want. Images used by the content you choose are always included.">
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($resources as $key => $resource)
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                                <input type="checkbox" name="types[]" value="{{ $key }}"
                                       @checked(in_array($key, old('types', ['pages', 'posts', 'categories', 'tags', 'products', 'menus', 'media']), true))
                                       class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600">
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-slate-900">{{ $resource->label() }}</span>
                                    @if ($resource->hint())
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ $resource->hint() }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @error('types')
                        <p class="mt-3 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </x-admin.card>

                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <x-admin.card title="Narrow it down" description="Leave these alone to export everything.">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-600">Status</label>
                                <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="any">Any status</option>
                                    <option value="published" @selected(old('status') === 'published')>Published only</option>
                                    <option value="draft" @selected(old('status') === 'draft')>Drafts only</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600">From</label>
                                    <input type="date" name="from" value="{{ old('from') }}"
                                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600">To</label>
                                    <input type="date" name="to" value="{{ old('to') }}"
                                           class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </div>
                            </div>

                            @error('to')
                                <p class="text-xs text-rose-600">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-slate-500">
                                Dates apply to posts by publication and to everything else by when it was created.
                                Categories, tags and menus ignore them, so imported content still has somewhere to sit.
                            </p>
                        </div>
                    </x-admin.card>

                    <x-admin.card title="File format">
                        <div class="space-y-3" x-data="{ format: '{{ old('format', 'zip') }}' }">
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                                <input type="radio" name="format" value="zip" x-model="format" class="mt-0.5 text-indigo-600">
                                <span>
                                    <span class="block text-sm font-medium text-slate-900">Bundle (.zip)</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">Records and the image files together. Use this to move a site.</span>
                                </span>
                            </label>

                            <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                                <input type="radio" name="format" value="json" x-model="format" class="mt-0.5 text-indigo-600">
                                <span>
                                    <span class="block text-sm font-medium text-slate-900">Single file (.json)</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">Readable, and small. Image addresses travel; the files themselves do not.</span>
                                </span>
                            </label>

                            <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                                <input type="radio" name="format" value="csv" x-model="format" class="mt-0.5 text-indigo-600">
                                <span>
                                    <span class="block text-sm font-medium text-slate-900">Spreadsheet (.csv)</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">One file per type, for Excel. Flat, so nesting and layouts are left out.</span>
                                </span>
                            </label>

                            <div class="space-y-2 border-t border-slate-100 pt-3" x-show="format !== 'csv'">
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" name="include_media_files" value="1" checked
                                           x-bind:disabled="format !== 'zip'"
                                           class="h-4 w-4 rounded border-slate-300 text-indigo-600 disabled:opacity-40">
                                    Include the image files
                                    <span class="text-xs text-slate-400" x-show="format !== 'zip'">(bundle only)</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" name="include_layouts" value="1" checked
                                           class="h-4 w-4 rounded border-slate-300 text-indigo-600">
                                    Include builder layouts
                                </label>
                            </div>
                        </div>
                    </x-admin.card>
                </div>

                <div class="mt-6 flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">
                        A very large site is better exported from the command line:
                        <code class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px]">php artisan cms:export</code>
                    </p>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">
                        Download export
                    </button>
                </div>
            </form>
        </div>

        {{-- Import a Radius bundle ------------------------------------- --}}
        <div x-show="tab === 'import'" x-cloak class="space-y-6">
            @if ($pending)
                <x-admin.card title="Waiting to be imported"
                              description="Uploaded but not applied. Nothing has changed on this site yet.">
                    <ul class="divide-y divide-slate-100">
                        @foreach ($pending as $token => $upload)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $upload['original_name'] }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $upload['kind'] === 'wordpress' ? 'WordPress export' : 'Radius bundle' }}
                                        &middot; {{ number_format($upload['size'] / 1024, 0) }} KB
                                        @if ($upload['uploaded_at'])
                                            &middot; uploaded {{ \Illuminate\Support\Carbon::parse($upload['uploaded_at'])->diffForHumans() }}
                                        @endif
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.transfer.review', $token) }}"
                                       class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">Review</a>
                                    <form method="POST" action="{{ route('admin.transfer.discard', $token) }}"
                                          onsubmit="return confirm('Discard this upload?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-50">Discard</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-admin.card>
            @endif

            <form method="POST" action="{{ route('admin.transfer.upload') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="kind" value="bundle">

                <x-admin.card title="Import a Radius export"
                              description="A .zip or .json file made by the export tab, here or on another Radius site.">
                    <input type="file" name="file" accept=".zip,.json" required
                           class="block w-full rounded-lg border border-slate-300 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium">

                    @error('file')
                        <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
                    @enderror

                    <p class="mt-3 text-xs text-slate-500">
                        Up to {{ $maxUploadMb }} MB@if ($serverLimit && $serverLimit < $maxUploadMb), though this server's PHP accepts only {{ $serverLimit }} MB@endif.
                        Nothing is applied on upload — the next screen shows what the file holds and asks first.
                    </p>

                    <x-slot:actions>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Upload and review
                        </button>
                    </x-slot:actions>
                </x-admin.card>
            </form>
        </div>

        {{-- WordPress --------------------------------------------------- --}}
        <div x-show="tab === 'wordpress'" x-cloak class="space-y-6">
            <form method="POST" action="{{ route('admin.transfer.upload') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="kind" value="wordpress">

                <x-admin.card title="Import from WordPress"
                              description="The .xml file WordPress gives you from Tools &rsaquo; Export.">
                    <input type="file" name="file" accept=".xml" required
                           class="block w-full rounded-lg border border-slate-300 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium">

                    <div class="mt-4 space-y-2 rounded-xl bg-slate-50 p-4 text-xs text-slate-600">
                        <p class="font-medium text-slate-900">What comes across</p>
                        <p>Posts, pages, categories, tags, comments, menus, and WooCommerce products with their prices, stock and categories. Image files are fetched from the old site if you ask for them on the next screen.</p>
                        <p class="pt-1 font-medium text-slate-900">What does not</p>
                        <p>Plugins, widgets, theme settings and passwords. Shortcodes are removed and the words inside them kept, because <code>[contact-form-7]</code> means nothing outside WordPress.</p>
                    </div>

                    @error('file')
                        <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
                    @enderror

                    <x-slot:actions>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Upload and review
                        </button>
                    </x-slot:actions>
                </x-admin.card>
            </form>

            <x-admin.card title="A large WordPress site">
                <p class="text-sm text-slate-600">
                    WordPress splits very large exports into several files; upload and import them one at a time, oldest first.
                    Above roughly a few thousand posts, run it from the command line instead, where there is no upload limit
                    and no request to time out:
                </p>
                <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">php artisan cms:import-wordpress /path/to/export.xml --media</pre>
            </x-admin.card>
        </div>
    </div>
@endsection
