@php
    $images = $settings['images'] ?? [];
    $ratio = $settings['ratio'] ?? '1-1';
    $lightbox = ! empty($settings['lightbox']);
@endphp

@if (empty($images))
    {!! $block->placeholder('Add images to the gallery', $context) !!}
@else
    <div class="cb-gallery" @if ($lightbox) data-cb-lightbox @endif>
        @foreach ($images as $image)
            @php
                $url = \App\Cms\Builder\Blocks\Block::imageUrl($image);
                $caption = is_array($image) ? ($image['caption'] ?? '') : '';
                $path = is_array($image) ? ($image['path'] ?? null) : $image;
                $alt = (is_array($image) ? ($image['alt'] ?? '') : '') ?: ($libraryAlts[$path] ?? $caption);
            @endphp
            @continue(! $url)

            <figure class="cb-gallery__item cb-ratio--{{ $ratio }}">
                @if ($lightbox)
                    <a href="{{ $url }}" data-cb-lightbox-item aria-label="{{ $alt ?: 'View full size image' }}">
                @endif
                    <img src="{{ $url }}" alt="{{ $alt }}" loading="lazy" decoding="async">
                @if ($lightbox)
                    </a>
                @endif

                @if (! empty($settings['show_captions']) && $caption !== '')
                    <figcaption>{{ $caption }}</figcaption>
                @endif
            </figure>
        @endforeach
    </div>
@endif
