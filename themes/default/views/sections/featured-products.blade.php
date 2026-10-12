{{-- A grid of in-stock products, featured ones first. --}}
@php
    $products = modules()->enabled('shop')
        ? \App\Models\Product::published()->inStock()
            ->orderByDesc('is_featured')->orderByDesc('created_at')
            ->limit(max(1, min(24, (int) ($settings['count'] ?? 8))))->get()
        : collect();

    $grid = match ((int) ($settings['columns'] ?? 4)) {
        2 => 'lg:grid-cols-2',
        3 => 'lg:grid-cols-3',
        default => 'lg:grid-cols-4',
    };
@endphp

@if ($products->isNotEmpty())
    <section class="mx-auto max-w-6xl px-4 py-16">
        <div class="mb-8 flex items-end justify-between gap-4">
            <div>
                @if (filled($settings['title'] ?? 'Featured products'))
                    <h2 class="text-2xl font-bold text-slate-900">{{ $settings['title'] ?? 'Featured products' }}</h2>
                @endif
                @if (filled($settings['subtitle'] ?? 'A hand-picked selection'))
                    <p class="mt-1 text-sm text-slate-500">{{ $settings['subtitle'] ?? 'A hand-picked selection' }}</p>
                @endif
            </div>
            @if ($settings['show_link'] ?? true)
                <a href="{{ route('shop.index') }}" class="shrink-0 text-sm font-medium text-brand hover:underline">View all &rarr;</a>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-5 {{ $grid }}">
            @foreach ($products as $product)
                @include('theme::partials.product-card', ['product' => $product])
            @endforeach
        </div>
    </section>
@elseif ($editing)
    <div class="cb-placeholder">Featured products &mdash; no products in stock to show yet.</div>
@endif
