@php
    $categories = collect();
    $count = max(2, min(8, (int) ($settings['count'] ?? 4)));

    if (modules()->enabled('shop')) {
        $categories = \App\Models\Category::shop()
            ->active()
            ->roots()
            ->withCount('products')
            ->orderBy('sort_order')
            ->limit($count)
            ->get();
    }

    $kicker = trim((string) ($settings['kicker'] ?? 'CURATED CATEGORIES'));
    $title = trim((string) ($settings['title'] ?? 'Browse by Collection'));
@endphp

@if ($categories->isNotEmpty() || $editing)
    <section class="zn-categories">
        <div class="zn-wrap">
            <div class="zn-section-head">
                <div>
                    @if ($kicker !== '')
                        <div class="zn-section-head__kicker">{{ $kicker }}</div>
                    @endif
                    <h2 class="zn-section-head__title">{{ $title }}</h2>
                    <p class="zn-section-head__subtitle">Form, function, and refined aesthetics across our essential collections.</p>
                </div>

                @module('shop')
                    <a href="{{ route('shop.index') }}" class="zn-btn zn-btn--ghost zn-btn--sm" style="flex-shrink: 0;">
                        All Categories
                        @include('theme::partials.icon', ['name' => 'arrow-right'])
                    </a>
                @endmodule
            </div>

            @if ($categories->isEmpty())
                <div style="text-align: center; padding: 40px; border: 1px dashed var(--zn-border); border-radius: var(--zn-radius-lg); color: var(--zn-muted);">
                    No shop categories created yet.
                </div>
            @else
                <div class="zn-bento">
                    @foreach ($categories as $cat)
                        <a href="{{ $cat->url() }}" class="zn-bento-card">
                            @if ($cat->image)
                                <img src="{{ media_url($cat->image) }}" alt="{{ $cat->name }}" class="zn-bento-card__bg" loading="lazy">
                            @else
                                <div class="zn-bento-card__bg" style="background: linear-gradient(135deg, var(--zn-surface) 0%, var(--zn-bg-alt) 100%);"></div>
                            @endif
                            <div class="zn-bento-card__overlay"></div>

                            <div class="zn-bento-card__content">
                                <span class="zn-bento-card__count">{{ $cat->products_count }} {{ Str::plural('piece', $cat->products_count) }}</span>
                                <h3 class="zn-bento-card__title">{{ $cat->name }}</h3>
                                <span class="zn-bento-card__arrow">
                                    Explore
                                    @include('theme::partials.icon', ['name' => 'arrow-right', 'class' => 'w-4 h-4'])
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
