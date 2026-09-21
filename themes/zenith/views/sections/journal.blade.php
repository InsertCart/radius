@php
    $posts = collect();
    $count = max(2, min(6, (int) ($settings['count'] ?? 3)));

    if (modules()->enabled('blog')) {
        $posts = \App\Models\Post::published()->with(['category'])->latest('published_at')->limit($count)->get();
    }

    $kicker = trim((string) ($settings['kicker'] ?? 'PERSPECTIVES & ESSAYS'));
    $title = trim((string) ($settings['title'] ?? 'From the Journal'));
    $subtitle = trim((string) ($settings['subtitle'] ?? 'Behind the craft, cultural dispatches, and design notes.'));
@endphp

@if ($posts->isNotEmpty() || $editing)
    <section class="zn-journal">
        <div class="zn-wrap">
            <div class="zn-section-head">
                <div>
                    @if ($kicker !== '')
                        <div class="zn-section-head__kicker">{{ $kicker }}</div>
                    @endif
                    <h2 class="zn-section-head__title">{{ $title }}</h2>
                    @if ($subtitle !== '')
                        <p class="zn-section-head__subtitle">{{ $subtitle }}</p>
                    @endif
                </div>

                @module('blog')
                    <a href="{{ route('blog.index') }}" class="zn-btn zn-btn--ghost zn-btn--sm" style="flex-shrink: 0;">
                        All Articles
                        @include('theme::partials.icon', ['name' => 'arrow-right'])
                    </a>
                @endmodule
            </div>

            @if ($posts->isEmpty())
                <div style="text-align: center; padding: 40px; border: 1px dashed var(--zn-border); border-radius: var(--zn-radius-lg); color: var(--zn-muted);">
                    No journal articles published yet.
                </div>
            @else
                <div class="zn-grid--journal">
                    @foreach ($posts as $post)
                        @include('theme::partials.post-card', ['post' => $post])
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
