@extends('admin.layout')
@section('title', $category->exists ? 'Edit category' : 'New category')

@section('content')
    <form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
        @csrf
        @if ($category->exists) @method('PUT') @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-admin.card>
                    <div class="space-y-5">
                        <x-form.field label="Type" name="type" required>
                            <x-form.select name="type" :options="$availableTypes" :value="$category->type" />
                        </x-form.field>

                        <x-form.field label="Name" name="name" required>
                            <x-form.input name="name" :value="$category->name" required autofocus />
                        </x-form.field>

                        <x-form.field label="Slug" name="slug">
                            <x-form.input name="slug" :value="$category->slug" />
                        </x-form.field>

                        <x-form.field label="Description" name="description">
                            <x-form.textarea name="description" :value="$category->description" rows="4" />
                        </x-form.field>
                    </div>
                </x-admin.card>

                @include('admin.partials.seo-panel', ['model' => $category])
            </div>

            <div class="space-y-6">
                <x-admin.card title="Options">
                    <div class="space-y-4">
                        <x-form.field label="Parent category" name="parent_id">
                            <x-form.select name="parent_id" :options="$parents" :value="$category->parent_id" placeholder="None (top level)" />
                        </x-form.field>

                        <x-form.field label="Sort order" name="sort_order">
                            <x-form.input name="sort_order" type="number" :value="$category->sort_order ?? 0" />
                        </x-form.field>

                        <x-form.toggle name="is_active" label="Visible on the site" :checked="(bool) $category->is_active" />
                        <x-form.toggle name="show_in_menu" label="Offer in menus" :checked="(bool) $category->show_in_menu" />
                    </div>

                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ $category->exists ? 'Save changes' : 'Create category' }}
                        </button>
                    </div>
                </x-admin.card>

                <x-admin.card title="Image">
                    <x-form.media name="image" label="" :value="$category->image" />
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection
