@extends('theme::layout')

@section('content')
    <section class="border-b border-slate-100 bg-gradient-to-b from-slate-50 to-white">
        <div class="mx-auto max-w-6xl px-4 py-20 text-center sm:py-28">
            <h1 class="mx-auto max-w-3xl text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl">
                {{ setting('site_name', config('app.name')) }}
            </h1>
            @if (setting('site_tagline'))
                <p class="mx-auto mt-5 max-w-2xl text-lg text-slate-600">{{ setting('site_tagline') }}</p>
            @endif

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                @module('shop')
                    <a href="{{ route('shop.index') }}" class="rounded-lg px-6 py-3 text-sm font-semibold text-white btn-brand">
                        Browse the shop
                    </a>
                @endmodule
                @module('blog')
                    <a href="{{ route('blog.index') }}" class="rounded-lg border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Read the blog
                    </a>
                @endmodule
            </div>
        </div>
    </section>

    @if ($featuredProducts->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 py-16">
            <div class="mb-8 flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Featured products</h2>
                    <p class="mt-1 text-sm text-slate-500">A hand-picked selection</p>
                </div>
                <a href="{{ route('shop.index') }}" class="shrink-0 text-sm font-medium text-brand hover:underline">View all &rarr;</a>
            </div>

            <div class="grid grid-cols-2 gap-5 lg:grid-cols-4">
                @foreach ($featuredProducts as $product)
                    @include('theme::partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

    @if ($featuredPosts->isNotEmpty())
        <section class="border-t border-slate-100 bg-slate-50">
            <div class="mx-auto max-w-6xl px-4 py-16">
                <div class="mb-8 flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900">From the blog</h2>
                        <p class="mt-1 text-sm text-slate-500">The latest writing</p>
                    </div>
                    <a href="{{ route('blog.index') }}" class="shrink-0 text-sm font-medium text-brand hover:underline">All posts &rarr;</a>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    @foreach ($featuredPosts->take(3) as $post)
                        @include('theme::partials.post-card', ['post' => $post])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($featuredPosts->isEmpty() && $featuredProducts->isEmpty())
        <section class="mx-auto max-w-2xl px-4 py-20 text-center">
            <h2 class="text-xl font-semibold text-slate-900">Your site is ready</h2>
            <p class="mt-3 text-slate-600">
                Nothing has been published yet. Sign in to the admin panel to add pages, write a
                post, or list your first product.
            </p>
            @staff
                <a href="{{ route('admin.dashboard') }}" class="mt-6 inline-block rounded-lg px-6 py-3 text-sm font-semibold text-white btn-brand">
                    Open the admin panel
                </a>
            @endstaff
        </section>
    @endif
@endsection
