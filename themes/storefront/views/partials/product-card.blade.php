@php
    // The catalogue has no separate brand field, so the first category stands
    // in for the "by" line — the publisher, label or maker in most shops.
    $cardBrand = $product->relationLoaded('categories')
        ? $product->categories->first()
        : null;

    $cardPrice = $product->isOnSale() ? $product->sale_price : $product->price;
@endphp

<article class="sf-card">
    <button type="button" class="sf-fav"
            data-fav="{{ $product->id }}"
            data-fav-name="{{ $product->name }}"
            data-fav-url="{{ $product->url() }}"
            data-fav-image="{{ $product->imageUrl() }}"
            data-fav-price="{{ money($cardPrice) }}"
            aria-pressed="false" aria-label="Save for later">
        @include('theme::partials.icon', ['name' => 'heart'])
    </button>

    <a href="{{ $product->url() }}" class="sf-card__media" tabindex="-1" aria-hidden="true">
        <div class="sf-card__frame {{ $product->imageUrl() ? '' : 'sf-card__frame--blank' }}">
            @if ($product->imageUrl())
                <img src="{{ $product->imageUrl() }}" alt="" loading="lazy">
            @endif
        </div>

        <div class="sf-card__flags">
            @if ($product->isOnSale())
                <span class="sf-chip sf-chip--save">{{ $product->discountPercent() }}% off</span>
            @endif
            @unless ($product->inStock())
                <span class="sf-chip sf-chip--out">Sold out</span>
            @endunless
        </div>
    </a>

    <div class="sf-card__body">
        <h3 class="sf-card__name"><a href="{{ $product->url() }}">{{ $product->name }}</a></h3>

        @if ($cardBrand)
            <p class="sf-card__by">{{ $cardBrand->name }}</p>
        @endif

        @if ($product->review_count > 0)
            <p class="sf-card__stars">
                {{ str_repeat('★', (int) round($product->rating)) }}<span>({{ $product->review_count }})</span>
            </p>
        @endif

        <div class="sf-card__price">
            @if ($product->isOnSale())
                <span class="sf-card__was">{{ money($product->price) }}</span>
                <span class="sf-card__now">{{ money($product->sale_price) }}</span>
                <span class="sf-chip sf-chip--save">{{ money($product->price - $product->sale_price) }} off</span>
            @else
                <span class="sf-card__now">{{ money($product->price) }}</span>
            @endif
        </div>

        <div class="sf-card__cta">
            @if ($product->inStock() && $product->type === 'simple')
                <form method="POST" action="{{ route('cart.add') }}">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button class="sf-btn sf-btn--primary sf-btn--block sf-btn--sm">
                        @include('theme::partials.icon', ['name' => 'bag'])
                        Add to bag
                    </button>
                </form>
            @else
                <a href="{{ $product->url() }}" class="sf-btn sf-btn--ghost sf-btn--block sf-btn--sm">
                    {{ $product->inStock() ? 'Choose options' : 'View details' }}
                </a>
            @endif
        </div>
    </div>
</article>
