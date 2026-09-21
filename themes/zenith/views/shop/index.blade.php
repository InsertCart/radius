@extends('theme::layout')

@section('content')
    @region('shop_index')
    <div class="zn-wrap">
        <nav class="zn-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <b>Shop Collection</b>
        </nav>

        <header style="margin-bottom: 36px;">
            <h1 style="font-size: clamp(30px, 4vw, 44px); font-weight: 800; margin-bottom: 8px;">
                {{ request('q') ? 'Results for “'.request('q').'”' : 'Curated Collection' }}
            </h1>
            <p style="color: var(--zn-muted); font-size: 15px; margin: 0;">
                {{ $products->total() }} {{ Str::plural('piece', $products->total()) }} available for acquisition.
            </p>
        </header>

        <div class="zn-shop-layout">
            @include('theme::shop.sidebar')

            <div>
                @if ($products->isEmpty())
                    <div style="text-align: center; padding: 70px 20px; background: var(--zn-surface); border: 1px solid var(--zn-border); border-radius: var(--zn-radius-xl);">
                        <h3 style="font-size: 20px; font-weight: 700; margin-bottom: 8px;">No matching pieces</h3>
                        <p style="color: var(--zn-muted); font-size: 14px; margin-bottom: 20px;">Try adjusting your filter criteria or price parameters.</p>
                        <a href="{{ route('shop.index') }}" class="zn-btn zn-btn--primary zn-btn--sm">View All Pieces</a>
                    </div>
                @else
                    <div class="zn-grid--products">
                        @foreach ($products as $product)
                            @include('theme::partials.product-card', ['product' => $product])
                        @endforeach
                    </div>

                    {{ $products->links('theme::partials.pagination') }}
                @endif
            </div>
        </div>
    </div>
    @endregion
@endsection
