@php
    $source = $settings['source'] ?? 'setting';
    $url = $source === 'custom'
        ? \App\Cms\Builder\Blocks\Block::imageUrl($settings['image'] ?? null)
        : \App\Cms\Builder\Blocks\Block::imageUrl(setting('site_logo'));
    $name = setting('site_name', config('app.name'));
    $link = ! empty($settings['link_home']);
@endphp

@if ($link)<a href="{{ url('/') }}" class="cb-logo">@else<span class="cb-logo">@endif
    @if ($source !== 'text' && $url)
        <img src="{{ $url }}" alt="{{ $name }}" loading="eager" decoding="async">
    @else
        <span class="cb-logo__text">{{ $name }}</span>
    @endif
@if ($link)</a>@else</span>@endif
