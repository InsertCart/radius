@php
    $badge = trim((string) ($settings['badge'] ?? 'THE 2026 EDIT'));
    $title = trim((string) ($settings['title'] ?? '')) ?: (setting('site_name') ?: 'Elegance defined by purpose and craft.');
    $text = trim((string) ($settings['text'] ?? '')) ?: (setting('site_tagline') ?: 'Discover a curated synthesis of timeless aesthetics and modern utility. Built for those who value enduring quality.');

    $primaryLabel = trim((string) ($settings['primary_label'] ?? 'Explore Collection'));
    $primaryUrl = \App\Cms\Builder\Blocks\Block::safeUrl($settings['primary_url'] ?? '') ?: (modules()->enabled('shop') ? route('shop.index') : '');

    $secondaryLabel = trim((string) ($settings['secondary_label'] ?? 'Read Journal'));
    $secondaryUrl = \App\Cms\Builder\Blocks\Block::safeUrl($settings['secondary_url'] ?? '') ?: (modules()->enabled('blog') ? route('blog.index') : '');

    $statOneNum = trim((string) ($settings['stat_one_num'] ?? '100%'));
    $statOneLabel = trim((string) ($settings['stat_one_label'] ?? 'Carbon Neutral'));
    $statTwoNum = trim((string) ($settings['stat_two_num'] ?? '24/7'));
    $statTwoLabel = trim((string) ($settings['stat_two_label'] ?? 'Concierge Support'));
@endphp

<section class="zn-hero">
    <div class="zn-wrap zn-hero__inner">
        @if ($badge !== '')
            <div class="zn-hero__badge">
                <span class="zn-badge zn-badge--accent">{{ $badge }}</span>
            </div>
        @endif

        <h1 class="zn-hero__title">{{ $title }}</h1>

        @if ($text !== '')
            <p class="zn-hero__text">{{ $text }}</p>
        @endif

        @if (($primaryLabel !== '' && $primaryUrl !== '') || ($secondaryLabel !== '' && $secondaryUrl !== ''))
            <div class="zn-hero__actions">
                @if ($primaryLabel !== '' && $primaryUrl !== '')
                    <a href="{{ $primaryUrl }}" class="zn-btn zn-btn--primary zn-btn--lg">
                        <span>{{ $primaryLabel }}</span>
                        @include('theme::partials.icon', ['name' => 'arrow-right'])
                    </a>
                @endif
                @if ($secondaryLabel !== '' && $secondaryUrl !== '')
                    <a href="{{ $secondaryUrl }}" class="zn-btn zn-btn--secondary zn-btn--lg">
                        <span>{{ $secondaryLabel }}</span>
                    </a>
                @endif
            </div>
        @endif

        @if ($statOneNum !== '' || $statTwoNum !== '')
            <div class="zn-hero__stats">
                @if ($statOneNum !== '')
                    <div class="zn-hero__stat-item">
                        <span class="zn-hero__stat-num">{{ $statOneNum }}</span>
                        <span class="zn-hero__stat-label">{{ $statOneLabel }}</span>
                    </div>
                @endif

                @if ($statOneNum !== '' && $statTwoNum !== '')
                    <span class="zn-hero__stat-dot"></span>
                @endif

                @if ($statTwoNum !== '')
                    <div class="zn-hero__stat-item">
                        <span class="zn-hero__stat-num">{{ $statTwoNum }}</span>
                        <span class="zn-hero__stat-label">{{ $statTwoLabel }}</span>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
