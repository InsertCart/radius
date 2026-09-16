@php
    $saving = $product->isOnSale() ? $product->price - $product->sale_price : 0;
@endphp

<div class="cb-price">
    <div class="cb-price__row">
        @if ($product->isOnSale())
            <del class="cb-price__was">{{ money($product->price) }}</del>
            <span class="cb-price__now">{{ money($product->sale_price) }}</span>

            @if (! empty($settings['show_saving']) && $saving > 0)
                <span class="cb-price__saving">
                    {{ strtr($settings['saving_text'] ?: ':amount off', [
                        ':amount' => money($saving),
                        ':percent' => $product->discountPercent().'%',
                    ]) }}
                </span>
            @endif
        @else
            <span class="cb-price__now">{{ money($product->price) }}</span>
        @endif
    </div>

    @if (! empty($settings['show_tax_note']))
        <p class="cb-price__tax">{{ setting('shop_tax_inclusive') ? 'Inclusive of all taxes' : 'Taxes calculated at checkout' }}</p>
    @endif
</div>
