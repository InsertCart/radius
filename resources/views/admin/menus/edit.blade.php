@extends('admin.layout')
@section('title', $menu->name)
@section('subtitle', 'Menu slug: '.$menu->slug)

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-admin.card title="Menu items" bodyClass="">
                @if ($items->isEmpty())
                    <x-admin.empty message="This menu is empty. Add an item using the panel on the right." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($items as $item)
                            <li class="p-4">
                                @include('admin.menus.item', ['item' => $item, 'depth' => 0])

                                @foreach ($item->children as $child)
                                    <div class="mt-3 border-l-2 border-slate-100 pl-4">
                                        @include('admin.menus.item', ['item' => $child, 'depth' => 1])
                                    </div>
                                @endforeach
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-admin.card>
        </div>

        <div class="space-y-6">
            <x-admin.card title="Add an item">
                <form method="POST" action="{{ route('admin.menus.items.store', $menu) }}" class="space-y-4"
                      x-data="{ type: 'custom' }">
                    @csrf

                    <x-form.field label="Label" name="label" required>
                        <x-form.input name="label" required />
                    </x-form.field>

                    <x-form.field label="Links to" name="type" required>
                        <x-form.select name="type" x-model="type" value="custom" :options="[
                            'custom' => 'A custom URL',
                            'page' => 'A page',
                            'post' => 'A blog post',
                            'category' => 'A category',
                            'product' => 'A product',
                        ]" />
                    </x-form.field>

                    <div x-show="type === 'custom'" x-cloak>
                        <x-form.field label="URL" name="url">
                            <x-form.input name="url" placeholder="/about or https://example.com" />
                        </x-form.field>
                    </div>

                    @foreach ($linkTargets as $targetType => $options)
                        <div x-show="type === '{{ $targetType }}'" x-cloak>
                            <x-form.field label="Choose {{ $targetType }}" name="reference_id">
                                <select name="reference_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    @foreach ($options as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-form.field>
                        </div>
                    @endforeach

                    <x-form.field label="Parent item" name="parent_id">
                        <select name="parent_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">None (top level)</option>
                            @foreach ($items as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->label }}</option>
                            @endforeach
                        </select>
                    </x-form.field>

                    <x-form.field label="Open in" name="target">
                        <x-form.select name="target" value="_self" :options="['_self' => 'The same tab', '_blank' => 'A new tab']" />
                    </x-form.field>

                    <x-form.field label="Only show when this module is on" name="requires_module"
                                  help="Optional. The item hides itself when the module is switched off.">
                        <x-form.select name="requires_module" :options="$moduleOptions" placeholder="Always show" />
                    </x-form.field>

                    <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Add item
                    </button>
                </form>
            </x-admin.card>

            <x-admin.card title="Menu settings">
                <form method="POST" action="{{ route('admin.menus.update', $menu) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-form.field label="Name" name="name" required>
                        <x-form.input name="name" :value="$menu->name" required />
                    </x-form.field>
                    <x-form.field label="Slug" name="slug" required>
                        <x-form.input name="slug" :value="$menu->slug" required />
                    </x-form.field>
                    <button class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                        Save
                    </button>
                </form>
            </x-admin.card>
        </div>
    </div>
@endsection
