@extends('admin.layout')
@section('title', $product->exists ? 'Edit product' : 'New product')

@section('content')
    <form method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
          enctype="multipart/form-data"
          x-data="{ type: @js(old('type', $product->type ?? 'simple')), manageStock: @js((bool) old('manage_stock', $product->manage_stock ?? true)) }">
        @csrf
        @if ($product->exists) @method('PUT') @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-admin.card>
                    <div class="space-y-5">
                        <x-form.field label="Name" name="name" required>
                            <x-form.input name="name" :value="$product->name" required autofocus />
                        </x-form.field>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-form.field label="Slug" name="slug">
                                <x-form.input name="slug" :value="$product->slug" />
                            </x-form.field>

                            <x-form.field label="SKU" name="sku">
                                <x-form.input name="sku" :value="$product->sku" />
                            </x-form.field>
                        </div>

                        <x-form.field label="Short description" name="short_description"
                                      help="Shown in listings and used as the default meta description.">
                            <x-form.textarea name="short_description" :value="$product->short_description" rows="2" />
                        </x-form.field>

                        <x-form.field label="Full description" name="description">
                            <x-form.richtext name="description" :value="$product->rawContent()" label="Product description" min-height="360px" />
                        </x-form.field>
                    </div>
                </x-admin.card>

                <x-admin.card title="Pricing" description="Enter amounts in {{ setting('shop_currency', 'USD') }}.">
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-form.field label="Price" name="price" required>
                            <x-form.input name="price" type="number" step="0.01" min="0" required
                                          :value="$product->exists ? from_minor_units($product->price) : ''" />
                        </x-form.field>

                        <x-form.field label="Sale price" name="sale_price" help="Must be below the price.">
                            <x-form.input name="sale_price" type="number" step="0.01" min="0"
                                          :value="$product->sale_price ? from_minor_units($product->sale_price) : ''" />
                        </x-form.field>

                        <x-form.field label="Cost price" name="cost_price" help="Internal only; never shown.">
                            <x-form.input name="cost_price" type="number" step="0.01" min="0"
                                          :value="$product->cost_price ? from_minor_units($product->cost_price) : ''" />
                        </x-form.field>

                        <x-form.field label="Sale starts" name="sale_starts_at">
                            <x-form.input name="sale_starts_at" type="datetime-local"
                                          :value="$product->sale_starts_at?->format('Y-m-d\TH:i')" />
                        </x-form.field>

                        <x-form.field label="Sale ends" name="sale_ends_at">
                            <x-form.input name="sale_ends_at" type="datetime-local"
                                          :value="$product->sale_ends_at?->format('Y-m-d\TH:i')" />
                        </x-form.field>
                    </div>
                </x-admin.card>

                <x-admin.card title="Inventory">
                    <div class="space-y-5">
                        <x-form.toggle name="manage_stock" label="Track stock levels" :checked="(bool) $product->manage_stock"
                                       x-model="manageStock" />

                        <div x-show="manageStock" x-cloak class="grid gap-5 sm:grid-cols-2">
                            <x-form.field label="Stock quantity" name="stock">
                                <x-form.input name="stock" type="number" :value="$product->stock ?? 0" />
                            </x-form.field>

                            <div class="flex items-end">
                                <x-form.toggle name="allow_backorder" label="Allow backorders" :checked="(bool) $product->allow_backorder"
                                               help="Customers can order beyond the stock on hand." />
                            </div>
                        </div>

                        <div x-show="type === 'digital'" x-cloak>
                            <x-form.field label="Downloadable file" name="digital_upload"
                                          help="Stored outside the public folder. It can only be reached through a paid order — never by its address.">
                                @if ($product->digital_file)
                                    <div class="mb-3 flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
                                         x-data="{ remove: false }">
                                        <svg class="h-5 w-5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                                        </svg>

                                        <span class="min-w-0 flex-1" :class="remove && 'line-through opacity-50'">
                                            <span class="block truncate font-medium text-slate-800">
                                                {{ $product->digital_name ?: basename($product->digital_file) }}
                                            </span>
                                            <span class="text-xs text-slate-500">
                                                @if ($downloadMissing)
                                                    <span class="font-medium text-rose-600">This file is missing from storage — upload it again.</span>
                                                @elseif ($product->digital_size)
                                                    {{ number_format($product->digital_size / 1048576, 2) }} MB
                                                @else
                                                    Stored privately
                                                @endif
                                            </span>
                                        </span>

                                        <label class="flex shrink-0 items-center gap-1.5 text-xs text-rose-600">
                                            <input type="checkbox" name="remove_digital_file" value="1" x-model="remove"
                                                   class="h-4 w-4 rounded border-slate-300 text-rose-600">
                                            Remove
                                        </label>
                                    </div>
                                @endif

                                <input type="file" name="digital_upload"
                                       accept="{{ collect($downloadExtensions)->map(fn ($e) => '.'.$e)->implode(',') }}"
                                       class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-indigo-700">

                                <p class="mt-1.5 text-xs text-slate-500">
                                    {{ $product->digital_file ? 'Uploading a new file replaces the current one. ' : '' }}
                                    Accepted: {{ implode(', ', $downloadExtensions) }} &middot;
                                    up to {{ number_format($downloadMaxKb / 1024) }} MB.
                                    Customers who already bought this product keep the file they paid for.
                                </p>
                            </x-form.field>
                        </div>

                        <div x-show="type !== 'digital'" x-cloak class="grid gap-5 sm:grid-cols-2">
                            <x-form.field label="Weight (kg)" name="weight">
                                <x-form.input name="weight" type="number" step="0.001" min="0" :value="$product->weight" />
                            </x-form.field>

                            <x-form.field label="Dimensions" name="dimensions" help="For example 20 x 15 x 4 cm">
                                <x-form.input name="dimensions" :value="$product->dimensions" />
                            </x-form.field>
                        </div>

                        <div x-show="type !== 'digital'" x-cloak>
                            <x-form.toggle name="requires_shipping" label="This product needs shipping"
                                           :checked="(bool) $product->requires_shipping" />
                        </div>
                    </div>
                </x-admin.card>

                {{-- Variants. The repeater rewrites indexes so the array posts contiguously. --}}
                <div x-show="type === 'variable'" x-cloak>
                    <x-admin.card title="Variants" description="Each row becomes a separately stocked option.">
                        <div x-data="repeater(@js($product->exists ? $product->variants->map(fn ($v) => [
                            'id' => $v->id,
                            'name' => $v->name,
                            'sku' => $v->sku,
                            'options' => collect($v->options ?? [])->map(fn ($val, $k) => $k.': '.$val)->implode(', '),
                            'price' => $v->price ? from_minor_units($v->price) : '',
                            'stock' => $v->stock,
                        ])->values() : []))">
                            <div class="space-y-3">
                                <template x-for="(row, index) in rows" :key="index">
                                    <div class="grid grid-cols-12 items-end gap-2 rounded-xl border border-slate-200 p-3">
                                        <input type="hidden" :name="`variants[${index}][id]`" :value="row.id || ''">

                                        <div class="col-span-12 sm:col-span-3">
                                            <label class="mb-1 block text-xs font-medium text-slate-600">Name</label>
                                            <input type="text" :name="`variants[${index}][name]`" x-model="row.name" required
                                                   class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        </div>
                                        <div class="col-span-12 sm:col-span-3">
                                            <label class="mb-1 block text-xs font-medium text-slate-600">Options</label>
                                            <input type="text" :name="`variants[${index}][options]`" x-model="row.options"
                                                   placeholder="Size: M, Color: Blue"
                                                   class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        </div>
                                        <div class="col-span-6 sm:col-span-2">
                                            <label class="mb-1 block text-xs font-medium text-slate-600">SKU</label>
                                            <input type="text" :name="`variants[${index}][sku]`" x-model="row.sku"
                                                   class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        </div>
                                        <div class="col-span-6 sm:col-span-2">
                                            <label class="mb-1 block text-xs font-medium text-slate-600">Price</label>
                                            <input type="number" step="0.01" :name="`variants[${index}][price]`" x-model="row.price"
                                                   placeholder="Inherit"
                                                   class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        </div>
                                        <div class="col-span-6 sm:col-span-1">
                                            <label class="mb-1 block text-xs font-medium text-slate-600">Stock</label>
                                            <input type="number" :name="`variants[${index}][stock]`" x-model="row.stock"
                                                   class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        </div>
                                        <div class="col-span-6 sm:col-span-1">
                                            <button type="button" @click="remove(index)"
                                                    class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs text-rose-600 hover:bg-rose-50">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <button type="button" @click="add({ id: '', name: '', options: '', sku: '', price: '', stock: 0 })"
                                    class="mt-3 rounded-lg border border-dashed border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                                Add a variant
                            </button>
                        </div>
                    </x-admin.card>
                </div>

                @include('admin.partials.seo-panel', ['model' => $product])
            </div>

            <div class="space-y-6">
                <x-admin.card title="Publish">
                    <div class="space-y-4">
                        <x-form.field label="Status" name="status">
                            <x-form.select name="status" :value="$product->status"
                                           :options="['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']" />
                        </x-form.field>

                        <x-form.field label="Product type" name="type">
                            <x-form.select name="type" :value="$product->type" x-model="type" :options="[
                                'simple' => 'Simple',
                                'variable' => 'Variable (has options)',
                                'digital' => 'Digital download',
                            ]" />
                        </x-form.field>

                        <x-form.field label="Sort order" name="sort_order">
                            <x-form.input name="sort_order" type="number" :value="$product->sort_order ?? 0" />
                        </x-form.field>

                        <x-form.toggle name="is_featured" label="Feature this product" :checked="(bool) $product->is_featured" />
                    </div>

                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ $product->exists ? 'Save changes' : 'Create product' }}
                        </button>
                    </div>
                </x-admin.card>

                <x-admin.card title="Categories">
                    @if ($categories->isEmpty())
                        <p class="text-sm text-slate-500">
                            No shop categories yet.
                            <a href="{{ route('admin.categories.create', ['type' => 'shop']) }}" class="text-indigo-600 hover:underline">Create one</a>.
                        </p>
                    @else
                        <div class="max-h-56 space-y-2 overflow-y-auto">
                            @foreach ($categories as $id => $name)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="categories[]" value="{{ $id }}"
                                           @checked(in_array($id, old('categories', $selectedCategories)))
                                           class="h-4 w-4 rounded border-slate-300 text-indigo-600">
                                    {{ $name }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                </x-admin.card>

                @include('admin.partials.builder-panel', ['model' => $product, 'builderType' => 'product'])

                <x-admin.card title="Featured image">
                    <x-form.media name="featured_image" label="" :value="$product->featured_image" />
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection
