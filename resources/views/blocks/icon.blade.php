@php
    $icon = $settings['icon'] ?? null;
    $attrs = \App\Cms\Builder\Blocks\Block::linkAttributes($settings['link'] ?? null);
    $shape = $settings['shape'] ?? 'none';
@endphp

@if (blank($icon))
    {!! $block->placeholder('Choose an icon', $context) !!}
@else
    @if ($attrs)<a {!! $attrs !!} class="cb-icon cb-icon--{{ $shape }}">@else<span class="cb-icon cb-icon--{{ $shape }}">@endif
        <x-cb-icon :name="$icon" />
    @if ($attrs)</a>@else</span>@endif
@endif
