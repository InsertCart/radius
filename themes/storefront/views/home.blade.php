@extends('theme::layout')

@section('content')
    @php
        // The controller hands over eight featured products and six posts. To
        // get the multi-row storefront look, the theme adds a rail per top
        // category — capped at three so a large catalogue cannot turn the
        // homepage into dozens of queries.
        $categoryRails = collect();
        $circleCategories = collect();

        if (modules()->enabled('shop')) {
            $circleCategories = \App\Models\Category::shop()
                ->active()
                ->roots()
                ->orderBy('sort_order')
                ->limit(10)
                ->get();

            // Queried one category at a time on purpose: a "limit" inside an
            // eager-load constraint caps the combined result, which would hand
            // the first category every row and the rest none.
            $categoryRails = \App\Models\Category::shop()
                ->active()
                ->roots()
                ->has('products')
                ->orderBy('sort_order')
                ->limit(3)
                ->get()
                ->map(fn ($category) => [
                    'category' => $category,
                    'products' => $category->products()
                        ->published()
                        ->inStock()
                        ->orderByDesc('is_featured')
                        ->orderByDesc('products.created_at')
                        ->limit(10)
                        ->get(),
                ])
                ->filter(fn ($rail) => $rail['products']->isNotEmpty())
                ->values();
        }
    @endphp

    @region('hero')
        <section class="sf-hero">
            <div class="sf-hero__inner">
                <p class="sf-hero__eyebrow">{{ setting('site_name', config('app.name')) }}</p>
                <h1>{{ setting('site_tagline') ?: 'Everything worth having, in one place.' }}</h1>
                <p>
                    Browse the full range, save what catches your eye, and check out in a
                    couple of taps.
                </p>

                <div class="sf-hero__cta">
                    @module('shop')
                        <a href="{{ route('shop.index') }}" class="sf-btn sf-btn--dark sf-btn--lg">Start shopping</a>
                    @endmodule
                    @module('blog')
                        <a href="{{ route('blog.index') }}" class="sf-btn sf-btn--ghost sf-btn--lg">Read the blog</a>
                    @endmodule
                </div>
            </div>
        </section>
    @endregion

    @module('shop')
        @include('theme::partials.rail', [
            'title' => 'Featured this week',
            'subtitle' => 'Hand-picked by our buyers',
            'products' => $featuredProducts,
            'moreUrl' => route('shop.index'),
        ])

        @if ($circleCategories->isNotEmpty())
            <section class="sf-section sf-section--soft">
                <div class="sf-wrap">
                    <div class="sf-section__head">
                        <div>
                            <h2 class="sf-section__title">Shop by department</h2>
                            <p class="sf-section__sub">Find your way around the store</p>
                        </div>
                    </div>

                    <div class="sf-circles">
                        @foreach ($circleCategories as $category)
                            <a href="{{ $category->url() }}" class="sf-circle">
                                <span class="sf-circle__ring">
                                    @if ($category->image)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('cms.media.disk'))->url($category->image) }}"
                                             alt="" loading="lazy">
                                    @else
                                        {{ \Illuminate\Support\Str::substr($category->name, 0, 1) }}
                                    @endif
                                </span>
                                <span class="sf-circle__label">{{ $category->name }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @foreach ($categoryRails as $rail)
            @include('theme::partials.rail', [
                'title' => $rail['category']->name,
                'subtitle' => $rail['category']->description
                    ? \Illuminate\Support\Str::limit(strip_tags($rail['category']->description), 90)
                    : null,
                'products' => $rail['products'],
                'moreUrl' => $rail['category']->url(),
                'soft' => $loop->odd,
            ])
        @endforeach

        @if ($circleCategories->count() >= 3)
            <section class="sf-section">
                <div class="sf-wrap">
                    <div class="sf-tiles">
                        @foreach ($circleCategories->take(3) as $tile)
                            <a href="{{ $tile->url() }}" class="sf-tile sf-tile--{{ ['a', 'b', 'c'][$loop->index] }} {{ $tile->image ? 'sf-tile--shade' : '' }}">
                                @if ($tile->image)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('cms.media.disk'))->url($tile->image) }}"
                                         alt="" loading="lazy">
                                @endif
                                <span class="sf-tile__body">
                                    <span class="sf-tile__kicker">Collection</span>
                                    <span class="sf-tile__title">{{ $tile->name }}</span>
                                    <span class="sf-tile__link">
                                        Shop now
                                        @include('theme::partials.icon', ['name' => 'arrow-up-right'])
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    @endmodule

    @if ($featuredPosts->isNotEmpty())
        <section class="sf-section sf-section--soft">
            <div class="sf-wrap">
                <div class="sf-section__head">
                    <div>
                        <h2 class="sf-section__title">From the reading desk</h2>
                        <p class="sf-section__sub">News, guides and recommendations</p>
                    </div>
                    <div class="sf-section__tools">
                        <a href="{{ route('blog.index') }}" class="sf-btn sf-btn--ghost sf-btn--sm">
                            Show all
                            @include('theme::partials.icon', ['name' => 'arrow-up-right'])
                        </a>
                    </div>
                </div>

                <div class="sf-grid sf-grid--posts">
                    @foreach ($featuredPosts->take(3) as $post)
                        @include('theme::partials.post-card', ['post' => $post])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @region('home_bottom')@endregion

    @if ($featuredPosts->isEmpty() && $featuredProducts->isEmpty())
        <section class="sf-section">
            <div class="sf-wrap sf-wrap--narrow sf-center">
                <h2 class="sf-section__title">Your storefront is ready</h2>
                <p class="sf-mt sf-muted">
                    Nothing has been published yet. Sign in to the admin panel to add your
                    first products, build a category menu and design the homepage hero.
                </p>
                @staff
                    <p class="sf-mt">
                        <a href="{{ route('admin.dashboard') }}" class="sf-btn sf-btn--primary sf-btn--lg">Open the admin panel</a>
                    </p>
                @endstaff
            </div>
        </section>
    @endif
@endsection
