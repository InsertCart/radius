<?php

namespace App\Http\Controllers\Front;

use App\Cms\Shop\CartService;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index(): View
    {
        seo()->title('Your cart')->noindex();

        return view('theme::shop.cart', [
            'cart' => $this->cart->cart(),
            'summary' => $this->cart->summary(),
            'issues' => $this->cart->validationIssues(),
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $product = Product::published()->findOrFail($validated['product_id']);
        $variant = null;

        if (filled($validated['variant_id'] ?? null)) {
            // Scoped to this product, so a crafted form cannot attach another
            // product's cheaper variant to it.
            $variant = ProductVariant::where('product_id', $product->id)
                ->where('is_active', true)
                ->findOrFail($validated['variant_id']);
        }

        if ($product->type === 'variable' && ! $variant) {
            return back()->with('error', 'Please choose an option first.');
        }

        try {
            $this->cart->add($product, $validated['quantity'] ?? 1, $variant);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "\"{$product->name}\" added to your cart.");
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($item);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        try {
            $this->cart->updateQuantity($item, $validated['quantity']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Cart updated.');
    }

    public function remove(CartItem $item): RedirectResponse
    {
        $this->authorizeItem($item);

        $this->cart->remove($item);

        return back()->with('status', 'Item removed.');
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:60'],
        ]);

        $result = $this->cart->applyCoupon($validated['code']);

        return back()->with($result['applied'] ? 'status' : 'error', $result['message']);
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->cart->removeCoupon();

        return back()->with('status', 'Coupon removed.');
    }

    /** Line items are addressed by id, so confirm this one is really ours. */
    private function authorizeItem(CartItem $item): void
    {
        abort_unless($item->cart_id === $this->cart->cart()->id, 403);
    }
}
