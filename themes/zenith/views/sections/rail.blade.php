@php
    $products = collect();
    $moreUrl = modules()->enabled('shop') ? route('shop.index') : null;
    $count = max(2, min(16, (int) ($settings['count'] ?? 8)));
    $source = $settings['source'] ?? 'featured';
    $category = null;

    if (modules()->enabled('shop')) {
        $query = \App\Models\Product::published()->with(['categories']);

        if ($source === 'category' && filled($settings['category'] ?? null)) {
            $category = \App\Models\Category::shop()->active()->find($settings['category']);
            $query = $category
                ? $category->products()->published()->with(['categories'])
                : $query->whereRaw('1 = 0');
            $moreUrl = $category?->url() ?? $moreUrl;
        }

        $products = (match ($source) {
            'latest' => $query->latest('products.created_at'),
            'sale' => $query->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'price')->orderByDesc('products.created_at'),
            default => $query->orderByDesc('is_featured')->orderByDesc('products.created_at'),
        })->limit($count)->get();
    }

    $kicker = trim((string) ($settings['kicker'] ?? 'CURATED HIGHLIGHTS'));
    $title = trim((string) ($settings['title'] ?? '')) ?: ($category?->name ?? 'Featured Editions');
    $subtitle = trim((string) ($settings['subtitle'] ?? 'Hand-picked pieces crafted with precision and obsessive attention to detail.'));
@endphp

@if ($products->isNotEmpty() || $editing)
    <section class="zn-rail-section">
        <div class="zn-wrap">
            <div class="zn-section-head">
                <div>
                    @if ($kicker !== '')
                        <div class="zn-section-head__kicker">{{ $kicker }}</div>
                    @endif
                    <h2 class="zn-section-head__title">{{ $title }}</h2>
                    @if ($subtitle !== '')
                        <p class="zn-section-head__subtitle">{{ $subtitle }}</p>
                    @endif
                </div>

                @if ($moreUrl)
                    <a href="{{ $moreUrl }}" class="zn-btn zn-btn--ghost zn-btn--sm" style="flex-shrink: 0;">
                        View All
                        @include('theme::partials.icon', ['name' => 'arrow-right'])
                    </a>
                @endif
            </div>

            @if ($products->isEmpty())
                <div style="text-align: center; padding: 40px; border: 1px dashed var(--zn-border); border-radius: var(--zn-radius-lg); color: var(--zn-muted);">
                    No products found for this section yet.
                </div>
            @else
                <div class="zn-grid--products">
                    @foreach ($products as $product)
                        @include('theme::partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif
