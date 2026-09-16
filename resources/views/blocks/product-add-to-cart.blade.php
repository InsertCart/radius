@php
    $variants = $product->type === 'variable' ? $product->variants->where('is_active', true) : collect();
    $fieldId = 'cb-buy-'.($context['id'] ?? $product->id);
    $stacked = ($settings['layout'] ?? 'row') === 'stack';
@endphp

@if ($product->inStock())
    <form method="POST" action="{{ route('cart.add') }}" class="cb-buy">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">

        @if ($variants->isNotEmpty())
            <label class="cb-buy__field">
                <span>{{ $settings['variant_label'] ?: 'Choose an option' }}</span>
                <select name="variant_id" required>
                    @foreach ($variants as $variant)
                        <option value="{{ $variant->id }}" @disabled(! $variant->inStock())>
                            {{ $variant->name }}
                            @if ($variant->optionsLabel()) — {{ $variant->optionsLabel() }} @endif
                            — {{ money($variant->effectivePrice()) }}
                            @unless ($variant->inStock()) (sold out) @endunless
                        </option>
                    @endforeach
                </select>
            </label>
        @endif

        @if (! empty($settings['show_quantity']))
            <span class="cb-qty" data-cb-qty>
                <button type="button" data-step="-1" aria-label="Decrease quantity">&minus;</button>
                <label for="{{ $fieldId }}" class="cb-sr">Quantity</label>
                <input type="number" name="quantity" id="{{ $fieldId }}" value="1" min="1" max="99">
                <button type="button" data-step="1" aria-label="Increase quantity">+</button>
            </span>
        @else
            <input type="hidden" name="quantity" value="1">
        @endif

        <div class="cb-buy__actions {{ $stacked ? 'cb-buy__actions--stack' : '' }}">
            <button type="submit" class="cb-buy__btn cb-buy__cart">
                <x-cb-icon :name="$settings['cart_icon'] ?? null" />
                {{ $settings['cart_label'] ?: 'Add to cart' }}
            </button>

            @if (! empty($settings['show_buy_now']))
                {{-- The button's own name/value tells the server to go straight
                     to checkout, so this needs no JavaScript. --}}
                <button type="submit" name="buy_now" value="1" class="cb-buy__btn cb-buy__now">
                    {{ $settings['buy_now_label'] ?: 'Buy now' }}
                </button>
            @endif
        </div>
    </form>
@else
    <div class="cb-buy">
        <span class="cb-buy__btn cb-buy__soldout" aria-disabled="true">{{ $settings['sold_out_label'] ?: 'Out of stock' }}</span>
    </div>
@endif
