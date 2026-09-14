@extends('theme::layout')

@section('content')
    <article class="mx-auto max-w-3xl px-4 py-14">
        <header class="mb-8">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{{ $page->title }}</h1>
        </header>

        @if ($page->imageUrl())
            <img src="{{ $page->imageUrl() }}" alt="{{ $page->title }}" class="mb-8 w-full rounded-2xl">
        @endif

        @region('before_content')@endregion

        {{-- Authored in the admin panel by a trusted user, so rendered as
             HTML rather than escaped. When a visual layout has been built
             for this page, the accessor returns that instead - which is why
             a theme needs no changes to support the builder. --}}
        <div class="prose-content text-slate-700">{!! $page->content !!}</div>

        @region('after_content')@endregion
    </article>
@endsection
