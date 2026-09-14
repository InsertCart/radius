@extends('admin.layout')
@section('title', 'SEO')
@section('subtitle', 'Per-page settings for routes with no editable content behind them')

@section('content')
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <a href="{{ $sitemapUrl }}" target="_blank" rel="noopener"
           class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-indigo-300">
            <p class="text-sm font-medium text-slate-900">sitemap.xml</p>
            <p class="mt-1 text-xs text-slate-500">Regenerated automatically as content changes</p>
        </a>
        <a href="{{ $robotsUrl }}" target="_blank" rel="noopener"
           class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-indigo-300">
            <p class="text-sm font-medium text-slate-900">robots.txt</p>
            <p class="mt-1 text-xs text-slate-500">Edit the body under Settings &rarr; SEO</p>
        </a>
        <a href="{{ route('admin.seo.redirects') }}"
           class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-indigo-300">
            <p class="text-sm font-medium text-slate-900">Redirects</p>
            <p class="mt-1 text-xs text-slate-500">Keep rankings when URLs change</p>
        </a>
    </div>

    <div class="space-y-6">
        @foreach ($routes as $key => $route)
            @php $row = $meta[$key] ?? null; @endphp

            <form method="POST" action="{{ route('admin.seo.meta.update', $key) }}">
                @csrf @method('PUT')

                <x-admin.card :title="$route['label']" :description="$route['url']">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.field label="Meta title" :name="'meta_title'">
                            <input type="text" name="meta_title" value="{{ $row?->meta_title }}"
                                   class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </x-form.field>

                        <x-form.field label="Schema type">
                            <select name="schema_type" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Default for this page</option>
                                @foreach ($schemaTypes as $value => $label)
                                    <option value="{{ $value }}" @selected($row?->schema_type === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-form.field>

                        <div class="sm:col-span-2">
                            <x-form.field label="Meta description">
                                <textarea name="meta_description" rows="2"
                                          class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $row?->meta_description }}</textarea>
                            </x-form.field>
                        </div>

                        <x-form.field label="Keywords">
                            <input type="text" name="meta_keywords" value="{{ $row?->meta_keywords }}"
                                   class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </x-form.field>

                        <x-form.field label="Canonical URL">
                            <input type="url" name="canonical_url" value="{{ $row?->canonical_url }}"
                                   class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </x-form.field>

                        <div class="sm:col-span-2">
                            <x-form.media name="og_image" label="Social share image" :value="$row?->og_image" />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="hidden" name="noindex" value="0">
                                <input type="checkbox" name="noindex" value="1" @checked($row?->noindex)
                                       class="h-4 w-4 rounded border-slate-300 text-indigo-600">
                                Hide this page from search engines
                            </label>
                        </div>
                    </div>

                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Save
                        </button>
                    </div>
                </x-admin.card>
            </form>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.seo.sitemap.ping') }}" class="mt-6">
        @csrf
        <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
            Tell search engines the sitemap changed
        </button>
    </form>
@endsection
