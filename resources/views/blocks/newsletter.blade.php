@php $action = safe_route('newsletter.subscribe', [], '#'); @endphp

<div class="cb-newsletter">
    @if (filled($settings['heading'] ?? null))
        <h3 class="cb-newsletter__heading">{{ $settings['heading'] }}</h3>
    @endif

    @if (filled($settings['description'] ?? null))
        <p class="cb-newsletter__text">{{ $settings['description'] }}</p>
    @endif

    <form method="POST" action="{{ $action }}"
          @class(['cb-newsletter__form', 'cb-newsletter__form--inline' => ! empty($settings['inline'])])>
        @csrf
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="cb-hp" aria-hidden="true">

        @if (! empty($settings['show_name']))
            <input type="text" name="name" placeholder="Your name" aria-label="Your name">
        @endif

        <input type="email"
               name="email"
               required
               placeholder="{{ $settings['placeholder'] ?? 'you@example.com' }}"
               aria-label="Email address">

        <button type="submit" class="cb-button cb-button--md">
            {{ $settings['button_text'] ?? 'Subscribe' }}
        </button>
    </form>
</div>
