@extends('admin.layout')
@section('title', 'Products')
@section('subtitle', $products->total().' product'.($products->total() === 1 ? '' : 's'))

@section('content')
    <x-admin.card bodyClass="">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <form method="GET" class="flex flex-1 flex-wrap gap-2">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name or SKU"
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
                <label class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="checkbox" name="low_stock" value="1" @checked($filters['low_stock'] ?? false)
                           class="h-4 w-4 rounded border-slate-300 text-indigo-600">
                    Low stock
                </label>
                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Filter</button>
            </form>

            <a href="{{ route('admin.products.create') }}"
               class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                New product
            </a>
        </div>

        @if ($products->isEmpty())
            <x-admin.empty message="No products match this filter." action="Add a product" :actionUrl="route('admin.products.create')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Product</th>
                            <th class="px-4 py-2.5 font-medium">Price</th>
                            <th class="px-4 py-2.5 font-medium">Stock</th>
                            <th class="px-4 py-2.5 font-medium">Status</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($products as $product)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                                            @if ($product->imageUrl())
                                                <img src="{{ $product->imageUrl() }}" alt="" class="h-full w-full object-cover" loading="lazy">
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.products.edit', $product) }}" class="font-medium text-slate-900 hover:text-indigo-600">
                                                {{ $product->name }}
                                            </a>
                                            <p class="text-xs text-slate-400">
                                                {{ $product->sku ?: '/'.$product->slug }}
                                                @if ($product->type !== 'simple') &middot; {{ ucfirst($product->type) }} @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($product->isOnSale())
                                        <span class="font-medium text-slate-900">{{ money($product->sale_price) }}</span>
                                        <span class="text-xs text-slate-400 line-through">{{ money($product->price) }}</span>
                                    @else
                                        <span class="font-medium text-slate-900">{{ money($product->price) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if (! $product->manage_stock)
                                        <span class="text-xs text-slate-400">Not tracked</span>
                                    @else
                                        <x-admin.badge :color="$product->stock > 5 ? 'green' : ($product->stock > 0 ? 'amber' : 'red')">
                                            {{ $product->stock }}
                                        </x-admin.badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-admin.badge :color="$product->status === 'published' ? 'green' : 'gray'">
                                        {{ ucfirst($product->status) }}
                                    </x-admin.badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        @if ($product->status === 'published')
                                            <a href="{{ $product->url() }}" target="_blank" rel="noopener" class="text-xs text-slate-500 hover:text-indigo-600">View</a>
                                        @endif
                                        @include('admin.partials.builder-link', ['model' => $product, 'builderType' => 'product'])
                                        <form method="POST" action="{{ route('admin.products.destroy', $product) }}"
                                              onsubmit="return confirm('Move this product to trash?')">
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
            <div class="border-t border-slate-100 p-4">{{ $products->links() }}</div>
        @endif
    </x-admin.card>
@endsection
