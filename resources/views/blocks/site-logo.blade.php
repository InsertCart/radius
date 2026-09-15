@php
    $source = $settings['source'] ?? 'setting';
    $name = setting('site_name', config('app.name'));
    $link = ! empty($settings['link_home']);

    // A custom image is used exactly as uploaded - there is only the one file,
    // so there is no second ink to switch to. The site logo goes through the
    // component, which has both and picks by the background chosen below.
    $customUrl = $source === 'custom'
        ? \App\Cms\Builder\Blocks\Block::imageUrl($settings['image'] ?? null)
        : null;
@endphp

@if ($link)<a href="{{ url('/') }}" class="cb-logo">@else<span class="cb-logo">@endif
    @if ($source === 'custom' && $customUrl)
        <img src="{{ $customUrl }}" alt="{{ $name }}" loading="eager" decoding="async">
    @elseif ($source === 'setting')
        <x-site-logo :on="$settings['on'] ?? 'auto'" :alt="$name" loading="eager" decoding="async" />
    @else
        <span class="cb-logo__text">{{ $name }}</span>
    @endif
@if ($link)</a>@else</span>@endif
