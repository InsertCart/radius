@if ($products->isEmpty())
    <p class="cb-empty">{{ $settings['empty_text'] ?? 'No products yet.' }}</p>
@else
    <div class="cb-products">
        @foreach ($products as $product)
            <article class="cb-product-card">
                <a class="cb-product-card__media" href="{{ $product->url() }}">
                    @if ($product->imageUrl())
                        <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy" decoding="async">
                    @endif

                    @if ($product->isOnSale())
                        <span class="cb-product-card__badge">−{{ $product->discountPercent() }}%</span>
                    @endif
                </a>

                <div class="cb-product-card__body">
                    <h3 class="cb-product-card__title">
                        <a href="{{ $product->url() }}">{{ $product->name }}</a>
                    </h3>

                    @if (! empty($settings['show_rating']) && $product->review_count > 0)
                        <div class="cb-product-card__rating" aria-label="{{ $product->rating }} out of 5">
                            <span aria-hidden="true">{{ str_repeat('★', (int) round($product->rating)) }}</span>
                            <small>({{ $product->review_count }})</small>
                        </div>
                    @endif

                    @if (! empty($settings['show_price']))
                        <div class="cb-product-card__price">
                            @if ($product->isOnSale())
                                <span>{{ money($product->sale_price) }}</span>
                                <del>{{ money($product->price) }}</del>
                            @else
                                <span>{{ money($product->price) }}</span>
                            @endif
                        </div>
                    @endif

                    @if (! empty($settings['show_cart_button']))
                        @if ($product->inStock() && $product->type === 'simple' && ! $editing)
                            <form method="POST" action="{{ route('cart.add') }}">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <button class="cb-button cb-button--sm cb-button--full">Add to cart</button>
                            </form>
                        @else
                            <a class="cb-button cb-button--sm cb-button--full" href="{{ $product->url() }}">View</a>
                        @endif
                    @endif
                </div>
            </article>
        @endforeach
    </div>
@endif
