<?php

namespace App\Cms\Api;

use App\Models\Address;
use App\Models\ApiToken;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Every shape the API hands out.
 *
 * Deliberately written by hand, field by field, rather than by serialising
 * models. A model grows columns - an internal note, a cost price, an IP
 * address, somebody's password hash - and anything that serialises the whole
 * model publishes them the day they are added. Listing the fields means a new
 * column is invisible to the API until somebody decides otherwise.
 *
 * Money is an integer in the currency's minor unit, the way it is stored,
 * with a formatted string beside it so an app does not have to reimplement
 * the site's currency settings.
 */
class Resource
{
    // Content --------------------------------------------------------------

    public static function post(Post $post, bool $full = false): array
    {
        $data = [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->summary(),
            'image' => $post->imageUrl(),
            'published_at' => $post->published_at?->toIso8601String(),
            'is_featured' => (bool) $post->is_featured,
            'views' => (int) $post->views,
            'url' => $post->url(),
            'author' => $post->relationLoaded('author') && $post->author
                ? ['name' => $post->author->name, 'avatar' => $post->author->avatarUrl()]
                : null,
            'category' => $post->relationLoaded('category') && $post->category
                ? self::category($post->category)
                : null,
        ];

        if (! $full) {
            return $data;
        }

        return $data + [
            // Embeds are resolved here too, so an app showing the body in a
            // web view gets the same players the website does.
            'content' => rich_content($post->content),
            'allow_comments' => (bool) $post->allow_comments,
            'comment_count' => $post->approvedComments()->count(),
            'tags' => $post->relationLoaded('tags')
                ? $post->tags->map(fn (Tag $tag) => self::tag($tag))->all()
                : [],
            'seo' => self::seo($post),
        ];
    }

    public static function page(Page $page, bool $full = false): array
    {
        $data = [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'image' => $page->imageUrl(),
            'parent_id' => $page->parent_id,
            'url' => $page->url(),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];

        if (! $full) {
            return $data;
        }

        return $data + [
            'content' => rich_content($page->content),
            'seo' => self::seo($page),
        ];
    }

