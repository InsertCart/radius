@php
    $kicker = trim((string) ($settings['kicker'] ?? 'PRIVATE CIRCLE'));
    $title = trim((string) ($settings['title'] ?? 'Receive private dispatches & early access'));
    $text = trim((string) ($settings['text'] ?? 'Join our global community. Exclusive seasonal previews and invitations directly to your inbox.'));
    $buttonLabel = trim((string) ($settings['button_label'] ?? 'Join Society'));
@endphp

<section class="zn-newsletter-section">
    <div class="zn-wrap">
        <div class="zn-newsletter-card">
            @if ($kicker !== '')
                <div class="zn-newsletter-card__kicker">{{ $kicker }}</div>
            @endif

            <h2 class="zn-newsletter-card__title">{{ $title }}</h2>

            @if ($text !== '')
                <p class="zn-newsletter-card__text">{{ $text }}</p>
            @endif

            @module('newsletter')
                <form method="POST" action="{{ route('newsletter.subscribe') }}" class="zn-newsletter-form">
                    @csrf
                    <input type="email" name="email" placeholder="Enter your email address" required aria-label="Your email address">
                    <button type="submit" class="zn-btn zn-btn--primary zn-btn--lg">{{ $buttonLabel }}</button>
                </form>
            @endmodule
        </div>
    </div>
</section>
