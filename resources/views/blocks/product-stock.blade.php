@if (! $product->inStock())
    <p class="cb-stock cb-stock--out">{{ $settings['out_of_stock_text'] }}</p>
@elseif ($product->isDigital())
    @if (filled($settings['digital_text'] ?? null))
        <p class="cb-stock cb-stock--in">{{ $settings['digital_text'] }}</p>
    @endif
@elseif ($product->isLowStock())
    <p class="cb-stock cb-stock--low">{{ str_replace(':count', (string) $product->stock, (string) $settings['low_stock_text']) }}</p>
@else
    <p class="cb-stock cb-stock--in">{{ $settings['in_stock_text'] }}</p>
@endif
