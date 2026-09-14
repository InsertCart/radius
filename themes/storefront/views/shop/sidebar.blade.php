<aside class="sf-side">
    @if ($categories->isNotEmpty())
        <div>
            <h2 class="sf-side__title">Departments</h2>
            <ul class="sf-side__list">
                @foreach ($categories as $sidebarCategory)
                    <li>
                        <a href="{{ $sidebarCategory->url() }}"
                           class="{{ request()->is('shop/category/'.$sidebarCategory->slug) ? 'is-active' : '' }}">
                            <span>{{ $sidebarCategory->name }}</span>
                            <span>{{ $sidebarCategory->products_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET">
        {{-- Carry the current search and sort through the price filter. --}}
        @foreach (['q', 'sort'] as $carry)
            @if (request()->filled($carry))
                <input type="hidden" name="{{ $carry }}" value="{{ request($carry) }}">
            @endif
        @endforeach

        <h2 class="sf-side__title">Price</h2>
        <div class="sf-row sf-row--split">
            <input type="number" name="min" value="{{ request('min') }}" placeholder="Min" min="0" class="sf-input">
            <input type="number" name="max" value="{{ request('max') }}" placeholder="Max" min="0" class="sf-input">
        </div>

        <label class="sf-check sf-mt">
            <input type="checkbox" name="in_stock" value="1" @checked(request()->boolean('in_stock'))>
            <span>In stock only</span>
        </label>

        <button class="sf-btn sf-btn--ghost sf-btn--block sf-mt">Apply filters</button>

        @if (request()->hasAny(['q', 'min', 'max', 'in_stock', 'sort']))
            <p class="sf-center sf-mt">
                <a href="{{ url()->current() }}" class="sf-small sf-muted">Clear everything</a>
            </p>
        @endif
    </form>
</aside>
