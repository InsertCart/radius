@php
    $end = (float) ($settings['end'] ?? 0);
    $start = (float) ($settings['start'] ?? 0);
@endphp

<div class="cb-counter">
    <div class="cb-counter__number"
         data-cb-counter
         data-start="{{ $start }}"
         data-end="{{ $end }}"
         data-duration="{{ (int) ($settings['duration'] ?? 1800) }}"
         data-separator="{{ ! empty($settings['separator']) ? '1' : '0' }}">
        @if (filled($settings['prefix'] ?? null))<span class="cb-counter__prefix">{{ $settings['prefix'] }}</span>@endif
        {{-- The final value is printed server-side so it is correct with
             JavaScript disabled; the script animates up to it. --}}
        <span class="cb-counter__value">{{ ! empty($settings['separator']) ? number_format($end) : $end }}</span>
        @if (filled($settings['suffix'] ?? null))<span class="cb-counter__suffix">{{ $settings['suffix'] }}</span>@endif
    </div>

    @if (filled($settings['title'] ?? null))
        <div class="cb-counter__title">{{ $settings['title'] }}</div>
    @endif
</div>
