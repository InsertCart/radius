<aside class="zn-filter-box">
    @if ($categories->isNotEmpty())
        <div style="margin-bottom: 28px;">
            <h3 class="zn-filter-title">Collections</h3>
            <ul class="zn-filter-list">
                @foreach ($categories as $sidebarCategory)
                    <li>
                        <a href="{{ $sidebarCategory->url() }}"
                           class="{{ request()->is('shop/category/'.$sidebarCategory->slug) ? 'active' : '' }}">
                            <span>{{ $sidebarCategory->name }}</span>
                            <span style="font-size: 11px; opacity: 0.7;">{{ $sidebarCategory->products_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET">
        @foreach (['q', 'sort'] as $carry)
            @if (request()->filled($carry))
                <input type="hidden" name="{{ $carry }}" value="{{ request($carry) }}">
            @endif
        @endforeach

        <h3 class="zn-filter-title">Price Range</h3>
        <div style="display: flex; gap: 8px; margin-bottom: 16px;">
            <input type="number" name="min" value="{{ request('min') }}" placeholder="Min" min="0"
                   style="width: 100%; height: 38px; padding: 0 10px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 13px;">
            <input type="number" name="max" value="{{ request('max') }}" placeholder="Max" min="0"
                   style="width: 100%; height: 38px; padding: 0 10px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 13px;">
        </div>

        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--zn-text-secondary); margin-bottom: 18px; cursor: pointer;">
            <input type="checkbox" name="in_stock" value="1" @checked(request()->boolean('in_stock'))>
            <span>In stock only</span>
        </label>

        <button type="submit" class="zn-btn zn-btn--primary zn-btn--sm zn-btn--block">Filter Collection</button>

        @if (request()->hasAny(['q', 'min', 'max', 'in_stock', 'sort']))
            <div style="text-align: center; margin-top: 14px;">
                <a href="{{ url()->current() }}" style="font-size: 12px; color: var(--zn-muted); text-decoration: underline;">Reset all filters</a>
            </div>
        @endif
    </form>
</aside>
