@php
    $text = setting('announcement_text') ?: 'Complimentary express shipping on all orders over $150.';
    $link = setting('announcement_link');
    $badge = 'NEW EDIT';
@endphp

@if ($text)
    <div class="zn-announcement">
        <div class="zn-wrap zn-announcement__inner">
            <span class="zn-announcement__badge">{{ $badge }}</span>
            <span>{{ $text }}</span>
            @if ($link)
                <a href="{{ $link }}" class="zn-announcement__link">Explore Now &rarr;</a>
            @endif
        </div>
    </div>
@endif
