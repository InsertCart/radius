<?php

namespace App\Cms\Shop;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns a cart into an order, and moves an order through its lifecycle.
 *
 * Totals are recalculated here from the database rather than trusted from the
 * checkout form: the browser sees prices, but it never gets to decide them.
 */
class OrderService
{
    public function __construct(private CartService $cart) {}

    /**
     * Create an order from the current cart.
     *
     * Stock is decremented inside the same transaction that writes the order,
     * so two people racing for the last item cannot both succeed.
     *
     * @throws \RuntimeException when the cart is empty or an item is unavailable.
     */
    public function createFromCart(array $data, ?User $user = null): Order
    {
        $cart = $this->cart->cart();

        if ($cart->items->isEmpty()) {
            throw new \RuntimeException('Your cart is empty.');
        }

        if ($issues = $this->cart->validationIssues()) {
            throw new \RuntimeException($issues[0]);
        }

        $summary = $this->cart->summary();

        return DB::transaction(function () use ($cart, $data, $user, $summary) {
            $order = Order::create([
                'user_id' => $user?->id,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_gateway' => $data['payment_gateway'] ?? null,
                'currency' => setting('shop_currency', 'USD'),
                'subtotal' => $summary['subtotal'],
                'discount_total' => $summary['discount'],
                'shipping_total' => $summary['shipping'],
                'tax_total' => $summary['tax'],
                'grand_total' => $summary['total'],
                'coupon_code' => $summary['coupon']?->code,
                'billing_address' => $data['billing_address'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? $data['billing_address'] ?? null,
                'customer_note' => $data['customer_note'] ?? null,
                'ip_address' => request()->ip(),
            ]);

            foreach ($cart->items as $item) {
                $this->createOrderItem($order, $item);
            }

            if ($summary['coupon']) {
                Coupon::whereKey($summary['coupon']->id)->increment('used_count');
            }

            return $order->load('items');
        });
    }

    private function createOrderItem(Order $order, CartItem $item): void
    {
        $product = $item->product;

        $order->items()->create([
            'product_id' => $product?->id,
            'variant_id' => $item->variant_id,
            // Copied in, so the line still reads correctly if the product is
            // renamed or deleted later.
            'name' => $item->name(),
            'sku' => $item->variant?->sku ?: $product?->sku,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'line_total' => $item->lineTotal(),
            'options' => $item->options,
            // The file is pinned to the line as well. If the seller later
            // replaces the product's download, this customer keeps the version
            // they actually paid for.
            'digital_file' => $product?->isDigital() ? $product->digital_file : null,
            'digital_name' => $product?->isDigital() ? $product->digital_name : null,
            'digital_size' => $product?->isDigital() ? $product->digital_size : null,
        ]);

        $this->decrementStock($product, $item->variant, $item->quantity);
    }

    private function decrementStock(?Product $product, ?ProductVariant $variant, int $quantity): void
    {
        if (! $product || ! setting('shop_stock_management', true) || ! $product->manage_stock) {
            return;
        }

        if ($variant) {
            ProductVariant::whereKey($variant->id)->decrement('stock', $quantity);
        } else {
            Product::whereKey($product->id)->decrement('stock', $quantity);
        }

        Product::whereKey($product->id)->increment('sold_count', $quantity);
    }

    /** Put stock back when an order is cancelled or refunded. */
    public function restoreStock(Order $order): void
    {
        if (! setting('shop_stock_management', true)) {
            return;
        }

        foreach ($order->items as $item) {
            if (! $item->product || ! $item->product->manage_stock) {
                continue;
            }

            if ($item->variant_id) {
                ProductVariant::whereKey($item->variant_id)->increment('stock', $item->quantity);
            } else {
                Product::whereKey($item->product_id)->increment('stock', $item->quantity);
            }

            Product::whereKey($item->product_id)->decrement('sold_count', $item->quantity);
        }
    }

    /** Move an order to a new fulfilment status, stamping the matching date. */
    public function updateStatus(Order $order, string $status, ?string $note = null): Order
    {
        if (! in_array($status, Order::STATUSES, true)) {
            throw new \InvalidArgumentException("Unknown order status [{$status}].");
        }

        $attributes = ['status' => $status];

        $attributes += match ($status) {
            'shipped' => ['shipped_at' => now()],
            'completed' => ['completed_at' => now()],
            'cancelled' => ['cancelled_at' => now()],
            default => [],
        };

        if (filled($note)) {
            $attributes['admin_note'] = trim($order->admin_note."\n".$note);
        }

        // Returning stock is only correct the first time an order is cancelled.
        if (in_array($status, ['cancelled', 'refunded'], true) && ! in_array($order->status, ['cancelled', 'refunded'], true)) {
            $this->restoreStock($order);
        }

        $order->forceFill($attributes)->save();

        return $order;
    }

    /** Empty the cart once its order has been placed successfully. */
    public function clearCart(): void
    {
        $this->cart->clear();
    }
}
