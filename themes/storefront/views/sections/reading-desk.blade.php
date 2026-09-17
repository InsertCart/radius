{{-- Latest blog posts. --}}
@php
    $posts = modules()->enabled('blog')
        ? \App\Models\Post::published()->with('category')
            ->orderByDesc('is_featured')->orderByDesc('published_at')
            ->limit(max(1, min(12, (int) ($settings['count'] ?? 3))))->get()
        : collect();
@endphp

@if ($posts->isNotEmpty())
    <section class="sf-section {{ ($settings['soft'] ?? true) ? 'sf-section--soft' : '' }}">
        <div class="sf-wrap">
            <div class="sf-section__head">
                <div>
                    @if (filled($settings['title'] ?? null))
                        <h2 class="sf-section__title">{{ $settings['title'] }}</h2>
                    @endif
                    @if (filled($settings['subtitle'] ?? null))
                        <p class="sf-section__sub">{{ $settings['subtitle'] }}</p>
                    @endif
                </div>
                <div class="sf-section__tools">
                    <a href="{{ route('blog.index') }}" class="sf-btn sf-btn--ghost sf-btn--sm">
                        Show all
                        @include('theme::partials.icon', ['name' => 'arrow-up-right'])
                    </a>
                </div>
            </div>

            <div class="sf-grid sf-grid--posts">
                @foreach ($posts as $post)
                    @include('theme::partials.post-card', ['post' => $post])
                @endforeach
            </div>
        </div>
    </section>
@elseif ($editing)
    <div class="cb-placeholder">From the reading desk — no published posts yet.</div>
@endif
