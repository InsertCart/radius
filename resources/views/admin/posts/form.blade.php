@extends('admin.layout')
@section('title', $post->exists ? 'Edit post' : 'New post')

@section('content')
    <form method="POST"
          action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}">
        @csrf
        @if ($post->exists) @method('PUT') @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-admin.card>
                    <div class="space-y-5">
                        <x-form.field label="Title" name="title" required>
                            <x-form.input name="title" :value="$post->title" required autofocus />
                        </x-form.field>

                        <x-form.field label="Slug" name="slug" help="Leave blank to generate one from the title.">
                            <x-form.input name="slug" :value="$post->slug" />
                        </x-form.field>

                        <x-form.field label="Excerpt" name="excerpt" help="A short summary used in listings and as the default meta description.">
                            <x-form.textarea name="excerpt" :value="$post->excerpt" rows="2" />
                        </x-form.field>

                        <x-form.field label="Content" name="content">
                            <x-form.richtext name="content" :value="$post->rawContent()" label="Post content" min-height="420px" />
                        </x-form.field>
                    </div>
                </x-admin.card>

                @include('admin.partials.seo-panel', ['model' => $post])
            </div>

            <div class="space-y-6">
                <x-admin.card title="Publish">
                    <div class="space-y-4">
                        <x-form.field label="Status" name="status">
                            <x-form.select name="status" :value="$post->status" :options="[
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ]" />
                        </x-form.field>

                        <x-form.field label="Publish date" name="published_at"
                                      help="Set a future date to schedule the post.">
                            <x-form.input name="published_at" type="datetime-local"
                                          :value="$post->published_at?->format('Y-m-d\TH:i')" />
                        </x-form.field>

                        <x-form.toggle name="is_featured" label="Feature this post" :checked="(bool) $post->is_featured" />
                        <x-form.toggle name="allow_comments" label="Allow comments" :checked="(bool) $post->allow_comments" />
                    </div>

                    <div class="mt-5 flex gap-2 border-t border-slate-100 pt-4">
                        <button type="submit" class="flex-1 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                            {{ $post->exists ? 'Save changes' : 'Create post' }}
                        </button>
                    </div>
                </x-admin.card>

                <x-admin.card title="Organisation">
                    <div class="space-y-4">
                        <x-form.field label="Category" name="category_id">
                            <x-form.select name="category_id" :options="$categories" :value="$post->category_id"
                                           placeholder="Uncategorised" />
                        </x-form.field>

                        <x-form.field label="Tags" name="tags" help="Comma separated. New tags are created automatically.">
                            <x-form.input name="tags" :value="$tagList" />
                        </x-form.field>
                    </div>
                </x-admin.card>

                @include('admin.partials.builder-panel', ['model' => $post, 'builderType' => 'post'])

                <x-admin.card title="Featured image">
                    <x-form.media name="featured_image" label="" :value="$post->featured_image" />
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection
