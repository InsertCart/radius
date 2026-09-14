@extends('admin.layout')
@section('title', 'Categories')

@section('content')
    @if (count($availableTypes) > 1)
        <div class="mb-4 flex gap-2">
            @foreach ($availableTypes as $key => $label)
                <a href="{{ route('admin.categories.index', ['type' => $key]) }}"
                   @class([
                       'rounded-lg px-4 py-2 text-sm font-medium transition',
                       'bg-indigo-600 text-white' => $type === $key,
                       'bg-white text-slate-600 hover:bg-slate-50' => $type !== $key,
                   ])>{{ $label }}</a>
            @endforeach
        </div>
    @endif

    <x-admin.card bodyClass="">
        <div class="flex items-center justify-between border-b border-slate-100 p-4">
            <p class="text-sm text-slate-500">{{ $categories->total() }} {{ $availableTypes[$type] ?? '' }} categories</p>
            <a href="{{ route('admin.categories.create', ['type' => $type]) }}"
               class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                New category
            </a>
        </div>

        @if ($categories->isEmpty())
            <x-admin.empty message="No categories yet." action="Create one" :actionUrl="route('admin.categories.create', ['type' => $type])" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Name</th>
                            <th class="px-4 py-2.5 font-medium">Parent</th>
                            <th class="px-4 py-2.5 font-medium">Items</th>
                            <th class="px-4 py-2.5 font-medium">Status</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($categories as $category)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.categories.edit', $category) }}" class="font-medium text-slate-900 hover:text-indigo-600">
                                        {{ $category->name }}
                                    </a>
                                    <p class="text-xs text-slate-400">/{{ $category->slug }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $category->parent?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ $category->posts_count ?? $category->products_count ?? 0 }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-admin.badge :color="$category->is_active ? 'green' : 'gray'">
                                        {{ $category->is_active ? 'Active' : 'Hidden' }}
                                    </x-admin.badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                                          onsubmit="return confirm('Delete this category?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-rose-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $categories->links() }}</div>
        @endif
    </x-admin.card>
@endsection
