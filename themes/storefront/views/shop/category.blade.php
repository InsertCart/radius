@extends('theme::layout')

@section('content')
    <div class="sf-wrap">
        <nav class="sf-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <a href="{{ route('shop.index') }}">Shop</a>
            @foreach ($category->ancestors() as $ancestor)
                <span>/</span>
                <a href="{{ $ancestor->url() }}">{{ $ancestor->name }}</a>
            @endforeach
            <span>/</span>
            <b>{{ $category->name }}</b>
        </nav>

        <header class="sf-phead">
            <h1>{{ $category->name }}</h1>
            @if ($category->description)
                <p>{{ $category->description }}</p>
            @endif
            <p class="sf-small sf-muted">{{ $products->total() }} {{ Str::plural('product', $products->total()) }}</p>
        </header>

        <div class="sf-listing">
            @include('theme::shop.sidebar')

            <div>
                @include('theme::shop.toolbar')

                @if ($products->isEmpty())
                    <p class="sf-empty">Nothing in this department yet. Check back soon.</p>
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
@endsection
