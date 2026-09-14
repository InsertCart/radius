@extends('theme::layout')

@section('content')
    <article class="sf-wrap sf-wrap--narrow sf-article">
        <nav class="sf-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <b>{{ $page->title }}</b>
        </nav>

        <h1>{{ $page->title }}</h1>

        @if ($page->imageUrl())
            <img src="{{ $page->imageUrl() }}" alt="{{ $page->title }}" class="sf-article__cover">
        @endif

        @region('before_content')@endregion

        {{-- Authored in the admin panel by a trusted user, so rendered as HTML
             rather than escaped. When a visual layout has been built for this
             page the accessor returns that instead, which is why no theme
             change is needed to support the builder. --}}
        <div class="prose-content">{!! $page->content !!}</div>

        @region('after_content')@endregion
    </article>
@endsection
