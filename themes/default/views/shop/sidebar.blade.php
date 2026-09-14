<aside class="space-y-8">
    @if ($categories->isNotEmpty())
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-900">Categories</h2>
            <ul class="mt-3 space-y-1.5">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ $category->url() }}"
                           class="flex items-center justify-between text-sm hover:text-brand
                                  {{ (isset($category) && request()->is('shop/category/'.$category->slug)) ? 'font-medium text-brand' : 'text-slate-600' }}">
                            <span>{{ $category->name }}</span>
                            <span class="text-xs text-slate-400">{{ $category->products_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" class="space-y-3">
        {{-- Carry the current search and sort through the price filter. --}}
        @foreach (['q', 'sort'] as $carry)
            @if (request()->filled($carry))
                <input type="hidden" name="{{ $carry }}" value="{{ request($carry) }}">
            @endif
        @endforeach

        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-900">Price</h2>
        <div class="flex items-center gap-2">
            <input type="number" name="min" value="{{ request('min') }}" placeholder="Min" min="0"
                   class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
            <span class="text-slate-400">&ndash;</span>
            <input type="number" name="max" value="{{ request('max') }}" placeholder="Max" min="0"
                   class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="in_stock" value="1" @checked(request()->boolean('in_stock'))
                   class="h-4 w-4 rounded border-slate-300">
            In stock only
        </label>

        <button class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50">
            Apply filters
        </button>
    </form>
</aside>
