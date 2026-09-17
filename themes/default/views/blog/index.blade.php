@extends('theme::layout')

@section('content')
    @region('blog_index')

    <div class="mx-auto max-w-6xl px-4 py-14">
        <header class="mb-10">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Blog</h1>
            <p class="mt-2 text-slate-600">Thoughts, updates and announcements.</p>
        </header>

        <div class="grid gap-10 lg:grid-cols-4">
            <div class="lg:col-span-3">
                <form method="GET" class="mb-8 max-w-sm" role="search" data-radius-search="post">
                    <input type="search" name="q" value="{{ $query }}" placeholder="Search posts" autocomplete="off"
                           aria-label="Search posts"
                           class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm">
                </form>
                @searchScripts

                @if ($posts->isEmpty())
                    <p class="rounded-2xl border border-dashed border-slate-300 py-16 text-center text-slate-500">
                        {{ $query ? 'No posts match that search.' : 'No posts published yet.' }}
                    </p>
                @else
                    <div class="grid gap-6 sm:grid-cols-2">
                        @foreach ($posts as $post)
                            @include('theme::partials.post-card', ['post' => $post])
                        @endforeach
                    </div>

                    {{ $posts->links('theme::partials.pagination') }}
                @endif
            </div>

            @include('theme::blog.sidebar')
        </div>
    </div>
    @endregion
@endsection
