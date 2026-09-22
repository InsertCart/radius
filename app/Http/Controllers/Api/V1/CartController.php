<?php

namespace App\Http\Controllers\Api\V1;

use App\Cms\Api\ApiManager;
use App\Cms\Api\Resource;
use App\Cms\Shop\CartService;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\ResolvesApiCart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The basket.
 *
 * Every answer is the whole cart, not just the line that changed: an app that
 * adds an item wants the new totals in the same round trip, and a cart that
 * is always sent in full cannot drift out of step with the server's.
 *
 * Prices are never taken from the request. The cart service reads them from
 * the product, which is what stops an app - or anything pretending to be one -
 * deciding what something costs.
 */
class CartController extends ApiController
{
    use ResolvesApiCart;

    public function __construct(
        private CartService $cart,
        private ApiManager $api,
    ) {}

    public function show(Request $request): JsonResponse
    {
        if ($refusal = $this->refuseGuest($request)) {
            return $refusal;
        }

        $token = $this->bindCart($request, $this->cart);

        return $this->cartResponse($token);
    }

    public function store(Request $request): JsonResponse
    {
        if ($refusal = $this->refuseGuest($request)) {
            return $refusal;
        }

        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $product = Product::published()->find($validated['product_id']);

        if (! $product) {
            return $this->fail('That product is not available.', 404, 'not_found');
        }

        $variant = null;

        if (filled($validated['variant_id'] ?? null)) {
            // Scoped to this product, so a crafted request cannot attach
            // another product's cheaper variant to it.
            $variant = ProductVariant::where('product_id', $product->id)
                ->where('is_active', true)
                ->find($validated['variant_id']);

            if (! $variant) {
                return $this->fail('That option is not available.', 404, 'variant_not_found');
            }
        }

        if ($product->type === 'variable' && ! $variant) {
            return $this->fail('Choose an option first.', 422, 'variant_required');
        }

        $token = $this->bindCart($request, $this->cart);

        try {
            $this->cart->add($product, $validated['quantity'] ?? 1, $variant);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'out_of_stock');
        }

        return $this->cartResponse($token, status: 201);
    }

    public function update(Request $request, int $item): JsonResponse
    {
        if ($refusal = $this->refuseGuest($request)) {
            return $refusal;
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $token = $this->bindCart($request, $this->cart);
        $line = $this->ownLine($item);

        if (! $line) {
            return $this->fail('That item is not in your cart.', 404, 'not_found');
        }

        try {
            $this->cart->updateQuantity($line, $validated['quantity']);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'out_of_stock');
        }

        return $this->cartResponse($token);
    }

    public function destroy(Request $request, int $item): JsonResponse
    {
        if ($refusal = $this->refuseGuest($request)) {
            return $refusal;
        }

        $token = $this->bindCart($request, $this->cart);
        $line = $this->ownLine($item);

        if (! $line) {
            return $this->fail('That item is not in your cart.', 404, 'not_found');
        }

        $this->cart->remove($line);

        return $this->cartResponse($token);
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        if ($refusal = $this->refuseGuest($request)) {
            return $refusal;
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:60'],
        ]);

        $token = $this->bindCart($request, $this->cart);
        $result = $this->cart->applyCoupon($validated['code']);

        if (! $result['applied']) {
            return $this->fail($result['message'], 422, 'coupon_rejected');
        }

        return $this->cartResponse($token, message: $result['message']);
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        if ($refusal = $this->refuseGuest($request)) {
            return $refusal;
        }

        $token = $this->bindCart($request, $this->cart);
        $this->cart->removeCoupon();

        return $this->cartResponse($token);
    }

    // Internals -------------------------------------------------------------

    /**
     * The whole cart, plus the token a guest should keep sending.
     *
     * The token is in the body rather than only in a header so an app that
     * stores the response wholesale still has it next time.
     */
    private function cartResponse(?string $token, int $status = 200, ?string $message = null): JsonResponse
    {
        $payload = Resource::cart(
            $this->cart->cart(),
            $this->cart->summary(),
            $this->cart->validationIssues(),
            $token,
        );

        $extra = $message ? ['message' => $message] : [];

        return $this->data($payload, $extra, $status);
    }

    /**
     * A line in *this* caller's cart, or null.
     *
     * Looked up through the cart rather than by id, so an id belonging to
     * somebody else's basket simply is not found.
     */
    private function ownLine(int $id): ?CartItem
    {
        return $this->cart->cart()->items->firstWhere('id', $id);
    }

    /** Guests are only allowed a cart while the owner permits it. */
    private function refuseGuest(Request $request): ?JsonResponse
    {
        if ($request->user() || $this->api->allowsGuestCart()) {
            return null;
        }

        return $this->fail('Sign in to use a cart on this site.', 401, 'sign_in_required');
    }
}
