@extends('theme::layout')

@section('content')
    <article class="sf-wrap sf-wrap--narrow sf-article">
        <nav class="sf-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <a href="{{ route('blog.index') }}">Blog</a>
            @if ($post->category)
                <span>/</span>
                <a href="{{ $post->category->url() }}">{{ $post->category->name }}</a>
            @endif
        </nav>

        @if ($post->category)
            <p class="sf-post__cat">{{ $post->category->name }}</p>
        @endif

        <h1>{{ $post->title }}</h1>

        <div class="sf-article__meta">
            @if ($post->author)
                <span>By {{ $post->author->name }}</span>
                <span>&middot;</span>
            @endif
            <time datetime="{{ $post->published_at?->toDateString() }}">{{ format_date($post->published_at) }}</time>
            @if ($post->reading_minutes)
                <span>&middot;</span>
                <span>{{ $post->reading_minutes }} min read</span>
            @endif
        </div>

        @if ($post->imageUrl())
            <img src="{{ $post->imageUrl() }}" alt="{{ $post->title }}" class="sf-article__cover">
        @endif

        <div class="prose-content">{!! $post->content !!}</div>

        @if ($post->tags->isNotEmpty())
            <div class="sf-tags">
                @foreach ($post->tags as $tag)
                    <a href="{{ $tag->url() }}" class="sf-tag">#{{ $tag->name }}</a>
                @endforeach
            </div>
        @endif
    </article>

    @if ($related->isNotEmpty())
        <section class="sf-section sf-section--soft">
            <div class="sf-wrap">
                <div class="sf-section__head">
                    <div><h2 class="sf-section__title">Keep reading</h2></div>
                </div>
                <div class="sf-grid sf-grid--posts">
                    @foreach ($related as $item)
                        @include('theme::partials.post-card', ['post' => $item])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($post->allow_comments)
        <section class="sf-wrap sf-wrap--narrow sf-section">
            <h2 class="sf-section__title">
                {{ $comments->count() }} {{ Str::plural('comment', $comments->count()) }}
            </h2>

            <div class="sf-mt-lg">
                @forelse ($comments as $comment)
                    <div class="sf-comment">
                        <div class="sf-comment__head">
                            <p class="sf-b">{{ $comment->displayName() }}</p>
                            <time class="sf-small sf-muted">{{ $comment->created_at->diffForHumans() }}</time>
                        </div>
                        <p class="sf-comment__body">{{ $comment->body }}</p>

                        @foreach ($comment->replies as $reply)
                            <div class="sf-comment__reply">
                                <div class="sf-comment__head">
                                    <p class="sf-b sf-small">{{ $reply->displayName() }}</p>
                                    <time class="sf-small sf-muted">{{ $reply->created_at->diffForHumans() }}</time>
                                </div>
                                <p class="sf-comment__body">{{ $reply->body }}</p>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <p class="sf-muted sf-small">Be the first to comment.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('blog.comment', $post->slug) }}" class="sf-panel sf-mt-lg">
                @csrf
                <input type="text" name="website" tabindex="-1" autocomplete="off" class="sf-hp" aria-hidden="true">

                <p class="sf-panel__title">Join the conversation</p>

                @guest
                    <div class="sf-fields sf-fields--2">
                        <div>
                            <label for="sf-comment-name" class="sf-label">Your name</label>
                            <input type="text" name="author_name" id="sf-comment-name" required class="sf-input"
                                   value="{{ old('author_name') }}">
                        </div>
                        <div>
                            <label for="sf-comment-email" class="sf-label">Your email (not published)</label>
                            <input type="email" name="author_email" id="sf-comment-email" required class="sf-input"
                                   value="{{ old('author_email') }}">
                        </div>
                    </div>
                @endguest

                <div class="sf-field sf-mt">
                    <label for="sf-comment-body" class="sf-label">Comment</label>
                    <textarea name="body" id="sf-comment-body" rows="4" required class="sf-textarea">{{ old('body') }}</textarea>
                </div>

                <button class="sf-btn sf-btn--primary sf-mt">Post comment</button>
                <p class="sf-help sf-mt">Comments are reviewed before they appear.</p>
            </form>
        </section>
    @endif
@endsection
