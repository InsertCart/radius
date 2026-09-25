@extends('theme::layout')

@section('content')
    <article class="mx-auto max-w-3xl px-4 py-14">
        <header class="mb-8">
            @if ($post->category)
                <a href="{{ $post->category->url() }}" class="text-sm font-semibold uppercase tracking-wide text-brand">
                    {{ $post->category->name }}
                </a>
            @endif

            <h1 class="mt-2 text-3xl font-bold leading-tight tracking-tight text-slate-900 sm:text-4xl">
                {{ $post->title }}
            </h1>

            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-slate-500">
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
        </header>

        @if ($post->imageUrl())
            <img src="{{ $post->imageUrl() }}" alt="{{ $post->title }}" class="mb-8 w-full rounded-2xl">
        @endif

        <div class="prose-content text-slate-700">{!! rich_content($post->content) !!}</div>

        @if ($post->tags->isNotEmpty())
            <div class="mt-10 flex flex-wrap gap-2 border-t border-slate-100 pt-6">
                @foreach ($post->tags as $tag)
                    <a href="{{ $tag->url() }}" class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600 hover:bg-slate-200">
                        #{{ $tag->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </article>

    @if ($related->isNotEmpty())
        <section class="border-t border-slate-100 bg-slate-50">
            <div class="mx-auto max-w-6xl px-4 py-14">
                <h2 class="mb-6 text-xl font-bold text-slate-900">Keep reading</h2>
                <div class="grid gap-6 md:grid-cols-3">
                    @foreach ($related as $item)
                        @include('theme::partials.post-card', ['post' => $item])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($post->allow_comments)
        <section class="mx-auto max-w-3xl px-4 py-14">
            <h2 class="text-xl font-bold text-slate-900">
                {{ $comments->count() }} {{ Str::plural('comment', $comments->count()) }}
            </h2>

            <div class="mt-6 space-y-6">
                @forelse ($comments as $comment)
                    <div class="rounded-2xl border border-slate-200 p-5">
                        <div class="flex items-baseline justify-between gap-2">
                            <p class="font-medium text-slate-900">{{ $comment->displayName() }}</p>
                            <time class="text-xs text-slate-400">{{ $comment->created_at->diffForHumans() }}</time>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $comment->body }}</p>

                        @foreach ($comment->replies as $reply)
                            <div class="mt-4 border-l-2 border-slate-100 pl-4">
                                <div class="flex items-baseline justify-between gap-2">
                                    <p class="text-sm font-medium text-slate-900">{{ $reply->displayName() }}</p>
                                    <time class="text-xs text-slate-400">{{ $reply->created_at->diffForHumans() }}</time>
                                </div>
                                <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $reply->body }}</p>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Be the first to comment.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('blog.comment', $post->slug) }}" class="mt-8 space-y-4">
                @csrf
                <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                @guest
                    <div class="grid gap-4 sm:grid-cols-2">
                        <input type="text" name="author_name" required placeholder="Your name"
                               value="{{ old('author_name') }}"
                               class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <input type="email" name="author_email" required placeholder="Your email (not published)"
                               value="{{ old('author_email') }}"
                               class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                @endguest

                <textarea name="body" rows="4" required placeholder="Join the conversation"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('body') }}</textarea>

                <button class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white btn-brand">Post comment</button>
                <p class="text-xs text-slate-400">Comments are reviewed before they appear.</p>
            </form>
        </section>
    @endif
@endsection
