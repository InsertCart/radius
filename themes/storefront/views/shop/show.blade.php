@extends('theme::layout')

@section('content')
    @php
        $category = $product->categories->first();

        // Main image first, then the gallery collection. Keyed by URL so a
        // featured image that also sits in the gallery is not shown twice.
        $images = collect();

        if ($product->imageUrl()) {
            $images->put($product->imageUrl(), $product->name);
        }

        foreach ($product->gallery as $media) {
            $images->put($media->url, $media->alt ?: $product->name);
        }

        $saving = $product->isOnSale() ? $product->price - $product->sale_price : 0;
    @endphp

    {{-- Replaced by the Product page template once one is published under
         Builder > Site pages. Until then this markup is used as-is. --}}
    @region('product', ['model' => $product])
    <div class="sf-wrap">
        <nav class="sf-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <a href="{{ route('shop.index') }}">Shop</a>
            @if ($category)
                <span>/</span>
                <a href="{{ $category->url() }}">{{ $category->name }}</a>
            @endif
            <span>/</span>
            <b>{{ $product->name }}</b>
        </nav>

        <div class="sf-pdp">
            {{-- Gallery ------------------------------------------------- --}}
            <div class="sf-gallery {{ $images->count() > 1 ? 'sf-gallery--thumbs' : '' }}">
                @if ($images->count() > 1)
                    <div class="sf-thumbs">
                        @foreach ($images as $url => $alt)
                            <button type="button" class="sf-thumb {{ $loop->first ? 'is-active' : '' }}"
                                    data-gallery-thumb data-full="{{ $url }}" data-alt="{{ $alt }}"
                                    aria-label="View image {{ $loop->iteration }}">
                                <img src="{{ $url }}" alt="" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="sf-gallery__stage">
                    @if ($images->isNotEmpty())
                        <img src="{{ $images->keys()->first() }}" alt="{{ $product->name }}" data-gallery-stage>
                    @else
                        <span class="sf-muted sf-small">No image yet</span>
                    @endif
                </div>
            </div>

            {{-- Buy box -------------------------------------------------- --}}
            <div>
                <h1 class="sf-pdp__title">{{ $product->name }}</h1>

                @if ($category)
                    <p class="sf-pdp__brand"><a href="{{ $category->url() }}">{{ $category->name }}</a></p>
                @endif

                @if ($product->review_count > 0)
                    <p class="sf-pdp__rating">
                        <b>{{ str_repeat('★', (int) round($product->rating)) }}{{ str_repeat('☆', 5 - (int) round($product->rating)) }}</b>
                        <span>{{ $product->rating }} out of 5 &middot; {{ $product->review_count }} {{ Str::plural('review', $product->review_count) }}</span>
                    </p>
                @endif

                <div class="sf-pdp__price">
                    @if ($product->isOnSale())
                        <span class="sf-pdp__mrp">{{ money($product->price) }}</span>
                        <span class="sf-pdp__now">{{ money($product->sale_price) }}</span>
                        <span class="sf-chip sf-chip--save">{{ money($saving) }} off</span>
                    @else
                        <span class="sf-pdp__now">{{ money($product->price) }}</span>
                    @endif
                </div>
                <p class="sf-pdp__tax">{{ setting('shop_tax_inclusive') ? 'Inclusive of all taxes' : 'Taxes calculated at checkout' }}</p>

                @if ($product->inStock())
                    <form method="POST" action="{{ route('cart.add') }}">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">

                        @if ($product->type === 'variable' && $product->variants->isNotEmpty())
                            <div class="sf-field sf-mt">
                                <label for="sf-variant" class="sf-label">Choose an option</label>
                                <select name="variant_id" id="sf-variant" required class="sf-select">
                                    @foreach ($product->variants->where('is_active', true) as $variant)
                                        <option value="{{ $variant->id }}" @disabled(! $variant->inStock())>
                                            {{ $variant->name }}
                                            @if ($variant->optionsLabel()) — {{ $variant->optionsLabel() }} @endif
                                            — {{ money($variant->effectivePrice()) }}
                                            @unless ($variant->inStock()) (sold out) @endunless
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="sf-row sf-mt">
                            <span class="sf-qty" data-qty>
                                <button type="button" data-qty-step="-1" aria-label="Decrease quantity">&minus;</button>
                                <label for="sf-qty-input" class="sf-sr">Quantity</label>
                                <input type="number" name="quantity" id="sf-qty-input" value="1" min="1" max="99">
                                <button type="button" data-qty-step="1" aria-label="Increase quantity">+</button>
                            </span>
                        </div>

                        <div class="sf-pdp__buy">
                            <button class="sf-btn sf-btn--primary sf-btn--lg">
                                @include('theme::partials.icon', ['name' => 'bag'])
                                Add to bag
                            </button>

                            {{-- Adds to the bag, then jumps straight to checkout.
                                 Without JavaScript it is an ordinary add-to-bag. --}}
                            <button type="submit" class="sf-btn sf-btn--ghost sf-btn--lg"
                                    data-buy-now="{{ route('checkout.index') }}">
                                Buy now
                                @include('theme::partials.icon', ['name' => 'arrow-up-right'])
                            </button>
                        </div>
                    </form>
                @else
                    <p class="sf-pdp__buy">
                        <span class="sf-btn sf-btn--ghost sf-btn--lg" aria-disabled="true">Out of stock</span>
                    </p>
                @endif

                <p class="sf-pdp__stock">
                    @if ($product->inStock())
                        @if ($product->isLowStock())
                            <span class="sf-chip sf-chip--low">Only {{ $product->stock }} left</span>
                        @else
                            <span class="sf-ok sf-b sf-small">In stock, ready to ship</span>
                        @endif
                    @else
                        <span class="sf-bad sf-b sf-small">Currently unavailable</span>
                    @endif
                </p>

                @if ($product->isDigital())
                    <div class="sf-offer">
                        <span class="sf-offer__icon">@include('theme::partials.icon', ['name' => 'download'])</span>
                        <span>
                            <b>Instant download</b>
                            <span>Your file is available as soon as the payment clears.</span>
                        </span>
                    </div>
                @elseif ($saving > 0)
                    <div class="sf-offer">
                        <span class="sf-offer__icon">@include('theme::partials.icon', ['name' => 'tag'])</span>
                        <span>
                            <b>You save {{ money($saving) }} on this item</b>
                            <span>Discount already applied — no code needed.</span>
                        </span>
                    </div>
                @endif

                <div class="sf-trust">
                    @foreach ([
                        ['verified', 'Genuine products'],
                        ['leaf', 'Recyclable packaging'],
                        ['shield', 'Secure payments'],
                        ['truck', 'Tracked delivery'],
                    ] as [$trustIcon, $trustLabel])
                        <div class="sf-trust__item">
                            <span class="sf-trust__ring">@include('theme::partials.icon', ['name' => $trustIcon])</span>
                            <span>{{ $trustLabel }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- Accordions ------------------------------------------- --}}
                <div class="sf-acc">
                    @if ($product->description || $product->short_description)
                        <div class="sf-acc__item">
                            <button type="button" class="sf-acc__btn" data-acc-btn aria-expanded="true" aria-controls="sf-acc-desc">
                                Description
                                @include('theme::partials.icon', ['name' => 'chevron-down'])
                            </button>
                            <div class="sf-acc__panel is-open" id="sf-acc-desc">
                                @if ($product->description)
                                    <div class="prose-content">{!! rich_content($product->description) !!}</div>
                                @else
                                    <p>{{ $product->short_description }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="sf-acc__item">
                        <button type="button" class="sf-acc__btn" data-acc-btn aria-expanded="false" aria-controls="sf-acc-details">
                            Product details
                            @include('theme::partials.icon', ['name' => 'chevron-down'])
                        </button>
                        <div class="sf-acc__panel" id="sf-acc-details">
                            <dl>
                                @if ($product->sku)
                                    <dt>SKU</dt><dd>{{ $product->sku }}</dd>
                                @endif
                                @if ($product->categories->isNotEmpty())
                                    <dt>Department</dt>
                                    <dd>
                                        @foreach ($product->categories as $item)
                                            <a href="{{ $item->url() }}">{{ $item->name }}</a>{{ $loop->last ? '' : ', ' }}
                                        @endforeach
                                    </dd>
                                @endif
                                @if ($product->weight)
                                    <dt>Weight</dt><dd>{{ $product->weight }}</dd>
                                @endif
                                @if ($product->dimensions)
                                    <dt>Dimensions</dt><dd>{{ $product->dimensions }}</dd>
                                @endif
                                <dt>Delivery</dt>
                                <dd>{{ $product->isDigital() ? 'Digital download' : 'Shipped to your address' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Reviews -------------------------------------------------------- --}}
    <div class="sf-wrap">
        <div class="sf-section__head">
            <div>
                <h2 class="sf-section__title">Reviews</h2>
                <p class="sf-section__sub">What other customers thought</p>
            </div>
        </div>

        <div class="sf-reviews">
            <div>
                @forelse ($product->approvedReviews as $review)
                    <div class="sf-review">
                        <div class="sf-review__head">
                            <p class="sf-b">{{ $review->displayName() }}</p>
                            <span class="sf-card__stars">{{ str_repeat('★', $review->rating) }}</span>
                        </div>
                        @if ($review->title)
                            <p class="sf-b sf-mt">{{ $review->title }}</p>
                        @endif
                        @if ($review->body)
                            <p class="sf-muted sf-small">{{ $review->body }}</p>
                        @endif
                        <p class="sf-review__meta">
                            {{ format_date($review->created_at) }}
                            @if ($review->verified_purchase)
                                <span class="sf-review__verified">&middot; Verified purchase</span>
                            @endif
                        </p>
                    </div>
                @empty
                    <p class="sf-muted sf-small">No reviews yet. Be the first to write one.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('shop.review', $product->slug) }}" class="sf-reviewform">
                @csrf
                <p class="sf-panel__title">Write a review</p>

                @guest
                    <div class="sf-field">
                        <label for="sf-review-name" class="sf-label">Your name</label>
                        <input type="text" name="author_name" id="sf-review-name" required class="sf-input">
                    </div>
                @endguest

                <div class="sf-field">
                    <label for="sf-review-rating" class="sf-label">Rating</label>
                    <select name="rating" id="sf-review-rating" required class="sf-select">
                        @for ($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}">{{ str_repeat('★', $i) }} — {{ $i }} out of 5</option>
                        @endfor
                    </select>
                </div>

                <div class="sf-field">
                    <label for="sf-review-title" class="sf-label">Headline (optional)</label>
                    <input type="text" name="title" id="sf-review-title" class="sf-input">
                </div>

                <div class="sf-field">
                    <label for="sf-review-body" class="sf-label">Your review</label>
                    <textarea name="body" id="sf-review-body" rows="4" class="sf-textarea"></textarea>
                </div>

                <button class="sf-btn sf-btn--primary sf-btn--block sf-mt">Submit review</button>
                <p class="sf-help sf-mt">Reviews are published after moderation.</p>
            </form>
        </div>
    </div>

    @include('theme::partials.rail', [
        'title' => 'You may also like',
        'subtitle' => 'More from the same shelves',
        'products' => $related,
        'moreUrl' => $category?->url() ?? route('shop.index'),
        'soft' => true,
    ])
    @endregion
@endsection
