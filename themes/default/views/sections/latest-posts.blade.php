{{-- The newest blog posts, featured ones first. --}}
@php
    $posts = modules()->enabled('blog')
        ? \App\Models\Post::published()->with('category')
            ->orderByDesc('is_featured')->orderByDesc('published_at')
            ->limit(max(1, min(12, (int) ($settings['count'] ?? 3))))->get()
        : collect();

    $grid = match ((int) ($settings['columns'] ?? 3)) {
        2 => 'md:grid-cols-2',
        4 => 'md:grid-cols-2 lg:grid-cols-4',
        default => 'md:grid-cols-3',
    };
@endphp

@if ($posts->isNotEmpty())
    <section class="{{ ($settings['tinted'] ?? true) ? 'border-t border-slate-100 bg-slate-50' : '' }}">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <div class="mb-8 flex items-end justify-between gap-4">
                <div>
                    @if (filled($settings['title'] ?? 'From the blog'))
                        <h2 class="text-2xl font-bold text-slate-900">{{ $settings['title'] ?? 'From the blog' }}</h2>
                    @endif
                    @if (filled($settings['subtitle'] ?? 'The latest writing'))
                        <p class="mt-1 text-sm text-slate-500">{{ $settings['subtitle'] ?? 'The latest writing' }}</p>
                    @endif
                </div>
                @if ($settings['show_link'] ?? true)
                    <a href="{{ route('blog.index') }}" class="shrink-0 text-sm font-medium text-brand hover:underline">All posts &rarr;</a>
                @endif
            </div>

            <div class="grid gap-6 {{ $grid }}">
                @foreach ($posts as $post)
                    @include('theme::partials.post-card', ['post' => $post])
                @endforeach
            </div>
        </div>
    </section>
@elseif ($editing)
    <div class="cb-placeholder">Latest blog posts &mdash; no published posts to show yet.</div>
@endif
