@php $layout = $settings['layout'] ?? 'grid'; @endphp

@if ($posts->isEmpty())
    <p class="cb-empty">{{ $settings['empty_text'] ?? 'No posts yet.' }}</p>
@else
    <div class="cb-posts cb-posts--{{ $layout }}">
        @foreach ($posts as $post)
            <article class="cb-post-card">
                @if (! empty($settings['show_image']) && $post->imageUrl())
                    <a class="cb-post-card__media" href="{{ $post->url() }}">
                        <img src="{{ $post->imageUrl() }}" alt="{{ $post->title }}" loading="lazy" decoding="async">
                    </a>
                @endif

                <div class="cb-post-card__body">
                    @if (! empty($settings['show_category']) && $post->category)
                        <a class="cb-post-card__category" href="{{ $post->category->url() }}">{{ $post->category->name }}</a>
                    @endif

                    <h3 class="cb-post-card__title">
                        <a href="{{ $post->url() }}">{{ $post->title }}</a>
                    </h3>

                    @if (! empty($settings['show_excerpt']))
                        <p class="cb-post-card__excerpt">{{ $post->summary(140) }}</p>
                    @endif

                    @if (! empty($settings['show_date']) || ! empty($settings['show_author']))
                        <p class="cb-post-card__meta">
                            @if (! empty($settings['show_author']) && $post->author)
                                <span>{{ $post->author->name }}</span>
                            @endif
                            @if (! empty($settings['show_date']))
                                <time datetime="{{ $post->published_at?->toDateString() }}">{{ format_date($post->published_at) }}</time>
                            @endif
                        </p>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
@endif
