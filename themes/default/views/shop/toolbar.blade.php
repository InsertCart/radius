<form method="GET" class="mb-8 flex flex-wrap items-center gap-3" role="search" data-radius-search="product">
    @foreach (['min', 'max', 'in_stock'] as $carry)
        @if (request()->filled($carry))
            <input type="hidden" name="{{ $carry }}" value="{{ request($carry) }}">
        @endif
    @endforeach

    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search products" autocomplete="off" aria-label="Search products"
           class="min-w-[12rem] flex-1 rounded-lg border border-slate-300 px-4 py-2.5 text-sm">

    <select name="sort" onchange="this.form.submit()"
            class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        @foreach ([
            '' => 'Featured',
            'name' => 'Name (A–Z)',
            'price_asc' => 'Price: low to high',
            'price_desc' => 'Price: high to low',
            'rating' => 'Best rated',
            'popular' => 'Best selling',
        ] as $value => $label)
            <option value="{{ $value }}" @selected(request('sort') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm hover:bg-slate-50">Search</button>
</form>
