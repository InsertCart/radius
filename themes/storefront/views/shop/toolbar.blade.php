<form method="GET" class="sf-toolbar">
    @foreach (['min', 'max', 'in_stock'] as $carry)
        @if (request()->filled($carry))
            <input type="hidden" name="{{ $carry }}" value="{{ request($carry) }}">
        @endif
    @endforeach

    <label for="sf-listing-q" class="sf-sr">Search products</label>
    <input type="search" name="q" id="sf-listing-q" value="{{ request('q') }}"
           placeholder="Search within these results" class="sf-input">

    <label for="sf-listing-sort" class="sf-sr">Sort by</label>
    <select name="sort" id="sf-listing-sort" class="sf-select sf-select--auto" onchange="this.form.submit()">
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

    <button class="sf-btn sf-btn--ghost">Search</button>
</form>
