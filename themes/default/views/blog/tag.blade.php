@extends('theme::layout')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-14">
        <header class="mb-10">
            <p class="text-sm text-slate-500">Tagged</p>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">#{{ $tag->name }}</h1>
        </header>

        <div class="grid gap-10 lg:grid-cols-4">
            <div class="lg:col-span-3">
                @if ($posts->isEmpty())
                    <p class="rounded-2xl border border-dashed border-slate-300 py-16 text-center text-slate-500">
                        Nothing tagged with this yet.
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
@endsection
