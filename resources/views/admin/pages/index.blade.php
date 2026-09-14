@extends('admin.layout')
@section('title', 'Pages')

@section('content')
    <x-admin.card bodyClass="">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <form method="GET" class="flex flex-1 flex-wrap gap-2">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search pages"
                       class="min-w-[12rem] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Any status</option>
                    <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
                    <option value="published" @selected(($filters['status'] ?? '') === 'published')>Published</option>
                </select>
                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Filter</button>
            </form>

            <a href="{{ route('admin.pages.create') }}"
               class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                New page
            </a>
        </div>

        @if ($pages->isEmpty())
            <x-admin.empty message="No pages yet." action="Create a page" :actionUrl="route('admin.pages.create')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Title</th>
                            <th class="px-4 py-2.5 font-medium">Parent</th>
                            <th class="px-4 py-2.5 font-medium">Template</th>
                            <th class="px-4 py-2.5 font-medium">Status</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($pages as $page)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.pages.edit', $page) }}" class="font-medium text-slate-900 hover:text-indigo-600">
                                        {{ $page->title }}
                                    </a>
                                    @if ($page->is_homepage)
                                        <x-admin.badge color="indigo" class="ml-1">Homepage</x-admin.badge>
                                    @endif
                                    <p class="text-xs text-slate-400">/{{ $page->slug }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $page->parent?->title ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $page->template ?: 'Default' }}</td>
                                <td class="px-4 py-3">
                                    <x-admin.badge :color="$page->status === 'published' ? 'green' : 'gray'">
                                        {{ ucfirst($page->status) }}
                                    </x-admin.badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        @include('admin.partials.builder-link', ['model' => $page, 'builderType' => 'page'])

                                        <form method="POST" action="{{ route('admin.pages.destroy', $page) }}"
                                              onsubmit="return confirm('Move this page to trash?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-rose-600 hover:underline">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $pages->links() }}</div>
        @endif
    </x-admin.card>
@endsection
