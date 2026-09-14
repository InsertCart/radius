@php
    $avatar = \App\Cms\Builder\Blocks\Block::imageUrl($settings['avatar'] ?? null);
    $rating = (int) ($settings['rating'] ?? 0);
    $quote = trim((string) ($settings['quote'] ?? ''));
@endphp

<figure class="cb-testimonial">
    @if ($rating > 0)
        <div class="cb-testimonial__stars" aria-label="{{ $rating }} out of 5">
            <span aria-hidden="true">{{ str_repeat('★', min(5, $rating)) }}{{ str_repeat('☆', max(0, 5 - $rating)) }}</span>
        </div>
    @endif

    @if ($quote !== '')
        <blockquote class="cb-testimonial__quote">{{ $quote }}</blockquote>
    @endif

    <figcaption class="cb-testimonial__author">
        @if ($avatar)
            <img class="cb-testimonial__avatar" src="{{ $avatar }}" alt="" loading="lazy">
        @endif
        <span>
            @if (filled($settings['author'] ?? null))
                <span class="cb-testimonial__name">{{ $settings['author'] }}</span>
            @endif
            @if (filled($settings['role'] ?? null))
                <span class="cb-testimonial__role">{{ $settings['role'] }}</span>
            @endif
        </span>
    </figcaption>
</figure>
