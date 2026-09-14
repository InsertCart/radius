<?php

namespace App\Cms\Shop;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Owns the visitor's cart and computes its totals.
 *
 * Carts are keyed by session for guests and by user id once signed in; the
 * guest cart is merged into the account cart at login so nothing is lost.
 * Every amount is an integer in minor units.
 */
class CartService
{
    private ?Cart $cart = null;

    /** The current cart, created on first use. */
    public function cart(): Cart
    {
        if ($this->cart) {
            return $this->cart;
        }

        $cart = auth()->check()
            ? Cart::firstOrCreate(['user_id' => auth()->id()])
            : Cart::firstOrCreate(['session_id' => session()->getId(), 'user_id' => null]);

        return $this->cart = $cart->load('items.product', 'items.variant');
    }

    public function refresh(): Cart
    {
        $this->cart = null;

        return $this->cart();
    }

    /**
     * Merge a guest cart into the signed-in user's cart. Called from the login
     * listener, so a visitor who fills a cart then logs in keeps it.
     */
    public function mergeGuestCart(string $sessionId, int $userId): void
    {
        $guestCart = Cart::where('session_id', $sessionId)->whereNull('user_id')->first();

        if (! $guestCart) {
            return;
        }

        $userCart = Cart::firstOrCreate(['user_id' => $userId]);

        foreach ($guestCart->items as $item) {
            $existing = $userCart->items()
                ->where('product_id', $item->product_id)
                ->where('variant_id', $item->variant_id)
                ->first();

            if ($existing) {
                $existing->increment('quantity', $item->quantity);
            } else {
                $item->update(['cart_id' => $userCart->id]);
            }
        }

        $userCart->coupon_code ??= $guestCart->coupon_code;
        $userCart->save();

        $guestCart->delete();

        $this->cart = null;
    }

    // Mutation ------------------------------------------------------------

