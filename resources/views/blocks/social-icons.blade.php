@php $shape = $settings['shape'] ?? 'circle'; @endphp

@if (empty($links))
    {!! $block->placeholder('Add your social links under Settings → Social links', $context) !!}
@else
    <div class="cb-social cb-social--{{ $shape }}">
        @foreach ($links as $link)
            @continue(blank($link['url'] ?? null))
            <a href="{{ $link['url'] }}"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="{{ ucfirst($link['network'] ?? 'Link') }}">
                <x-cb-icon :name="$link['network'] ?? 'globe'" />
            </a>
        @endforeach
    </div>
@endif
