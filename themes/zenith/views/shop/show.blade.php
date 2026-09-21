@extends('theme::layout')

@section('content')
    @php
        $category = $product->categories->first();

        $images = collect();
        if ($product->imageUrl()) {
            $images->put($product->imageUrl(), $product->name);
        }
        foreach ($product->gallery as $media) {
            $images->put($media->url, $media->alt ?: $product->name);
        }

        $saving = $product->isOnSale() ? $product->price - $product->sale_price : 0;
    @endphp

    @region('product', ['model' => $product])
    <div class="zn-wrap">
        <nav class="zn-crumbs" aria-label="Breadcrumb">
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

        <div class="zn-product-detail">
            {{-- Product Gallery --}}
            <div>
                <div class="zn-gallery__main">
                    @if ($images->isNotEmpty())
                        <img src="{{ $images->keys()->first() }}" alt="{{ $product->name }}" data-gallery-main>
                    @else
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--zn-bg-alt); color: var(--zn-muted);">
                            No image available
                        </div>
                    @endif
                </div>

                @if ($images->count() > 1)
                    <div class="zn-gallery__thumbs">
                        @foreach ($images as $url => $alt)
                            <div class="zn-gallery__thumb {{ $loop->first ? 'active' : '' }}" data-gallery-thumb data-src="{{ $url }}">
                                <img src="{{ $url }}" alt="{{ $alt }}">
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Product Buy Box & Info --}}
            <div>
                @if ($category)
                    <div class="zn-pinfo__eyebrow">{{ $category->name }}</div>
                @endif

                <h1 class="zn-pinfo__title">{{ $product->name }}</h1>

                @if ($product->review_count > 0)
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 14px; margin-bottom: 18px; color: #f59e0b;">
                        @for ($i = 0; $i < 5; $i++)
                            @if ($i < round($product->rating)) &#9733; @else &#9734; @endif
                        @endfor
                        <span style="color: var(--zn-muted); font-size: 13px;">({{ $product->review_count }} reviews)</span>
                    </div>
                @endif

                <div class="zn-pinfo__price-box">
                    @if ($product->isOnSale())
                        <span class="zn-pinfo__price">{{ money($product->sale_price) }}</span>
                        <span class="zn-pinfo__price--was">{{ money($product->price) }}</span>
                        <span class="zn-badge zn-badge--rose">Save {{ money($saving) }}</span>
                    @else
                        <span class="zn-pinfo__price">{{ money($product->price) }}</span>
                    @endif
                </div>

                <div style="font-size: 13px; color: var(--zn-muted); margin-bottom: 24px;">
                    {{ setting('shop_tax_inclusive') ? 'Tax included.' : 'Taxes calculated at checkout.' }}
                    @if ($product->inStock())
                        <span style="color: var(--zn-emerald); font-weight: 600; margin-left: 8px;">&bull; In stock, ready to ship</span>
                    @else
                        <span style="color: var(--zn-rose); font-weight: 600; margin-left: 8px;">&bull; Out of stock</span>
                    @endif
                </div>

                @if ($product->inStock())
                    <form method="POST" action="{{ route('cart.add') }}">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">

                        @if ($product->type === 'variable' && $product->variants->isNotEmpty())
                            <div style="margin-bottom: 20px;">
                                <label for="zn-variant" style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em;">Edition / Variant</label>
                                <select name="variant_id" id="zn-variant" required
                                        style="width: 100%; height: 44px; padding: 0 14px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 14px;">
                                    @foreach ($product->variants->where('is_active', true) as $variant)
                                        <option value="{{ $variant->id }}" @disabled(! $variant->inStock())>
                                            {{ $variant->name }} @if ($variant->optionsLabel()) &mdash; {{ $variant->optionsLabel() }} @endif &mdash; {{ money($variant->effectivePrice()) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div style="display: flex; gap: 14px; margin-bottom: 28px;">
                            <div style="width: 100px;">
                                <label for="quantity" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase;">Qty</label>
                                <input type="number" name="quantity" id="quantity" value="1" min="1" max="99"
                                       style="width: 100%; height: 46px; text-align: center; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 15px; font-weight: 700; box-sizing: border-box;">
                            </div>

                            <div style="flex: 1; display: flex; align-items: flex-end; gap: 10px;">
                                <button type="submit" class="zn-btn zn-btn--primary zn-btn--lg" style="flex: 1;">
                                    @include('theme::partials.icon', ['name' => 'bag'])
                                    Add to Bag
                                </button>
                            </div>
                        </div>
                    </form>
                @endif

                {{-- Trust Pillars / Guarantees --}}
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; padding: 18px; background: var(--zn-surface); border: 1px solid var(--zn-border); border-radius: var(--zn-radius-md); margin-bottom: 30px;">
                    <div style="display: flex; align-items: center; gap: 10px; font-size: 13px;">
                        <span style="color: var(--zn-accent);">@include('theme::partials.icon', ['name' => 'truck', 'class' => 'w-5 h-5'])</span>
                        <span>Free Global Delivery</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; font-size: 13px;">
                        <span style="color: var(--zn-accent);">@include('theme::partials.icon', ['name' => 'shield', 'class' => 'w-5 h-5'])</span>
                        <span>Lifetime Authenticity</span>
                    </div>
                </div>

                {{-- Product Accordions --}}
                <div class="zn-accordion">
                    <div class="zn-accordion__item">
                        <button type="button" class="zn-accordion__btn" data-accordion-btn aria-expanded="true">
                            <span>Overview & Craftsmanship</span>
                            <span class="zn-accordion__icon">@include('theme::partials.icon', ['name' => 'chevron-down'])</span>
                        </button>
                        <div class="zn-accordion__content prose-content">
                            {!! $product->description !!}
                        </div>
                    </div>

                    <div class="zn-accordion__item">
                        <button type="button" class="zn-accordion__btn" data-accordion-btn aria-expanded="false">
                            <span>Delivery, Sizing & Returns</span>
                            <span class="zn-accordion__icon">@include('theme::partials.icon', ['name' => 'chevron-down'])</span>
                        </button>
                        <div class="zn-accordion__content" hidden>
                            <p>All items are packaged in archival climate-sealed presentation boxes. Orders are dispatched within 24 hours via tracked courier. We offer a 30-day complimentary exchange or return policy for unworn items.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endregion
@endsection
