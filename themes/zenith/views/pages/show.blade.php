@extends('theme::layout')

@section('content')
    <article class="zn-wrap zn-wrap--narrow" style="padding-top: 40px; padding-bottom: 80px;">
        <header style="margin-bottom: 36px;">
            <h1 style="font-size: clamp(32px, 4.5vw, 48px); font-weight: 800; line-height: 1.2; letter-spacing: -0.03em;">
                {{ $page->title }}
            </h1>
        </header>

        @if ($page->imageUrl())
            <div style="width: 100%; aspect-ratio: 16 / 9; border-radius: var(--zn-radius-xl); overflow: hidden; margin-bottom: 40px; border: 1px solid var(--zn-border);">
                <img src="{{ $page->imageUrl('large') ?: $page->imageUrl() }}" alt="{{ $page->title }}" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
        @endif

        @region('before_content')@endregion

        <div class="prose-content" style="font-size: 16.5px; line-height: 1.8; color: var(--zn-text);">
            {!! $page->content !!}
        </div>

        @region('after_content')@endregion
    </article>
@endsection