    public static function tag(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
        ];
    }

    public static function category(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'type' => $category->type,
            'description' => $category->description,
            'image' => media_url($category->image),
            'icon' => $category->icon,
            'parent_id' => $category->parent_id,
            'url' => $category->url(),
        ];
    }

    public static function comment(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'author' => $comment->displayName(),
            'body' => $comment->body,
            'created_at' => $comment->created_at?->toIso8601String(),
        ];
    }

    // Shop -----------------------------------------------------------------

    public static function product(Product $product, bool $full = false): array
    {
        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'short_description' => $product->summary(),
            'image' => $product->imageUrl(),
            'type' => $product->type,
            'price' => self::money($product->price),
            'sale_price' => $product->isOnSale() ? self::money((int) $product->sale_price) : null,
            'effective_price' => self::money($product->effectivePrice()),
            'discount_percent' => $product->discountPercent(),
            'in_stock' => $product->inStock(),
            'stock' => setting('shop_stock_management', true) && $product->manage_stock
                ? (int) $product->stock
                : null,
            'is_digital' => $product->isDigital(),
            'requires_shipping' => (bool) $product->requires_shipping,
            'is_featured' => (bool) $product->is_featured,
            'rating' => (float) $product->rating,
            'review_count' => (int) $product->review_count,
            'url' => $product->url(),
        ];

        if (! $full) {
            return $data;
        }

        return $data + [
            'description' => rich_content($product->description),
            'gallery' => $product->relationLoaded('gallery')
                ? $product->gallery->map(fn ($medium) => [
                    'url' => $medium->url,
                    'thumbnail' => $medium->conversionUrl('thumb'),
                    'alt' => $medium->alt ?: $product->name,
                ])->all()
                : [],
            'variants' => $product->relationLoaded('variants')
                ? $product->variants->where('is_active', true)->values()
                    ->map(fn (ProductVariant $variant) => self::variant($variant))->all()
                : [],
            'categories' => $product->relationLoaded('categories')
                ? $product->categories->map(fn (Category $category) => self::category($category))->all()
                : [],
            'seo' => self::seo($product),
        ];
    }

    public static function variant(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'options' => $variant->options ?? [],
            'label' => $variant->optionsLabel(),
            'price' => self::money($variant->effectivePrice()),
            'in_stock' => $variant->inStock(),
            'image' => $variant->imageUrl(),
        ];
    }

    public static function review(ProductReview $review): array
    {
        return [
            'id' => $review->id,
            'author' => $review->displayName(),
            'rating' => (int) $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'verified_purchase' => (bool) $review->verified_purchase,
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }

    /** The cart, its lines and every total, in one shape the app can render directly. */
    public static function cart(Cart $cart, array $summary, array $issues = [], ?string $token = null): array
    {
        return [
            'token' => $token,
            'item_count' => (int) $summary['item_count'],
            'items' => $cart->items->map(fn (CartItem $item) => self::cartItem($item))->values()->all(),
            'coupon' => $summary['coupon'] ? [
                'code' => $summary['coupon']->code,
                'description' => $summary['coupon']->description ?? null,
            ] : null,
            'requires_shipping' => (bool) $summary['requires_shipping'],
            'totals' => [
                'subtotal' => self::money($summary['subtotal']),
                'discount' => self::money($summary['discount']),
                'shipping' => self::money($summary['shipping']),
                'tax' => self::money($summary['tax']),
                'total' => self::money($summary['total']),
            ],
            'currency' => setting('shop_currency', 'USD'),
            'issues' => array_values($issues),
        ];
    }

    public static function cartItem(CartItem $item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'name' => $item->name(),
            'slug' => $item->product?->slug,
            'image' => $item->imageUrl(),
            'options' => $item->options ?? [],
            'quantity' => (int) $item->quantity,
            'unit_price' => self::money((int) $item->unit_price),
            'line_total' => self::money($item->lineTotal()),
            'available' => $item->isAvailable(),
            'price_changed' => $item->priceHasChanged(),
        ];
    }

    public static function order(Order $order, bool $full = false): array
    {
        $data = [
            'number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_gateway' => $order->payment_gateway,
            'currency' => $order->currency,
            'item_count' => $order->itemCount(),
            'totals' => [
                'subtotal' => self::money((int) $order->subtotal),
                'discount' => self::money((int) $order->discount_total),
                'shipping' => self::money((int) $order->shipping_total),
                'tax' => self::money((int) $order->tax_total),
                'total' => self::money((int) $order->grand_total),
            ],
            'is_paid' => $order->isPaid(),
            'placed_at' => $order->created_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
        ];

        if (! $full) {
            return $data;
        }

        return $data + [
            'email' => $order->email,
            'phone' => $order->phone,
            'coupon_code' => $order->coupon_code,
            'customer_note' => $order->customer_note,
            'tracking_number' => $order->tracking_number,
            'shipped_at' => $order->shipped_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'billing_address' => $order->billing_address,
            'shipping_address' => $order->shipping_address,
            'items' => $order->items->map(fn (OrderItem $item) => self::orderItem($item))->all(),
        ];
        // Deliberately absent: admin_note and ip_address. The first is written
        // for staff, the second is not the customer's business to be told back.
    }

    public static function orderItem(OrderItem $item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'name' => $item->name,
            'sku' => $item->sku,
            'slug' => $item->product?->slug,
            'image' => $item->product?->imageUrl(),
            'options' => $item->options ?? [],
            'quantity' => (int) $item->quantity,
            'unit_price' => self::money((int) $item->unit_price),
            'line_total' => self::money((int) $item->line_total),
            'is_downloadable' => $item->isDownloadable(),
            'downloads_remaining' => $item->downloadsRemaining(),
        ];
    }

    public static function address(Address $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'name' => $address->name,
            'phone' => $address->phone,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'state' => $address->state,
            'postcode' => $address->postcode,
            'country' => $address->country,
            'country_name' => $address->countryName(),
            'is_default_billing' => (bool) $address->is_default_billing,
            'is_default_shipping' => (bool) $address->is_default_shipping,
        ];
    }

    // People ---------------------------------------------------------------

    /**
     * The signed-in customer, as told to themselves.
     *
     * The role is not published. An app has no decision to make with it, and
     * there is nothing on this API that an admin could do with it anyway.
     */
    public static function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatarUrl(),
            'initials' => $user->initials(),
            'email_verified' => $user->hasVerifiedEmail(),
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /** One signed-in device, for the "where am I signed in" screen. */
    public static function device(ApiToken $token, ?int $currentId = null): array
    {
        return [
            'id' => $token->id,
            'name' => $token->device_name ?: 'Unnamed device',
            'app' => $token->relationLoaded('client') ? $token->client?->name : null,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'current' => $currentId !== null && $token->id === $currentId,
        ];
    }

    // Helpers --------------------------------------------------------------

    /** An amount, both ways: the integer to do sums with and the string to show. */
    public static function money(?int $minorUnits): array
    {
        return [
            'amount' => (int) $minorUnits,
            'formatted' => money((int) $minorUnits),
        ];
    }

    /** The meta an app needs if it renders a share sheet or a web view. */
    private static function seo(Post|Page|Product $model): array
    {
        return [
            'title' => $model->meta_title ?: ($model->title ?? $model->name ?? null),
            'description' => $model->meta_description
                ?: Str::limit(strip_tags((string) ($model->excerpt ?? $model->short_description ?? '')), 160),
            'image' => media_url($model->og_image) ?: ($model->imageUrl() ?? null),
            'canonical' => $model->canonical_url ?: $model->url(),
        ];
    }

    /**
     * A paginated list, with the page links an app needs to keep scrolling.
     *
     * @param  callable(mixed): array  $transform
     */
    public static function paginated(LengthAwarePaginator $paginator, callable $transform): array
    {
        return [
            'data' => collect($paginator->items())->map($transform)->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }
}
