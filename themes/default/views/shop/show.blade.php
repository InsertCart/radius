@extends('theme::layout')

@section('content')
    @region('product', ['model' => $product])
    <div class="mx-auto max-w-6xl px-4 py-14">
        <nav class="mb-6 text-sm text-slate-500">
            <a href="{{ route('shop.index') }}" class="hover:text-slate-900">Shop</a>
            @if ($product->categories->first())
                <span class="mx-1">/</span>
                <a href="{{ $product->categories->first()->url() }}" class="hover:text-slate-900">
                    {{ $product->categories->first()->name }}
                </a>
            @endif
        </nav>

        <div class="grid gap-10 lg:grid-cols-2"
             x-data="{ variant: {{ $product->variants->first()?->id ?? 'null' }} }">
            <div>
                <div class="aspect-square overflow-hidden rounded-2xl bg-slate-100">
                    @if ($product->imageUrl())
                        <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                    @endif
                </div>
            </div>

            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">{{ $product->name }}</h1>

                @if ($product->review_count > 0)
                    <div class="mt-2 flex items-center gap-2 text-sm">
                        <span class="text-amber-500">{{ str_repeat('★', (int) round($product->rating)) }}{{ str_repeat('☆', 5 - (int) round($product->rating)) }}</span>
                        <span class="text-slate-500">{{ $product->rating }} out of 5 &middot; {{ $product->review_count }} reviews</span>
                    </div>
                @endif

                <div class="mt-5 flex items-baseline gap-3">
                    @if ($product->isOnSale())
                        <span class="text-3xl font-bold text-slate-900">{{ money($product->sale_price) }}</span>
                        <span class="text-lg text-slate-400 line-through">{{ money($product->price) }}</span>
                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-700">
                            Save {{ $product->discountPercent() }}%
                        </span>
                    @else
                        <span class="text-3xl font-bold text-slate-900">{{ money($product->price) }}</span>
                    @endif
                </div>

                @if ($product->short_description)
                    <p class="mt-5 text-slate-600">{{ $product->short_description }}</p>
                @endif

                <div class="mt-5 text-sm">
                    @if ($product->inStock())
                        <span class="text-emerald-600">In stock</span>
                        @if ($product->isLowStock())
                            <span class="text-amber-600">&middot; only {{ $product->stock }} left</span>
                        @endif
                    @else
                        <span class="text-rose-600">Out of stock</span>
                    @endif
                </div>

                @if ($product->inStock())
                    <form method="POST" action="{{ route('cart.add') }}" class="mt-7 space-y-4">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">

                        @if ($product->type === 'variable' && $product->variants->isNotEmpty())
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Choose an option</label>
                                <select name="variant_id" x-model="variant" required
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                                    @foreach ($product->variants->where('is_active', true) as $v)
                                        <option value="{{ $v->id }}" @disabled(! $v->inStock())>
                                            {{ $v->name }}
                                            @if ($v->optionsLabel()) — {{ $v->optionsLabel() }} @endif
                                            — {{ money($v->effectivePrice()) }}
                                            @unless ($v->inStock()) (sold out) @endunless
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="flex gap-3">
                            <input type="number" name="quantity" value="1" min="1" max="99"
                                   class="w-20 rounded-lg border border-slate-300 px-3 py-2.5 text-center text-sm">
                            <button class="flex-1 rounded-lg px-6 py-3 text-sm font-semibold text-white btn-brand">
                                Add to cart
                            </button>
                        </div>
                    </form>
                @endif

                <dl class="mt-8 space-y-2 border-t border-slate-100 pt-6 text-sm">
                    @if ($product->sku)
                        <div class="flex gap-2"><dt class="text-slate-500">SKU:</dt><dd class="text-slate-700">{{ $product->sku }}</dd></div>
                    @endif
                    @if ($product->categories->isNotEmpty())
                        <div class="flex gap-2">
                            <dt class="text-slate-500">Category:</dt>
                            <dd class="text-slate-700">
                                @foreach ($product->categories as $category)
                                    <a href="{{ $category->url() }}" class="hover:text-brand">{{ $category->name }}</a>{{ ! $loop->last ? ',' : '' }}
                                @endforeach
                            </dd>
                        </div>
                    @endif
                    @if ($product->isDigital())
                        <div class="flex gap-2"><dt class="text-slate-500">Delivery:</dt><dd class="text-slate-700">Instant download after payment</dd></div>
                    @endif
                </dl>
            </div>
        </div>

        @if ($product->description)
            <section class="mt-16 border-t border-slate-100 pt-10">
                <h2 class="text-xl font-bold text-slate-900">Description</h2>
                <div class="prose-content mt-4 max-w-3xl text-slate-700">{!! $product->description !!}</div>
            </section>
        @endif

        <section class="mt-16 border-t border-slate-100 pt-10">
            <h2 class="text-xl font-bold text-slate-900">Reviews</h2>

            <div class="mt-6 grid gap-10 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    @forelse ($product->approvedReviews as $review)
                        <div class="border-b border-slate-100 py-5 first:pt-0">
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="font-medium text-slate-900">{{ $review->displayName() }}</p>
                                <span class="text-amber-500">{{ str_repeat('★', $review->rating) }}</span>
                            </div>
                            @if ($review->title)
                                <p class="mt-1 font-medium text-slate-800">{{ $review->title }}</p>
                            @endif
                            @if ($review->body)
                                <p class="mt-1 text-sm text-slate-600">{{ $review->body }}</p>
                            @endif
                            <p class="mt-2 text-xs text-slate-400">
                                {{ format_date($review->created_at) }}
                                @if ($review->verified_purchase)
                                    <span class="ml-1 text-emerald-600">Verified purchase</span>
                                @endif
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No reviews yet. Be the first.</p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('shop.review', $product->slug) }}" class="space-y-3">
                    @csrf
                    <p class="text-sm font-semibold text-slate-900">Write a review</p>

                    @guest
                        <input type="text" name="author_name" required placeholder="Your name"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @endguest

                    <select name="rating" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @for ($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}">{{ str_repeat('★', $i) }} — {{ $i }} out of 5</option>
                        @endfor
                    </select>

                    <input type="text" name="title" placeholder="Headline (optional)"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">

                    <textarea name="body" rows="4" placeholder="What did you think?"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>

                    <button class="w-full rounded-lg px-4 py-2.5 text-sm font-semibold text-white btn-brand">
                        Submit review
                    </button>
                    <p class="text-xs text-slate-400">Reviews are published after moderation.</p>
                </form>
            </div>
        </section>

        @if ($related->isNotEmpty())
            <section class="mt-16 border-t border-slate-100 pt-10">
                <h2 class="mb-6 text-xl font-bold text-slate-900">You may also like</h2>
                <div class="grid grid-cols-2 gap-5 lg:grid-cols-4">
                    @foreach ($related as $item)
                        @include('theme::partials.product-card', ['product' => $item])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
    @endregion
@endsection
