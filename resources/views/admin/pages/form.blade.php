@extends('admin.layout')
@section('title', $page->exists ? 'Edit page' : 'New page')

@section('content')
    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}">
        @csrf
        @if ($page->exists) @method('PUT') @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-admin.card>
                    <div class="space-y-5">
                        <x-form.field label="Title" name="title" required>
                            <x-form.input name="title" :value="$page->title" required autofocus />
                        </x-form.field>

                        <x-form.field label="Slug" name="slug" help="The page address. Leave blank to generate one.">
                            <x-form.input name="slug" :value="$page->slug" />
                        </x-form.field>

                        <x-form.field label="Content" name="content">
                            <x-form.richtext name="content" :value="$page->rawContent()" label="Page content" min-height="420px" />
                        </x-form.field>
                    </div>
                </x-admin.card>

                @include('admin.partials.seo-panel', ['model' => $page])
            </div>

            <div class="space-y-6">
                <x-admin.card title="Publish">
                    <div class="space-y-4">
                        <x-form.field label="Status" name="status">
                            <x-form.select name="status" :value="$page->status" :options="['draft' => 'Draft', 'published' => 'Published']" />
                        </x-form.field>

                        <x-form.field label="Template" name="template"
                                      help="Templates come from the active theme's views/pages folder.">
                            <x-form.select name="template" :options="$templates" :value="$page->template" />
                        </x-form.field>

                        <x-form.field label="Parent page" name="parent_id">
                            <x-form.select name="parent_id" :options="$parents" :value="$page->parent_id" placeholder="None" />
                        </x-form.field>

                        <x-form.field label="Menu order" name="sort_order">
                            <x-form.input name="sort_order" type="number" :value="$page->sort_order ?? 0" />
                        </x-form.field>

                        <x-form.toggle name="show_in_menu" label="Show in menus" :checked="(bool) $page->show_in_menu" />
                        <x-form.toggle name="is_homepage" label="Use as the homepage" :checked="(bool) $page->is_homepage"
                                       help="Replaces the default homepage. Only one page can hold this." />
                    </div>

                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ $page->exists ? 'Save changes' : 'Create page' }}
                        </button>
                    </div>
                </x-admin.card>

                @include('admin.partials.builder-panel', ['model' => $page, 'builderType' => 'page'])

                <x-admin.card title="Featured image">
                    <x-form.media name="featured_image" label="" :value="$page->featured_image" />
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection
