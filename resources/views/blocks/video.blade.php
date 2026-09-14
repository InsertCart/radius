@php
    $source = $settings['source'] ?? 'youtube';
    $ratio = $settings['ratio'] ?? '16-9';
    $poster = \App\Cms\Builder\Blocks\Block::imageUrl($settings['poster'] ?? null);
@endphp

@if ($source === 'file')
    @php $file = \App\Cms\Builder\Blocks\Block::imageUrl($settings['url'] ?? null); @endphp

    @if (! $file)
        {!! $block->placeholder('Add a video file URL', $context) !!}
    @else
        <div class="cb-video cb-video--{{ $ratio }}">
            <video
                @if ($poster) poster="{{ $poster }}" @endif
                @if (! empty($settings['controls'])) controls @endif
                @if (! empty($settings['autoplay'])) autoplay playsinline @endif
                @if (! empty($settings['muted']) || ! empty($settings['autoplay'])) muted @endif
                @if (! empty($settings['loop'])) loop @endif
                preload="metadata">
                <source src="{{ $file }}">
            </video>
        </div>
    @endif
@elseif (! $embedUrl)
    {!! $block->placeholder('Paste a YouTube or Vimeo link', $context) !!}
@else
    {{-- Loaded lazily so a page full of embeds does not pull in several
         megabytes of third-party player before anyone presses play. --}}
    <div class="cb-video cb-video--{{ $ratio }}">
        <iframe src="{{ $embedUrl }}"
                title="{{ $settings['alt'] ?? 'Video' }}"
                loading="lazy"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen></iframe>
    </div>
@endif
