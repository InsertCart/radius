@php $display = in_array($settings['display'] ?? 'open', ['open', 'closed', 'plain'], true) ? $settings['display'] : 'open'; @endphp

@if (trim($body) === '')
    {!! $editing ? '<div class="cb-placeholder">Description (this product has none)</div>' : '' !!}
@else
    @include('blocks.partials.product-info', [
        'heading' => $settings['heading'] ?? '',
        'display' => $display,
        'html' => '<div class="prose-content">'.$body.'</div>',
    ])
@endif
