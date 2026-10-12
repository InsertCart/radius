{{-- The blog index: heading, search, the post grid with page numbers, and the
     sidebar.

     On the live /blog page the controller has already found the posts and
     passes them in through $context, so they are only queried again when the
     builder asks for a different number per page. In the editor there is no
     controller behind the preview, so the section finds them itself. --}}
@php
    $perPage = max(1, min(48, (int) ($settings['per_page'] ?? setting('posts_per_page', 12))));
    $query = request()->string('q')->toString();

    $posts = $context['posts'] ?? null;

    if (! $posts instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator || $posts->perPage() !== $perPage) {
        $posts = search()->constrain(\App\Models\Post::published(), 'post', $query, rank: true)
            ->with(['category', 'author'])
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString()
            ->withPath(route('blog.index'));
    }

    $showSidebar = (bool) ($settings['show_sidebar'] ?? true);

    $categories = $showSidebar
        ? ($context['categories'] ?? \App\Models\Category::blog()->active()
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('sort_order')->get())
        : collect();

    $grid = match ((int) ($settings['columns'] ?? 2)) {
        1 => '',
        3 => 'sm:grid-cols-2 lg:grid-cols-3',
        default => 'sm:grid-cols-2',
    };
@endphp

<div class="mx-auto max-w-6xl px-4 py-14">
    @if (filled($settings['title'] ?? 'Blog') || filled($settings['subtitle'] ?? 'Thoughts, updates and announcements.'))
        <header class="mb-10">
            @if (filled($settings['title'] ?? 'Blog'))
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ $settings['title'] ?? 'Blog' }}</h1>
            @endif
            @if (filled($settings['subtitle'] ?? 'Thoughts, updates and announcements.'))
                <p class="mt-2 text-slate-600">{{ $settings['subtitle'] ?? 'Thoughts, updates and announcements.' }}</p>
            @endif
        </header>
    @endif

    <div class="grid gap-10 lg:grid-cols-4">
        <div class="{{ $showSidebar ? 'lg:col-span-3' : 'lg:col-span-4' }}">
            @if ($settings['show_search'] ?? true)
                <form method="GET" action="{{ route('blog.index') }}" class="mb-8 max-w-sm" role="search" data-radius-search="post">
                    <input type="search" name="q" value="{{ $query }}" placeholder="Search posts" autocomplete="off"
                           aria-label="Search posts"
                           class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm">
                </form>
                @searchScripts
            @endif

            @if ($posts->isEmpty())
                <p class="rounded-2xl border border-dashed border-slate-300 py-16 text-center text-slate-500">
                    {{ $query ? 'No posts match that search.' : 'No posts published yet.' }}
                </p>
            @else
                <div class="grid gap-6 {{ $grid }}">
                    @foreach ($posts as $post)
                        @include('theme::partials.post-card', ['post' => $post])
                    @endforeach
                </div>

                {{ $posts->links('theme::partials.pagination') }}
            @endif
        </div>

        @if ($showSidebar)
            @include('theme::blog.sidebar', ['categories' => $categories])
        @endif
    </div>
</div>
