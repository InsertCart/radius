{{-- Homepage hero. Empty settings fall back to the site name and tagline,
     which is what the theme showed before the builder had a say. --}}
@php
    $safe = fn ($url) => \App\Cms\Builder\Blocks\Block::safeUrl($url);

    $eyebrow = trim((string) ($settings['eyebrow'] ?? '')) ?: setting('site_name', config('app.name'));
    $title = trim((string) ($settings['title'] ?? '')) ?: (setting('site_tagline') ?: 'Everything worth having, in one place.');
    $text = trim((string) ($settings['text'] ?? ''));

    $primaryLabel = trim((string) ($settings['primary_label'] ?? 'Start shopping'));
    $primaryUrl = $safe($settings['primary_url'] ?? '') ?: (modules()->enabled('shop') ? route('shop.index') : '');
    $secondaryLabel = trim((string) ($settings['secondary_label'] ?? 'Read the blog'));
    $secondaryUrl = $safe($settings['secondary_url'] ?? '') ?: (modules()->enabled('blog') ? route('blog.index') : '');
@endphp

<section class="sf-hero">
    <div class="sf-hero__inner">
        @if ($eyebrow)
            <p class="sf-hero__eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1>{{ $title }}</h1>
        @if ($text !== '')
            <p>{{ $text }}</p>
        @endif

        @if (($primaryLabel !== '' && $primaryUrl) || ($secondaryLabel !== '' && $secondaryUrl))
            <div class="sf-hero__cta">
                @if ($primaryLabel !== '' && $primaryUrl)
                    <a href="{{ $primaryUrl }}" class="sf-btn sf-btn--dark sf-btn--lg">{{ $primaryLabel }}</a>
                @endif
                @if ($secondaryLabel !== '' && $secondaryUrl)
                    <a href="{{ $secondaryUrl }}" class="sf-btn sf-btn--ghost sf-btn--lg">{{ $secondaryLabel }}</a>
                @endif
            </div>
        @endif
    </div>
</section>
