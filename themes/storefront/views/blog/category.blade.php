@extends('theme::layout')

@section('content')
    <div class="sf-wrap">
        <nav class="sf-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <a href="{{ route('blog.index') }}">Blog</a>
            <span>/</span>
            <b>{{ $category->name }}</b>
        </nav>

        <header class="sf-phead">
            <h1>{{ $category->name }}</h1>
            @if ($category->description)
                <p>{{ $category->description }}</p>
            @endif
        </header>

        <div class="sf-listing">
            @include('theme::blog.sidebar')

            <div>
                @if ($posts->isEmpty())
                    <p class="sf-empty">No posts in this topic yet.</p>
                @else
                    <div class="sf-grid sf-grid--posts">
                        @foreach ($posts as $post)
                            @include('theme::partials.post-card', ['post' => $post])
                        @endforeach
                    </div>

                    {{ $posts->links('theme::partials.pagination') }}
                @endif
            </div>
        </div>
    </div>
@endsection
