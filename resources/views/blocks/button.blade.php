@php
    $text = trim((string) ($settings['text'] ?? ''));
    $link = $settings['link'] ?? null;
    $url = is_array($link) ? ($link['url'] ?? '') : $link;
    $attrs = \App\Cms\Builder\Blocks\Block::linkAttributes($link);
    $icon = $settings['icon'] ?? null;
    $iconPosition = $settings['icon_position'] ?? 'after';
    $classes = 'cb-button cb-button--'.($settings['size'] ?? 'md').(!empty($settings['full_width']) ? ' cb-button--full' : '');
@endphp

@if ($text === '' && blank($icon))
    {!! $block->placeholder('Set a button label', $context) !!}
@else
    {{-- With no link this is still a button element, so it stays reachable by
         keyboard and announces itself correctly to screen readers. --}}
    <{{ $url ? 'a' : 'button' }} {!! $url ? $attrs : 'type="button"' !!} class="{{ $classes }}">
        @if ($icon && $iconPosition === 'before')
            <x-cb-icon :name="$icon" />
        @endif
        @if ($text !== '')
            <span>{{ $text }}</span>
        @endif
        @if ($icon && $iconPosition === 'after')
            <x-cb-icon :name="$icon" />
        @endif
    </{{ $url ? 'a' : 'button' }}>
@endif
