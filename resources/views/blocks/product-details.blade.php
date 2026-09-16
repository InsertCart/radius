@php
    $display = in_array($settings['display'] ?? 'closed', ['open', 'closed', 'plain'], true) ? $settings['display'] : 'closed';

    $rows = [];

    if (! empty($settings['show_sku']) && $product->sku) {
        $rows['SKU'] = e($product->sku);
    }

    if (! empty($settings['show_categories']) && $product->categories->isNotEmpty()) {
        $rows['Category'] = $product->categories
            ->map(fn ($category) => '<a href="'.e($category->url()).'">'.e($category->name).'</a>')
            ->implode(', ');
    }

    if (! empty($settings['show_weight']) && $product->weight) {
        $rows['Weight'] = e($product->weight);
    }

    if (! empty($settings['show_dimensions']) && $product->dimensions) {
        $rows['Dimensions'] = e($product->dimensions);
    }

    if (! empty($settings['show_delivery'])) {
        $rows['Delivery'] = $product->isDigital() ? 'Digital download' : 'Shipped to your address';
    }

    $html = '<dl class="cb-pdetails">';
    foreach ($rows as $label => $value) {
        $html .= '<dt>'.e($label).'</dt><dd>'.$value.'</dd>';
    }
    $html .= '</dl>';
@endphp

@if ($rows === [])
    {!! $editing ? '<div class="cb-placeholder">Product details (nothing to show for this product)</div>' : '' !!}
@else
    @include('blocks.partials.product-info', [
        'heading' => $settings['heading'] ?? '',
        'display' => $display,
        'html' => $html,
    ])
@endif
