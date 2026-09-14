@php
    $attrs = \App\Cms\Builder\Blocks\Block::linkAttributes($settings['link'] ?? null);
    $position = $settings['position'] ?? 'top';
    $title = trim((string) ($settings['title'] ?? ''));
    $description = trim((string) ($settings['description'] ?? ''));
@endphp

<div class="cb-icon-box cb-icon-box--{{ $position }}">
    @if (filled($settings['icon'] ?? null))
        <div class="cb-icon-box__icon">
            <x-cb-icon :name="$settings['icon']" />
        </div>
    @endif

    <div class="cb-icon-box__body">
        @if ($title !== '')
            <h3 class="cb-icon-box__title">
                @if ($attrs)<a {!! $attrs !!}>{{ $title }}</a>@else{{ $title }}@endif
            </h3>
        @endif

        @if ($description !== '')
            <p class="cb-icon-box__text">{{ $description }}</p>
        @endif
    </div>
</div>
