{{-- A horizontally scrolling row of products.

     @include('theme::partials.rail', [
         'title'    => 'Best sellers',
         'subtitle' => 'What everyone is reading',   (optional)
         'products' => $collection,
         'moreUrl'  => route('shop.index'),          (optional)
         'soft'     => true,                         (optional tinted band)
     ])
--}}
@if ($products->isNotEmpty())
    <section class="sf-section {{ ($soft ?? false) ? 'sf-section--soft' : '' }}">
        <div class="sf-wrap" data-rail-group>
            <div class="sf-section__head">
                <div>
                    <h2 class="sf-section__title">{{ $title }}</h2>
                    @if ($subtitle ?? null)
                        <p class="sf-section__sub">{{ $subtitle }}</p>
                    @endif
                </div>

                <div class="sf-section__tools">
                    <button type="button" class="sf-railbtn" data-rail-prev aria-label="Scroll left">
                        @include('theme::partials.icon', ['name' => 'chevron-left'])
                    </button>
                    <button type="button" class="sf-railbtn" data-rail-next aria-label="Scroll right">
                        @include('theme::partials.icon', ['name' => 'chevron-right'])
                    </button>
                    @if ($moreUrl ?? null)
                        <a href="{{ $moreUrl }}" class="sf-btn sf-btn--ghost sf-btn--sm">
                            Show all
                            @include('theme::partials.icon', ['name' => 'arrow-up-right'])
                        </a>
                    @endif
                </div>
            </div>

            <div class="sf-rail" data-rail>
                @foreach ($products as $product)
                    @include('theme::partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </div>
    </section>
@endif
