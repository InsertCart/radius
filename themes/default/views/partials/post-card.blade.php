<article class="group overflow-hidden rounded-2xl border border-slate-200 transition hover:shadow-md">
    <a href="{{ $post->url() }}" class="block">
        <div class="aspect-[16/10] overflow-hidden bg-slate-100">
            @if ($post->imageUrl())
                <img src="{{ $post->imageUrl() }}" alt="{{ $post->title }}" loading="lazy"
                     class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
            @endif
        </div>
    </a>

    <div class="p-5">
        @if ($post->category)
            <a href="{{ $post->category->url() }}" class="text-xs font-semibold uppercase tracking-wide text-brand">
                {{ $post->category->name }}
            </a>
        @endif

        <h3 class="mt-1.5 text-lg font-semibold leading-snug text-slate-900">
            <a href="{{ $post->url() }}" class="hover:text-brand">{{ $post->title }}</a>
        </h3>

        <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $post->summary() }}</p>

        <div class="mt-4 flex items-center gap-2 text-xs text-slate-400">
            <span>{{ format_date($post->published_at) }}</span>
            @if ($post->reading_minutes)
                <span>&middot;</span>
                <span>{{ $post->reading_minutes }} min read</span>
            @endif
        </div>
    </div>
</article>
