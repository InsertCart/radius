@if (filled($product->short_description))
    <p class="cb-pshort">{{ $product->short_description }}</p>
@elseif ($editing)
    <div class="cb-placeholder">Short description (this product has none)</div>
@endif
