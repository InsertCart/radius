<article class="sf-post">
    <a href="{{ $post->url() }}" class="sf-post__frame" tabindex="-1" aria-hidden="true">
        @if ($post->imageUrl())
            <img src="{{ $post->imageUrl() }}" alt="" loading="lazy">
        @endif
    </a>

    <div class="sf-post__body">
        @if ($post->category)
            <a href="{{ $post->category->url() }}" class="sf-post__cat">{{ $post->category->name }}</a>
        @endif

        <h3 class="sf-post__title"><a href="{{ $post->url() }}">{{ $post->title }}</a></h3>

        <p class="sf-post__excerpt">{{ $post->summary() }}</p>

        <p class="sf-post__meta">
            {{ format_date($post->published_at) }}
            @if ($post->reading_minutes)
                &middot; {{ $post->reading_minutes }} min read
            @endif
        </p>
    </div>
</article>
