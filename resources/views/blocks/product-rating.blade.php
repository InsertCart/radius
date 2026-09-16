@php $stars = (int) round((float) $product->rating); @endphp

@if ($product->review_count > 0)
    <p class="cb-rating">
        <span class="cb-rating__stars" aria-label="{{ $product->rating }} out of 5">{{ str_repeat('★', $stars) }}{{ str_repeat('☆', 5 - $stars) }}</span>
        @if (! empty($settings['show_count']))
            <span class="cb-rating__count">{{ $product->rating }} &middot; {{ $product->review_count }} {{ Str::plural('review', $product->review_count) }}</span>
        @endif
    </p>
@elseif (empty($settings['hide_when_empty']))
    <p class="cb-rating">
        <span class="cb-rating__stars" aria-hidden="true">☆☆☆☆☆</span>
        @if (! empty($settings['show_count']))
            <span class="cb-rating__count">No reviews yet</span>
        @endif
    </p>
@elseif ($editing)
    {{-- Visible in the editor so the widget can still be selected. --}}
    <div class="cb-placeholder">Star rating (hidden until this product has a review)</div>
@endif
