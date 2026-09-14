@php
    $tag = in_array($settings['tag'] ?? 'h2', ['h1','h2','h3','h4','h5','h6','p'], true) ? $settings['tag'] : 'h2';
    $text = trim((string) ($settings['text'] ?? ''));
    $linkAttrs = \App\Cms\Builder\Blocks\Block::linkAttributes($settings['link'] ?? null);
@endphp

@if ($text === '')
    {!! $block->placeholder('Add your heading', $context) !!}
@else
    <{{ $tag }} class="cb-heading">
        @if ($linkAttrs)
            <a {!! $linkAttrs !!}>{{ $text }}</a>
        @else
            {{ $text }}
        @endif
    </{{ $tag }}>
@endif
