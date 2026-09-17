{{-- A rail for each top-level shop category, one after another. Queried one
     category at a time on purpose: a "limit" inside an eager-load constraint
     caps the combined result, handing the first category every row. --}}
@php
    $rails = collect();

    if (modules()->enabled('shop')) {
        $rails = \App\Models\Category::shop()
            ->active()
            ->roots()
            ->has('products')
            ->orderBy('sort_order')
            ->limit(max(1, min(10, (int) ($settings['categories'] ?? 3))))
            ->get()
            ->map(fn ($category) => [
                'category' => $category,
                'products' => $category->products()
                    ->published()
                    ->inStock()
                    ->orderByDesc('is_featured')
                    ->orderByDesc('products.created_at')
                    ->limit(max(1, min(24, (int) ($settings['count'] ?? 10))))
                    ->get(),
            ])
            ->filter(fn ($rail) => $rail['products']->isNotEmpty())
            ->values();
    }
@endphp

@if ($rails->isEmpty() && $editing)
    <div class="cb-placeholder">Category rails — no categories with products yet.</div>
@endif

@foreach ($rails as $rail)
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
