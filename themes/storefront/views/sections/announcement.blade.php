{{-- Announcement bar. Used by the layout, and offered to the visual builder
     as a section (see "sections" in theme.json). --}}
@php
    $text = trim((string) ($settings['text'] ?? '')) ?: setting('storefront_announcement', 'Free delivery on orders over the minimum spend — shop the new season now.');
    $link = trim((string) ($settings['url'] ?? ''));
@endphp

<div class="sf-announce">
    @if ($link !== '' && \App\Cms\Builder\Blocks\Block::safeUrl($link) !== '')
        <a href="{{ \App\Cms\Builder\Blocks\Block::safeUrl($link) }}">{{ $text }}</a>
    @else
        {{ $text }}
    @endif
</div>
