<article class="zn-post-card">
    <a href="{{ $post->url() }}" class="zn-post-card__media" tabindex="-1" aria-hidden="true">
        @if ($post->imageUrl())
            <img src="{{ $post->imageUrl('medium') ?: $post->imageUrl() }}" alt="{{ $post->title }}" loading="lazy">
        @else
            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--zn-bg-alt); color: var(--zn-subtle);">
                @include('theme::partials.icon', ['name' => 'mail', 'class' => 'w-8 h-8'])
            </div>
        @endif
    </a>

    <div class="zn-post-card__body">
        <div class="zn-post-card__meta">
            @if ($post->category)
                <span class="zn-post-card__tag">{{ $post->category->name }}</span>
                <span>&bull;</span>
            @endif
            <span>{{ format_date($post->published_at) }}</span>
            @if ($post->reading_minutes)
                <span>&bull;</span>
                <span>{{ $post->reading_minutes }} min read</span>
            @endif
        </div>

        <h3 class="zn-post-card__title">
            <a href="{{ $post->url() }}">{{ $post->title }}</a>
        </h3>

        <p class="zn-post-card__excerpt">
            {{ $post->summary() }}
        </p>

        <a href="{{ $post->url() }}" class="zn-post-card__readmore">
            <span>Read Story</span>
            @include('theme::partials.icon', ['name' => 'arrow-right'])
        </a>
    </div>
</article>
