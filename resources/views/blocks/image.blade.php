@php
    $url = \App\Cms\Builder\Blocks\Block::imageUrl($settings['image'] ?? null);
    $attrs = \App\Cms\Builder\Blocks\Block::linkAttributes($settings['link'] ?? null);
    $caption = trim((string) ($settings['caption'] ?? ''));
@endphp

@if (! $url)
    {!! $block->placeholder('Choose an image', $context) !!}
@else
    <figure class="cb-image">
        @if ($attrs)<a {!! $attrs !!}>@endif
            <img src="{{ $url }}"
                 alt="{{ ($settings['alt'] ?? '') ?: ($libraryAlt ?? '') }}"
                 loading="lazy"
                 decoding="async">
        @if ($attrs)</a>@endif

        @if ($caption !== '')
            <figcaption class="cb-image__caption">{{ $caption }}</figcaption>
        @endif
    </figure>
@endif
