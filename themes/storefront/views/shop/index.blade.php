@extends('theme::layout')

@section('content')
    @region('shop_index')

    <div class="sf-wrap">
        <nav class="sf-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <b>Shop</b>
        </nav>

        <header class="sf-phead">
            <h1>{{ request('q') ? 'Results for “'.request('q').'”' : 'All products' }}</h1>
            <p>{{ $products->total() }} {{ Str::plural('product', $products->total()) }} to browse.</p>
        </header>

        <div class="sf-listing">
            @include('theme::shop.sidebar')

            <div>
                @include('theme::shop.toolbar')

                @if ($products->isEmpty())
                    <p class="sf-empty">No products match your filters. Try widening the price range or clearing the search.</p>
                @else
                    <div class="sf-grid">
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
