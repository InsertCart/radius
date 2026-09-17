@extends('theme::layout')

@section('content')
    @region('blog_index')

    <div class="sf-wrap">
        <nav class="sf-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <b>Blog</b>
        </nav>

        <header class="sf-phead">
            <h1>The reading desk</h1>
            <p>News, guides and recommendations from the team.</p>
        </header>

        <div class="sf-listing">
            @include('theme::blog.sidebar')

            <div>
                <form method="GET" class="sf-toolbar" role="search" data-radius-search="post">
                    <label for="sf-blog-q" class="sf-sr">Search posts</label>
                    <input type="search" name="q" id="sf-blog-q" value="{{ $query }}" placeholder="Search posts" class="sf-input" autocomplete="off">
                    <button class="sf-btn sf-btn--ghost">Search</button>
                </form>
                @searchScripts

                @if ($posts->isEmpty())
                    <p class="sf-empty">{{ $query ? 'No posts match that search.' : 'No posts published yet.' }}</p>
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
    @endregion
@endsection
