<article class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 transition hover:shadow-md">
    <a href="{{ $product->url() }}" class="relative block">
        <div class="aspect-square overflow-hidden bg-slate-100">
            @if ($product->imageUrl())
                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy"
                     class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
            @endif
        </div>

        @if ($product->isOnSale())
            <span class="absolute left-3 top-3 rounded-full bg-rose-500 px-2 py-0.5 text-xs font-bold text-white">
                −{{ $product->discountPercent() }}%
            </span>
        @endif

        @unless ($product->inStock())
            <span class="absolute right-3 top-3 rounded-full bg-slate-900/80 px-2 py-0.5 text-xs font-medium text-white">
                Sold out
            </span>
        @endunless
    </a>

    <div class="flex flex-1 flex-col p-4">
        <h3 class="text-sm font-medium leading-snug text-slate-900">
            <a href="{{ $product->url() }}" class="hover:text-brand">{{ $product->name }}</a>
        </h3>

        @if ($product->review_count > 0)
            <div class="mt-1 flex items-center gap-1 text-xs text-amber-500">
                <span>{{ str_repeat('★', (int) round($product->rating)) }}</span>
                <span class="text-slate-400">({{ $product->review_count }})</span>
            </div>
        @endif

        <div class="mt-auto pt-3">
            @if ($product->isOnSale())
                <span class="font-semibold text-slate-900">{{ money($product->sale_price) }}</span>
                <span class="ml-1 text-sm text-slate-400 line-through">{{ money($product->price) }}</span>
            @else
                <span class="font-semibold text-slate-900">{{ money($product->price) }}</span>
            @endif
        </div>

        @if ($product->inStock() && $product->type === 'simple')
            <form method="POST" action="{{ route('cart.add') }}" class="mt-3">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                <button class="w-full rounded-lg px-3 py-2 text-sm font-medium text-white btn-brand">Add to cart</button>
            </form>
        @else
            <a href="{{ $product->url() }}"
               class="mt-3 block rounded-lg border border-slate-300 px-3 py-2 text-center text-sm font-medium hover:bg-slate-50">
                View details
            </a>
        @endif
    </div>
</article>
