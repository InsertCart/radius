@php
    $badge = trim((string) ($settings['badge'] ?? 'NEW DROP'));
    $text = trim((string) ($settings['text'] ?? 'Complimentary express shipping on all orders over $150.'));
    $linkText = trim((string) ($settings['link_text'] ?? 'Shop Now'));
    $url = \App\Cms\Builder\Blocks\Block::safeUrl($settings['url'] ?? '') ?: (modules()->enabled('shop') ? route('shop.index') : '');
@endphp

@if ($text !== '')
    <div class="zn-announcement">
        <div class="zn-wrap zn-announcement__inner">
            @if ($badge !== '')
                <span class="zn-announcement__badge">{{ $badge }}</span>
            @endif
            <span>{{ $text }}</span>
            @if ($url !== '')
                <a href="{{ $url }}" class="zn-announcement__link">{{ $linkText }} &rarr;</a>
            @endif
        </div>
    </div>
@endif
