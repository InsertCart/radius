@php
    // Authored by an administrator through the rich-text control, so markup is
    // intentional here and rendered rather than escaped.
    $content = (string) ($settings['content'] ?? '');
@endphp

@if (trim(strip_tags($content)) === '')
    {!! $block->placeholder('Add some text', $context) !!}
@else
    <div class="cb-text prose-content">{!! $content !!}</div>
@endif
