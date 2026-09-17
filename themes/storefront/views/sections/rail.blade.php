{{-- One scrolling row of products, chosen by the "source" setting. --}}
@php
    $products = collect();
    $moreUrl = modules()->enabled('shop') ? route('shop.index') : null;
    $count = max(1, min(24, (int) ($settings['count'] ?? 8)));
    $source = $settings['source'] ?? 'featured';
    $category = null;

    if (modules()->enabled('shop')) {
        $query = \App\Models\Product::published()->inStock();

        if ($source === 'category' && filled($settings['category'] ?? null)) {
            $category = \App\Models\Category::shop()->active()->find($settings['category']);
            $query = $category
                ? $category->products()->published()->inStock()
                : $query->whereRaw('1 = 0');
            $moreUrl = $category?->url() ?? $moreUrl;
        }

        $products = (match ($source) {
            'latest' => $query->latest('products.created_at'),
            'sale' => $query->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'price')
                ->orderByDesc('products.created_at'),
            'popular' => $query->orderByDesc('sold_count'),
            default => $query->orderByDesc('is_featured')->orderByDesc('products.created_at'),
        })->limit($count)->get();
    }
@endphp

@if ($products->isEmpty() && $editing)
    <div class="cb-placeholder">{{ ($settings['title'] ?? '') ?: 'Product rail' }} — no products match yet.</div>
@else
    @include('theme::partials.rail', [
        'title' => ($settings['title'] ?? '') !== '' ? $settings['title'] : ($category?->name ?? 'Featured this week'),
        'subtitle' => $settings['subtitle'] ?? null,
        'products' => $products,
        'moreUrl' => ! empty($settings['show_all']) ? $moreUrl : null,
        'soft' => ! empty($settings['soft']),
    ])
@endif
