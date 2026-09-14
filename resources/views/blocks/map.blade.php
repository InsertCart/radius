<div class="cb-map">
    <iframe src="{{ $embedUrl }}"
            title="{{ $settings['address'] ?? 'Map' }}"
            loading="lazy"
            frameborder="0"
            referrerpolicy="no-referrer-when-downgrade"></iframe>
</div>

@if (filled($settings['address'] ?? null))
    <p class="cb-map__address">
        <a href="{{ $linkUrl }}" target="_blank" rel="noopener noreferrer">{{ $settings['address'] }}</a>
    </p>
@endif