    /**
     * @throws \RuntimeException when the product is unavailable or out of stock.
     */
    public function add(Product $product, int $quantity = 1, ?ProductVariant $variant = null): CartItem
    {
        if ($product->status !== 'published') {
            throw new \RuntimeException('This product is not available.');
        }

        $quantity = max(1, $quantity);
        $cart = $this->cart();

        $item = $cart->items()
            ->where('product_id', $product->id)
            ->where('variant_id', $variant?->id)
            ->first();

        // Stock is checked against the resulting quantity, not the increment,
        // so adding one at a time cannot walk past the available count.
        $newQuantity = ($item?->quantity ?? 0) + $quantity;

        $this->assertInStock($product, $variant, $newQuantity);

        if ($item) {
            $item->update(['quantity' => $newQuantity]);
        } else {
            $item = $cart->items()->create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity' => $newQuantity,
                'unit_price' => $variant?->effectivePrice() ?? $product->effectivePrice(),
                'options' => $variant?->options,
            ]);
        }

        $this->refresh();

        return $item;
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity < 1) {
            $this->remove($item);

            return;
        }

        $this->assertInStock($item->product, $item->variant, $quantity);

        $item->update(['quantity' => $quantity]);
        $this->refresh();
    }

    public function remove(CartItem $item): void
    {
        $item->delete();
        $this->refresh();
    }

    public function clear(): void
    {
        $cart = $this->cart();
        $cart->items()->delete();
        $cart->update(['coupon_code' => null]);
        $this->refresh();
    }

    private function assertInStock(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        if (! setting('shop_stock_management', true)) {
            return;
        }

        $available = $variant ? $variant->inStock($quantity) : $product->inStock($quantity);

        if (! $available) {
            $remaining = $variant?->stock ?? $product->stock;

            throw new \RuntimeException(
                $remaining > 0
                    ? "Only {$remaining} of \"{$product->name}\" are left in stock."
                    : "\"{$product->name}\" is out of stock."
            );
        }
    }

    // Coupons -------------------------------------------------------------

    /** @return array{applied: bool, message: string} */
    public function applyCoupon(string $code): array
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        if (! $coupon) {
            return ['applied' => false, 'message' => 'That coupon code was not recognised.'];
        }

        if ($error = $coupon->validationError($this->subtotal(), auth()->user())) {
            return ['applied' => false, 'message' => $error];
        }

        $this->cart()->update(['coupon_code' => $coupon->code]);
        $this->refresh();

        return ['applied' => true, 'message' => "Coupon {$coupon->code} applied."];
    }

    public function removeCoupon(): void
    {
        $this->cart()->update(['coupon_code' => null]);
        $this->refresh();
    }

    public function coupon(): ?Coupon
    {
        $code = $this->cart()->coupon_code;

        if (blank($code)) {
            return null;
        }

        $coupon = Coupon::where('code', $code)->first();

        // A coupon can expire between being applied and checkout finishing.
        if (! $coupon || ! $coupon->isUsableBy($this->subtotal(), auth()->user())) {
            $this->removeCoupon();

            return null;
        }

        return $coupon;
    }

    // Totals --------------------------------------------------------------

    public function subtotal(): int
    {
        return (int) $this->cart()->items->sum(fn (CartItem $item) => $item->lineTotal());
    }

    public function discount(): int
    {
        return $this->coupon()?->discountFor($this->subtotal()) ?? 0;
    }

    public function shipping(): int
    {
        // Digital-only carts never attract a shipping charge.
        if (! $this->requiresShipping()) {
            return 0;
        }

        if ($this->coupon()?->givesFreeShipping()) {
            return 0;
        }

        $threshold = to_minor_units(setting('shop_free_shipping_over', 0));

        if ($threshold > 0 && ($this->subtotal() - $this->discount()) >= $threshold) {
            return 0;
        }

        return to_minor_units(setting('shop_shipping_flat', 0));
    }

    public function tax(): int
    {
        if (! setting('shop_tax_enabled', false)) {
            return 0;
        }

        $rate = (float) setting('shop_tax_rate', 0);

        if ($rate <= 0) {
            return 0;
        }

        $taxable = $this->subtotal() - $this->discount();

        // When prices already include tax, the tax is the portion inside the
        // total rather than an amount added on top.
        if (setting('shop_tax_inclusive', false)) {
            return (int) round($taxable - ($taxable / (1 + $rate / 100)));
        }

        return (int) round($taxable * ($rate / 100));
    }

    public function total(): int
    {
        $total = $this->subtotal() - $this->discount() + $this->shipping();

        if (! setting('shop_tax_inclusive', false)) {
            $total += $this->tax();
        }

        return max(0, $total);
    }

    public function requiresShipping(): bool
    {
        return $this->cart()->items->contains(fn (CartItem $item) => (bool) $item->product?->requires_shipping);
    }

    public function itemCount(): int
    {
        return $this->cart()->itemCount();
    }

    public function isEmpty(): bool
    {
        return $this->cart()->isEmpty();
    }

    /** Every total at once, for the cart and checkout views. */
    public function summary(): array
    {
        return [
            'subtotal' => $this->subtotal(),
            'discount' => $this->discount(),
            'shipping' => $this->shipping(),
            'tax' => $this->tax(),
            'total' => $this->total(),
            'item_count' => $this->itemCount(),
            'coupon' => $this->coupon(),
            'requires_shipping' => $this->requiresShipping(),
        ];
    }

    /**
     * Problems that must be resolved before checkout: items that went out of
     * stock or were unpublished while sitting in the cart.
     *
     * @return string[]
     */
    public function validationIssues(): array
    {
        $issues = [];

        foreach ($this->cart()->items as $item) {
            if (! $item->product) {
                $issues[] = 'An item in your cart is no longer available.';

                continue;
            }

            if (! $item->isAvailable()) {
                $issues[] = "\"{$item->name()}\" is no longer available in the quantity you selected.";
            }
        }

        return $issues;
    }
}
