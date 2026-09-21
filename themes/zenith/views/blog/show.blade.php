@extends('theme::layout')

@section('content')
    <article class="zn-wrap zn-wrap--narrow" style="padding-top: 30px; padding-bottom: 70px;">
        <nav class="zn-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <a href="{{ route('blog.index') }}">Journal</a>
            @if ($post->category)
                <span>/</span>
                <a href="{{ $post->category->url() }}">{{ $post->category->name }}</a>
            @endif
            <span>/</span>
            <b style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $post->title }}</b>
        </nav>

        <header style="margin-bottom: 36px;">
            @if ($post->category)
                <div style="margin-bottom: 12px;">
                    <a href="{{ $post->category->url() }}" class="zn-badge zn-badge--accent">{{ $post->category->name }}</a>
                </div>
            @endif

            <h1 style="font-size: clamp(32px, 5vw, 52px); font-weight: 800; line-height: 1.15; margin-bottom: 18px; letter-spacing: -0.03em;">
                {{ $post->title }}
            </h1>

            <div style="display: flex; align-items: center; gap: 12px; font-size: 13.5px; color: var(--zn-muted);">
                @if ($post->author)
                    <span style="font-weight: 600; color: var(--zn-text);">{{ $post->author->name }}</span>
                    <span>&bull;</span>
                @endif
                <time datetime="{{ $post->published_at?->toDateString() }}">{{ format_date($post->published_at) }}</time>
                @if ($post->reading_minutes)
                    <span>&bull;</span>
                    <span>{{ $post->reading_minutes }} min read</span>
                @endif
            </div>
        </header>

        @if ($post->imageUrl())
            <div style="width: 100%; aspect-ratio: 16 / 9; border-radius: var(--zn-radius-xl); overflow: hidden; margin-bottom: 40px; border: 1px solid var(--zn-border);">
                <img src="{{ $post->imageUrl('large') ?: $post->imageUrl() }}" alt="{{ $post->title }}" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
        @endif

        <div class="prose-content" style="font-size: 17px; line-height: 1.8; color: var(--zn-text);">
            {!! $post->content !!}
        </div>

        @if ($post->tags->isNotEmpty())
            <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 40px; padding-top: 24px; border-top: 1px solid var(--zn-border);">
                @foreach ($post->tags as $tag)
                    <a href="{{ $tag->url() }}" style="font-size: 12.5px; font-weight: 600; padding: 4px 12px; border-radius: var(--zn-radius-pill); background: var(--zn-bg-alt); color: var(--zn-text-secondary);">
                        #{{ $tag->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </article>

    @if ($related->isNotEmpty())
        <section style="background: var(--zn-bg-alt); border-top: 1px solid var(--zn-border); padding: 70px 0;">
            <div class="zn-wrap">
                <div class="zn-section-head">
                    <div>
                        <div class="zn-section-head__kicker">FURTHER READING</div>
                        <h2 class="zn-section-head__title">Related Stories</h2>
                    </div>
                </div>

                <div class="zn-grid--journal">
                    @foreach ($related->take(3) as $item)
                        @include('theme::partials.post-card', ['post' => $item])
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
