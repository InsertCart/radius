@php
    $items = array_values(array_filter((array) ($settings['items'] ?? []), fn ($item) => is_array($item)));
    $columns = max(2, min(6, (int) ($settings['columns'] ?? 4)));
@endphp

@if (empty($items))
    {!! $editing ? '<div class="cb-placeholder">Add some badges</div>' : '' !!}
@else
    <ul class="cb-badges" style="--cb-badges-cols: {{ $columns }}">
        @foreach ($items as $item)
            <li class="cb-badges__item">
                @if (filled($item['icon'] ?? null))
                    <span class="cb-badges__ring"><x-cb-icon :name="$item['icon']" /></span>
                @endif
                <span class="cb-badges__label">{{ $item['label'] ?? '' }}</span>
            </li>
        @endforeach
    </ul>
@endif
