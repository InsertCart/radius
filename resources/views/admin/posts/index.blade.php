@extends('admin.layout')
@section('title', 'Posts')
@section('subtitle', $posts->total().' post'.($posts->total() === 1 ? '' : 's'))

@section('content')
    <x-admin.card bodyClass="">
        <x-slot:actions></x-slot:actions>

        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <form method="GET" class="flex flex-1 flex-wrap gap-2">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search posts"
                       class="min-w-[12rem] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Any status</option>
                    @foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="category" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Any category</option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['category'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Filter</button>
            </form>

            <a href="{{ route('admin.posts.create') }}"
               class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                New post
            </a>
        </div>

        @if ($posts->isEmpty())
            <x-admin.empty message="No posts match this filter." action="Write your first post" :actionUrl="route('admin.posts.create')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Title</th>
                            <th class="px-4 py-2.5 font-medium">Category</th>
                            <th class="px-4 py-2.5 font-medium">Author</th>
                            <th class="px-4 py-2.5 font-medium">Status</th>
                            <th class="px-4 py-2.5 font-medium">Published</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($posts as $post)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.posts.edit', $post) }}" class="font-medium text-slate-900 hover:text-indigo-600">
                                        {{ $post->title }}
                                    </a>
                                    <p class="text-xs text-slate-400">/{{ $post->slug }} &middot; {{ number_format($post->views) }} views</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $post->category?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $post->author?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <x-admin.badge :color="$post->status === 'published' ? 'green' : 'gray'">
                                        {{ ucfirst($post->status) }}
                                    </x-admin.badge>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500">
                                    {{ $post->published_at ? format_date($post->published_at) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        @if ($post->isPublished())
                                            <a href="{{ $post->url() }}" target="_blank" rel="noopener"
                                               class="text-xs text-slate-500 hover:text-indigo-600">View</a>
                                        @endif
                                        @include('admin.partials.builder-link', ['model' => $post, 'builderType' => 'post'])
                                        <form method="POST" action="{{ route('admin.posts.destroy', $post) }}"
                                              onsubmit="return confirm('Move this post to trash?')">
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

            <div class="border-t border-slate-100 p-4">{{ $posts->links() }}</div>
        @endif
    </x-admin.card>
@endsection
