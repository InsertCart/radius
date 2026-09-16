@php
    $thumbs = in_array($settings['thumbs'] ?? 'left', ['left', 'below', 'none'], true) ? $settings['thumbs'] : 'left';
    $ratio = in_array($settings['ratio'] ?? '1-1', ['1-1', '4-3', '3-4', 'auto'], true) ? $settings['ratio'] : '1-1';
    $showThumbs = $thumbs !== 'none' && $images->count() > 1;
@endphp

<div class="cb-pimages {{ $showThumbs ? 'cb-pimages--'.$thumbs : '' }}" data-cb-pimages>
    @if ($showThumbs)
        <div class="cb-pimages__thumbs">
            @foreach ($images as $url => $alt)
                <button type="button" class="cb-pimages__thumb {{ $loop->first ? 'is-active' : '' }}"
                        data-full="{{ $url }}" data-alt="{{ $alt }}"
                        aria-label="View image {{ $loop->iteration }}">
                    <img src="{{ $url }}" alt="" loading="lazy">
                </button>
            @endforeach
        </div>
    @endif

    <div class="cb-pimages__stage {{ $ratio !== 'auto' ? 'cb-ratio--'.$ratio : '' }}">
        @if ($images->isNotEmpty())
            <img src="{{ $images->keys()->first() }}" alt="{{ $images->first() }}" data-cb-pimages-stage>
        @else
            <span class="cb-pimages__empty">No image yet</span>
        @endif
    </div>
</div>
