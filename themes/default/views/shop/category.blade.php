@extends('theme::layout')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-14">
        <nav class="mb-4 text-sm text-slate-500">
            <a href="{{ route('shop.index') }}" class="hover:text-slate-900">Shop</a>
            <span class="mx-1">/</span>
            <span class="text-slate-700">{{ $category->name }}</span>
        </nav>

        <header class="mb-10">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ $category->name }}</h1>
            @if ($category->description)
                <p class="mt-2 max-w-2xl text-slate-600">{{ $category->description }}</p>
            @endif
        </header>

        <div class="grid gap-10 lg:grid-cols-4">
            @include('theme::shop.sidebar')

            <div class="lg:col-span-3">
                @include('theme::shop.toolbar')

                @if ($products->isEmpty())
                    <p class="rounded-2xl border border-dashed border-slate-300 py-16 text-center text-slate-500">
                        Nothing in this category yet.
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
@endsection
