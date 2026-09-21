@php
    $cardCategory = $product->relationLoaded('categories') ? $product->categories->first() : null;
    $cardPrice = $product->isOnSale() ? $product->sale_price : $product->price;
@endphp

<article class="zn-card">
    {{-- Wishlist toggle button --}}
    <button type="button" class="zn-card__fav"
            data-fav="{{ $product->id }}"
            data-fav-name="{{ $product->name }}"
            data-fav-url="{{ $product->url() }}"
            data-fav-image="{{ $product->imageUrl('medium') ?: $product->imageUrl() }}"
            data-fav-price="{{ money($cardPrice) }}"
            aria-pressed="false"
            aria-label="Save {{ $product->name }} for later">
        @include('theme::partials.icon', ['name' => 'heart'])
    </button>

    {{-- Product Image & Badges --}}
    <a href="{{ $product->url() }}" class="zn-card__media" tabindex="-1" aria-hidden="true">
        @if ($product->imageUrl())
            <img src="{{ $product->imageUrl('medium') ?: $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy">
        @else
            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--zn-bg-alt); color: var(--zn-subtle);">
                @include('theme::partials.icon', ['name' => 'bag', 'class' => 'w-10 h-10'])
            </div>
        @endif

        <div class="zn-card__flags">
            @if ($product->isOnSale())
                <span class="zn-badge zn-badge--rose">{{ $product->discountPercent() }}% OFF</span>
            @endif
            @unless ($product->inStock())
                <span class="zn-badge" style="background: rgba(15, 23, 42, 0.85); color: #fff;">SOLD OUT</span>
            @endunless
        </div>
    </a>

    {{-- Product Body --}}
    <div class="zn-card__body">
        @if ($cardCategory)
            <span class="zn-card__category">{{ $cardCategory->name }}</span>
        @endif

        <h3 class="zn-card__title">
            <a href="{{ $product->url() }}">{{ $product->name }}</a>
        </h3>

        @if ($product->review_count > 0)
            <div class="zn-card__rating">
                @for ($i = 0; $i < 5; $i++)
                    @if ($i < round($product->rating))
                        &#9733;
                    @else
                        &#9734;
                    @endif
                @endfor
                <span>({{ $product->review_count }})</span>
            </div>
        @endif

        <div class="zn-card__price-row">
            @if ($product->isOnSale())
                <span class="zn-card__price">{{ money($product->sale_price) }}</span>
                <span class="zn-card__price--was">{{ money($product->price) }}</span>
                <span class="zn-card__discount">Save {{ money($product->price - $product->sale_price) }}</span>
            @else
                <span class="zn-card__price">{{ money($product->price) }}</span>
            @endif
        </div>

        {{-- Quick CTA --}}
        <div class="zn-card__action">
            @if ($product->inStock() && $product->type === 'simple')
                <form method="POST" action="{{ route('cart.add') }}">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" class="zn-btn zn-btn--secondary zn-btn--sm zn-btn--block">
                        @include('theme::partials.icon', ['name' => 'bag'])
                        Add to Bag
                    </button>
                </form>
            @else
                <a href="{{ $product->url() }}" class="zn-btn zn-btn--ghost zn-btn--sm zn-btn--block">
                    {{ $product->inStock() ? 'Select Options' : 'View Piece' }}
                </a>
            @endif
        </div>
    </div>
</article>
