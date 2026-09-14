@extends('theme::layout')

@section('content')
    @region('shop_index')

    <div class="mx-auto max-w-6xl px-4 py-14">
        <header class="mb-10">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Shop</h1>
            <p class="mt-2 text-slate-600">{{ $products->total() }} {{ Str::plural('product', $products->total()) }}</p>
        </header>

        <div class="grid gap-10 lg:grid-cols-4">
            @include('theme::shop.sidebar')

            <div class="lg:col-span-3">
                @include('theme::shop.toolbar')

                @if ($products->isEmpty())
                    <p class="rounded-2xl border border-dashed border-slate-300 py-16 text-center text-slate-500">
                        No products match your filters.
                    </p>
                @else
                    <div class="grid grid-cols-2 gap-5 lg:grid-cols-3">
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
