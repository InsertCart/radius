@php
    $features = $settings['features'] ?? [];
    $attrs = \App\Cms\Builder\Blocks\Block::linkAttributes($settings['button_link'] ?? null);
    $featured = ! empty($settings['featured']);
@endphp

<div class="cb-pricing @if ($featured) cb-pricing--featured @endif">
    @if ($featured && filled($settings['badge_text'] ?? null))
        <span class="cb-pricing__badge">{{ $settings['badge_text'] }}</span>
    @endif

    <h3 class="cb-pricing__plan">{{ $settings['plan'] ?? '' }}</h3>

    <div class="cb-pricing__price">
        <span class="cb-pricing__currency">{{ $settings['currency'] ?? '' }}</span>
        <span class="cb-pricing__amount">{{ $settings['price'] ?? '' }}</span>
        @if (filled($settings['period'] ?? null))
            <span class="cb-pricing__period">{{ $settings['period'] }}</span>
        @endif
    </div>

    @if (filled($settings['description'] ?? null))
        <p class="cb-pricing__description">{{ $settings['description'] }}</p>
    @endif

    @if (! empty($features))
        <ul class="cb-pricing__features">
            @foreach ($features as $feature)
                @continue(blank($feature['text'] ?? null))
                <li @class(['cb-pricing__feature', 'cb-pricing__feature--excluded' => ! empty($feature['excluded'])])>
                    <x-cb-icon :name="! empty($feature['excluded']) ? 'close' : 'check'" />
                    <span>{{ $feature['text'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if (filled($settings['button_text'] ?? null))
        <div class="cb-pricing__action">
            <{{ $attrs ? 'a' : 'button' }} {!! $attrs ?: 'type="button"' !!} class="cb-button cb-button--md cb-button--full">
                {{ $settings['button_text'] }}
            </{{ $attrs ? 'a' : 'button' }}>
        </div>
    @endif
</div>
