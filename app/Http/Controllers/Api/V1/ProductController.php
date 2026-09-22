<?php

namespace App\Http\Controllers\Api\V1;

use App\Cms\Api\Resource;
use App\Http\Controllers\Api\ApiController;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The catalogue, filtered and sorted the same way the shop pages filter and
 * sort it, so an app and the website agree about what "cheapest first" means.
 */
class ProductController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::published();

        if ($request->filled('category')) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $request->string('category')->toString()));
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        $products = $this->filtered($query, $request)->paginate($this->perPage($request));

        return $this->page(Resource::paginated($products, fn (Product $product) => Resource::product($product)));
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $product = Product::published()
            ->with(['categories', 'variants', 'gallery'])
            ->where('slug', $slug)
            ->first();

        if (! $product) {
            return $this->fail('No such product.', 404, 'not_found');
        }

        Product::whereKey($product->id)->increment('views');

        return $this->data(Resource::product($product, full: true) + [
            'related' => $this->related($product)->map(fn (Product $item) => Resource::product($item))->all(),
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::shop()
            ->active()
            ->withCount(['products' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $category) => Resource::category($category) + [
                'product_count' => (int) $category->products_count,
            ]);

        return $this->data($categories->all());
    }

    // Reviews ---------------------------------------------------------------

    public function reviews(Request $request, string $slug): JsonResponse
    {
        $product = Product::published()->where('slug', $slug)->first();

        if (! $product) {
            return $this->fail('No such product.', 404, 'not_found');
        }

        $reviews = $product->approvedReviews()
            ->paginate($this->perPage($request));

        return $this->page(Resource::paginated($reviews, fn (ProductReview $review) => Resource::review($review)) + [
            'summary' => [
                'rating' => (float) $product->rating,
                'count' => (int) $product->review_count,
            ],
        ]);
    }

    public function storeReview(Request $request, string $slug): JsonResponse
    {
        $product = Product::published()->where('slug', $slug)->first();

        if (! $product) {
            return $this->fail('No such product.', 404, 'not_found');
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:190'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        if ($product->reviews()->where('user_id', $user->id)->exists()) {
            return $this->fail('You have already reviewed this product.', 409, 'already_reviewed');
        }

        $product->reviews()->create([
            'user_id' => $user->id,
            'author_name' => $user->name,
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'] ?? null,
            // Moderated, as on the website.
            'status' => 'pending',
            'verified_purchase' => $this->hasPurchased($user->id, $product->id),
        ]);

        return $this->message('Thanks for the review. It will appear once approved.', status: 201);
    }

    // Internals -------------------------------------------------------------

    /** The shop's own filtering and sorting, in one place. */
    private function filtered($query, Request $request)
    {
        $query = search()->constrain(
            $query,
            'product',
            $request->string('q')->toString(),
            rank: ! $request->filled('sort')
        );

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

    private function related(Product $product)
    {
        $categoryIds = $product->categories->pluck('id');

        return Product::published()
            ->where('id', '!=', $product->id)
            ->when($categoryIds->isNotEmpty(), fn ($q) => $q->whereHas(
                'categories',
                fn ($c) => $c->whereIn('categories.id', $categoryIds)
            ))
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
