<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $products = $this->filtered(Product::published(), $request)
            ->paginate((int) setting('posts_per_page', 12))
            ->withQueryString();

        seo()->forRoute('shop.index')->schema('CollectionPage')
            ->breadcrumbs(['Home' => url('/'), 'Shop' => route('shop.index')]);

        return view('theme::shop.index', [
            'products' => $products,
            'categories' => $this->sidebarCategories(),
            'filters' => $request->only(['q', 'sort', 'min', 'max', 'in_stock']),
        ]);
    }

    public function category(Request $request, string $slug): View
    {
        $category = Category::shop()->active()->where('slug', $slug)->firstOrFail();

        $products = $this->filtered($category->products()->published(), $request)
            ->paginate((int) setting('posts_per_page', 12))
            ->withQueryString();

        seo()->forModel($category)->breadcrumbs([
            'Home' => url('/'),
            'Shop' => route('shop.index'),
            $category->name => $category->url(),
        ]);

        return view('theme::shop.category', [
            'category' => $category,
            'products' => $products,
            'categories' => $this->sidebarCategories(),
            'filters' => $request->only(['q', 'sort', 'min', 'max', 'in_stock']),
        ]);
    }

    public function show(string $slug): View
    {
        $product = Product::published()
            ->with(['categories', 'variants', 'approvedReviews.user'])
            ->where('slug', $slug)
            ->firstOrFail();

        Product::whereKey($product->id)->increment('views');

        $category = $product->categories->first();

        seo()->forModel($product)
            ->schema($product->seoSchemaType() ?? 'Product', array_filter([
                'sku' => $product->sku,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => number_format($product->effectivePrice() / 100, 2, '.', ''),
                    'priceCurrency' => setting('shop_currency', 'USD'),
                    'availability' => $product->inStock()
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                    'url' => $product->url(),
                ],
                'aggregateRating' => $product->review_count > 0 ? [
                    '@type' => 'AggregateRating',
                    'ratingValue' => (string) $product->rating,
                    'reviewCount' => $product->review_count,
                ] : null,
            ]))
            ->breadcrumbs(array_filter([
                'Home' => url('/'),
                'Shop' => route('shop.index'),
                $category?->name => $category?->url(),
                $product->name => $product->url(),
            ]));

        return view('theme::shop.show', [
            'product' => $product,
            'related' => $this->relatedProducts($product),
        ]);
    }

    public function review(Request $request, string $slug): RedirectResponse
    {
        $product = Product::published()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:190'],
            'body' => ['nullable', 'string', 'max:2000'],
            'author_name' => ['required_without:user_id', 'nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();

        if ($user && $product->reviews()->where('user_id', $user->id)->exists()) {
            return back()->with('error', 'You have already reviewed this product.');
        }

        $product->reviews()->create([
            'user_id' => $user?->id,
            'author_name' => $user?->name ?? $validated['author_name'],
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'] ?? null,
            'status' => 'pending',
            'verified_purchase' => $user ? $this->hasPurchased($user->id, $product->id) : false,
        ]);

        return back()->with('status', 'Thanks for the review. It will appear once approved.');
    }

    /** Shared filtering and sorting for the shop index and category pages. */
    private function filtered($query, Request $request)
    {
        // Relevance leads only when the visitor has not picked a sort order.
        $query = search()->constrain($query, 'product', $request->string('q')->toString(), rank: ! $request->filled('sort'));

        return $query
            ->when($request->filled('min'), fn ($q) => $q->where('price', '>=', to_minor_units($request->input('min'))))
            ->when($request->filled('max'), fn ($q) => $q->where('price', '<=', to_minor_units($request->input('max'))))
            ->when($request->boolean('in_stock'), fn ($q) => $q->inStock())
            ->when(true, fn ($q) => match ($request->string('sort')->toString()) {
                'price_asc' => $q->orderBy('price'),
                'price_desc' => $q->orderByDesc('price'),
                'name' => $q->orderBy('name'),
                'rating' => $q->orderByDesc('rating'),
                'popular' => $q->orderByDesc('sold_count'),
                default => $q->orderByDesc('is_featured')->orderByDesc('created_at'),
            });
    }

    private function sidebarCategories()
    {
        return Category::shop()
            ->active()
            ->withCount(['products' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->get();
    }

    private function relatedProducts(Product $product)
    {
        $categoryIds = $product->categories->pluck('id');

        return Product::published()
            ->where('id', '!=', $product->id)
            ->when($categoryIds->isNotEmpty(), fn ($q) => $q->whereHas('categories',
                fn ($c) => $c->whereIn('categories.id', $categoryIds)))
            ->inStock()
            ->limit(4)
            ->get();
    }

    private function hasPurchased(int $userId, int $productId): bool
    {
        return Order::where('user_id', $userId)
            ->where('payment_status', 'paid')
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))
            ->exists();
    }
}
