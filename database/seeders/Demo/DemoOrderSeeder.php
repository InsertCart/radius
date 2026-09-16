<?php

namespace Database\Seeders\Demo;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Three months of trading: customers, coupons, orders, order lines and the
 * payment attempts behind them.
 *
 * Most of the admin is a set of questions about orders - revenue this month,
 * the last thirty days as a chart, what is waiting to be processed - and all
 * of them read as empty on a catalogue with no sales behind it.
 *
 * The orders are generated rather than hand-written, because a chart needs a
 * couple of hundred of them before it looks like a business, but generated
 * from a fixed seed so that two runs produce the same history. Dates are
 * relative to the day the seeder runs, which is what makes 'this month' and
 * 'the last 30 days' mean something whenever the demo is installed.
 */
class DemoOrderSeeder extends Seeder
{
    /**
     * Demo orders carry their own number prefix instead of the shop's, so
     * `cms:demo --remove` can find exactly these and nothing a real customer
     * placed.
     */
    public const PREFIX = 'DEMO-';

    /** Fixed, so the same run produces the same history. */
    private const SEED = 20260401;

    /** How far back the generated history reaches. */
    private const DAYS = 90;

    /**
     * How often each product is bought, relative to the others. A roaster
     * sells far more bags of the house blend than electric grinders, and a
     * best-seller list where everything is level is no test of anything.
     */
    private const DEMAND = [
        'morning-blend' => 12,
        'kirinyaga-aa' => 9,
        'huila-reserve' => 8,
        'paper-filters-100' => 7,
        'night-owl-decaf' => 5,
        'brew-guide-ebook' => 4,
        'ceramic-dripper' => 3,
        'insulated-tumbler' => 3,
        'tasting-journal' => 3,
        'starter-kit' => 3,
        'pour-over-kettle' => 2,
        'cupping-spoon-set' => 2,
        'hand-grinder-pro' => 2,
        'electric-grinder' => 1,
    ];

    /** Weighted the way a small shop's gateway mix usually looks. */
    private const GATEWAYS = [
        'stripe' => 42,
        'razorpay' => 22,
        'paypal' => 18,
        'cod' => 12,
        'bank' => 6,
    ];

    /**
     * Registered buyers. Sign-in is deliberately impossible, exactly as with
     * the demo authors: these are order histories, not a way into the site.
     *
     * @return array<int, array<string, string>>
     */
    public static function customers(): array
    {
        return [
            ['email' => 'priya.nair@demo.invalid', 'name' => 'Priya Nair', 'phone' => '+91 9845 012 337',
                'line1' => '14 Rustom Bagh, HAL Old Airport Road', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postcode' => '560017', 'country' => 'India'],
            ['email' => 'marcus.doyle@demo.invalid', 'name' => 'Marcus Doyle', 'phone' => '+44 7700 900412',
                'line1' => '61 Bellevue Terrace', 'line2' => 'Flat 3', 'city' => 'Edinburgh', 'state' => '', 'postcode' => 'EH7 4DT', 'country' => 'United Kingdom'],
            ['email' => 'hannah.reid@demo.invalid', 'name' => 'Hannah Reid', 'phone' => '+1 415 555 0182',
                'line1' => '2210 Fillmore Street', 'city' => 'San Francisco', 'state' => 'CA', 'postcode' => '94115', 'country' => 'United States'],
            ['email' => 'daniel.okafor@demo.invalid', 'name' => 'Daniel Okafor', 'phone' => '+44 7700 900118',
                'line1' => '8 Tenby Road', 'city' => 'Manchester', 'state' => '', 'postcode' => 'M12 5HQ', 'country' => 'United Kingdom'],
            ['email' => 'lena.fischer@demo.invalid', 'name' => 'Lena Fischer', 'phone' => '+49 151 2345 6789',
                'line1' => 'Oranienstrasse 112', 'city' => 'Berlin', 'state' => '', 'postcode' => '10969', 'country' => 'Germany'],
            ['email' => 'ravi.shankar@demo.invalid', 'name' => 'Ravi Shankar', 'phone' => '+91 99302 44118',
                'line1' => '402 Sunder Nagar, Kalina', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'postcode' => '400098', 'country' => 'India'],
            ['email' => 'nadia.kovac@demo.invalid', 'name' => 'Nadia Kovac', 'phone' => '+61 400 118 220',
                'line1' => '19 Gertrude Street', 'city' => 'Melbourne', 'state' => 'VIC', 'postcode' => '3065', 'country' => 'Australia'],
            ['email' => 'owen.bradley@demo.invalid', 'name' => 'Owen Bradley', 'phone' => '+1 206 555 0147',
                'line1' => '905 East Pine Street', 'line2' => 'Apt 4B', 'city' => 'Seattle', 'state' => 'WA', 'postcode' => '98122', 'country' => 'United States'],
            ['email' => 'fiona.mackay@demo.invalid', 'name' => 'Fiona Mackay', 'phone' => '+44 7700 900773',
                'line1' => '27 Lark Lane', 'city' => 'Liverpool', 'state' => '', 'postcode' => 'L17 8UU', 'country' => 'United Kingdom'],
            ['email' => 'arjun.mehta@demo.invalid', 'name' => 'Arjun Mehta', 'phone' => '+91 97110 88245',
                'line1' => 'C-7 Hauz Khas Enclave', 'city' => 'New Delhi', 'state' => 'Delhi', 'postcode' => '110016', 'country' => 'India'],
            ['email' => 'marta.lopez@demo.invalid', 'name' => 'Marta Lopez', 'phone' => '+34 612 345 678',
                'line1' => 'Carrer de Verdi 84', 'city' => 'Barcelona', 'state' => '', 'postcode' => '08012', 'country' => 'Spain'],
            ['email' => 'chris.whelan@demo.invalid', 'name' => 'Chris Whelan', 'phone' => '+353 87 123 4567',
                'line1' => '12 Synge Street', 'city' => 'Dublin', 'state' => '', 'postcode' => 'D08 R6K2', 'country' => 'Ireland'],
        ];
    }

    /**
     * Buyers who never made an account. Guest checkout is on by default, so a
     * demo where every order has a user behind it hides a whole code path.
     *
     * @return array<int, array<string, string>>
     */
    public static function guests(): array
    {
        return [
            ['email' => 'j.whitfield@demo.invalid', 'name' => 'Joanne Whitfield', 'phone' => '+44 7700 900254',
                'line1' => '3 Kingsdown Parade', 'city' => 'Bristol', 'state' => '', 'postcode' => 'BS6 5UD', 'country' => 'United Kingdom'],
            ['email' => 'tomas.berg@demo.invalid', 'name' => 'Tomas Berg', 'phone' => '+46 70 123 45 67',
                'line1' => 'Bondegatan 44', 'city' => 'Stockholm', 'state' => '', 'postcode' => '116 33', 'country' => 'Sweden'],
            ['email' => 'sana.qureshi@demo.invalid', 'name' => 'Sana Qureshi', 'phone' => '+91 90040 21187',
                'line1' => '18 Banjara Hills Road No 12', 'city' => 'Hyderabad', 'state' => 'Telangana', 'postcode' => '500034', 'country' => 'India'],
            ['email' => 'greg.palmer@demo.invalid', 'name' => 'Greg Palmer', 'phone' => '+1 312 555 0164',
                'line1' => '1640 North Damen Avenue', 'city' => 'Chicago', 'state' => 'IL', 'postcode' => '60647', 'country' => 'United States'],
            ['email' => 'yuki.tanaka@demo.invalid', 'name' => 'Yuki Tanaka', 'phone' => '+81 90 1234 5678',
                'line1' => '2-14-8 Kichijoji Honcho', 'city' => 'Musashino, Tokyo', 'state' => '', 'postcode' => '180-0004', 'country' => 'Japan'],
            ['email' => 'ellie.combe@demo.invalid', 'name' => 'Ellie Combe', 'phone' => '+64 21 123 456',
                'line1' => '55 Ponsonby Road', 'city' => 'Auckland', 'state' => '', 'postcode' => '1011', 'country' => 'New Zealand'],
        ];
    }

    /**
     * A coupon in every state the admin screen filters on: running, expired,
     * switched off, capped, and one that has hit its usage limit.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function coupons(): array
    {
        return [
            ['code' => 'WELCOME10', 'description' => 'Ten percent off a first order', 'type' => 'percent',
                'value' => 1000, 'min_order_total' => 2000, 'max_discount' => 5000, 'usage_limit_per_user' => 1,
                'starts_at' => -120, 'expires_at' => 240, 'is_active' => true],
            ['code' => 'FREESHIP', 'description' => 'Free shipping, no minimum', 'type' => 'free_shipping',
                'value' => 0, 'min_order_total' => 0, 'starts_at' => -90, 'expires_at' => 60, 'is_active' => true],
            ['code' => 'BEANS15', 'description' => 'Fifteen percent off, capped', 'type' => 'percent',
                'value' => 1500, 'min_order_total' => 4000, 'max_discount' => 2500,
                'starts_at' => -45, 'expires_at' => 30, 'is_active' => true],
            ['code' => 'KIT500', 'description' => 'A flat discount on the starter kit', 'type' => 'fixed',
                'value' => 500, 'min_order_total' => 5000, 'starts_at' => -60, 'expires_at' => 45, 'is_active' => true],
            ['code' => 'LAUNCH25', 'description' => 'Opening week, long finished', 'type' => 'percent',
                'value' => 2500, 'min_order_total' => 0, 'usage_limit' => 100,
                'starts_at' => -360, 'expires_at' => -300, 'is_active' => true],
            ['code' => 'STAFFONLY', 'description' => 'Switched off until the next staff sale', 'type' => 'percent',
                'value' => 3000, 'min_order_total' => 0, 'starts_at' => -30, 'expires_at' => null, 'is_active' => false],
        ];
    }

    /** @return array<int, string> */
    public static function customerEmails(): array
    {
        return array_column(self::customers(), 'email');
    }

    /** @return array<int, string> */
    public static function couponCodes(): array
    {
        return array_column(self::coupons(), 'code');
    }

    public function run(): void
    {
        $products = Product::with('variants')
            ->whereIn('slug', DemoShopSeeder::productSlugs())
            ->where('status', 'published')
            ->get()
            ->keyBy('slug');

        if ($products->isEmpty()) {
            $this->command?->warn('No demo products are installed, so no demo orders were created.');

            return;
        }

        mt_srand(self::SEED);

        $customers = $this->syncCustomers();
        $coupons = $this->syncCoupons();

        // Rebuilt rather than patched. Every date is relative to today, so an
        // order written a week ago sits in the wrong place on the chart now;
        // clearing first is what keeps a re-run a repair rather than a mess.
        Order::where('order_number', 'like', self::PREFIX.'%')->delete();

        $sequence = 0;

        for ($daysAgo = self::DAYS; $daysAgo >= 0; $daysAgo--) {
            for ($i = 0, $placed = $this->ordersPlacedOn($daysAgo); $i < $placed; $i++) {
                $this->createOrder(++$sequence, $daysAgo, $products, $customers, $coupons);
            }
        }

        $this->syncCouponUsage();

        $this->command?->info("  Created {$sequence} demo orders across ".self::DAYS.' days.');
    }

    /**
     * How busy a given day was: quiet at the weekend, steadier midweek, so
     * the revenue chart has a shape instead of a flat row of equal bars.
     */
    private function ordersPlacedOn(int $daysAgo): int
    {
        return now()->subDays($daysAgo)->isWeekend() ? mt_rand(0, 2) : mt_rand(1, 4);
    }

    /**
     * @param  Collection<string, Product>  $products
     * @param  array<string, int>  $customers  email => user id
     * @param  Collection<string, Coupon>  $coupons
     */
    private function createOrder(int $sequence, int $daysAgo, Collection $products, array $customers, Collection $coupons): void
    {
        $placedAt = $this->placedAt($daysAgo);
        $lines = $this->basket($products);
        $buyer = $this->buyer($customers);

        $subtotal = (int) array_sum(array_column($lines, 'line_total'));
        $coupon = $this->couponFor($subtotal, $coupons);
        $discount = $coupon?->discountFor($subtotal) ?? 0;
        $shipping = $this->shipping($subtotal - $discount, $coupon, $this->shippable($lines, $products));
        $tax = $this->tax($subtotal - $discount);

        $state = $this->lifecycle($daysAgo, $placedAt);
        $gateway = $this->gateway();

        $order = new Order([
            'order_number' => self::PREFIX.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'user_id' => $buyer['user_id'],
            'email' => $buyer['email'],
            'phone' => $buyer['phone'],
            'status' => $state['status'],
            'payment_status' => $state['payment_status'],
            'payment_gateway' => $gateway,
            'transaction_id' => in_array($state['payment_status'], ['paid', 'refunded'], true)
                ? $this->reference($gateway)
                : null,
            'currency' => setting('shop_currency', 'USD'),
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'shipping_total' => $shipping,
            'tax_total' => $tax,
            'grand_total' => max(0, $subtotal - $discount + $shipping + (setting('shop_tax_inclusive', false) ? 0 : $tax)),
            'coupon_code' => $coupon?->code,
            'billing_address' => $buyer['address'],
            'shipping_address' => $buyer['address'],
            'customer_note' => $this->note(),
            'admin_note' => $state['admin_note'],
            'tracking_number' => $state['tracking_number'],
            'ip_address' => $buyer['ip'],
            'paid_at' => $state['paid_at'],
            'shipped_at' => $state['shipped_at'],
            'completed_at' => $state['completed_at'],
            'cancelled_at' => $state['cancelled_at'],
        ]);

        // Set by hand so the order lands in the past. Eloquent leaves a
        // timestamp alone once it has been filled in explicitly.
        $order->created_at = $placedAt;
        $order->updated_at = $state['updated_at'];
        $order->save();

        $order->items()->createMany($lines);

        OrderItem::where('order_id', $order->id)
            ->update(['created_at' => $placedAt, 'updated_at' => $placedAt]);

        $this->transactions($order, $state, $gateway);
    }

    /** A plausible hour of the day, and never in the future for today's orders. */
    private function placedAt(int $daysAgo): Carbon
    {
        $placedAt = now()->subDays($daysAgo)->setTime(mt_rand(7, 21), mt_rand(0, 59), mt_rand(0, 59));

        return $placedAt->isFuture() ? now()->subMinutes(mt_rand(5, 180)) : $placedAt;
    }

    /**
     * One to three lines, drawn against the demand weights above.
     *
     * @param  Collection<string, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    private function basket(Collection $products): array
    {
        $pool = $this->demandPool($products);
        $roll = mt_rand(1, 100);
        $wanted = match (true) {
            $roll <= 55 => 1,
            $roll <= 85 => 2,
            default => 3,
        };

        $lines = [];
        $picked = [];

        for ($i = 0; $i < $wanted; $i++) {
            $slug = $pool[mt_rand(0, count($pool) - 1)];

            if (isset($picked[$slug])) {
                continue;
            }

            $picked[$slug] = true;
            $lines[] = $this->line($products[$slug]);
        }

        return $lines;
    }

    /** @return array<int, array<string, mixed>> */
    private function line(Product $product): array
    {
        $variant = $this->variantFor($product);
        $unitPrice = $variant ? $variant->effectivePrice() : $product->effectivePrice();

        // Coffee gets stocked up on; nobody buys three grinders.
        $quantity = $product->price <= 2000 ? mt_rand(1, 3) : 1;

        return [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'name' => $variant ? $product->name.' - '.$variant->optionsLabel() : $product->name,
            'sku' => $variant?->sku ?: $product->sku,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * $quantity,
            'options' => $variant?->options,
            'digital_file' => $product->isDigital() ? $product->digital_file : null,
        ];
    }

    private function variantFor(Product $product): ?ProductVariant
    {
        $variants = $product->variants->where('is_active', true)->values();

        if ($variants->isEmpty()) {
            return null;
        }

        $variant = $variants[mt_rand(0, $variants->count() - 1)];

        // effectivePrice() falls back to the parent when a variant has no
        // price of its own, and the inverse relation is not loaded for us.
        $variant->setRelation('product', $product);

        return $variant;
    }

    /**
     * @param  Collection<string, Product>  $products
     * @return array<int, string>
     */
    private function demandPool(Collection $products): array
    {
        $pool = [];

        foreach (self::DEMAND as $slug => $weight) {
            if ($products->has($slug)) {
                $pool = array_merge($pool, array_fill(0, $weight, $slug));
            }
        }

        // Every weighted product is missing - fall back to whatever is there,
        // so a trimmed-down catalogue still produces orders.
        return $pool ?: $products->keys()->all();
    }

    /**
     * @param  array<string, int>  $customers  email => user id
     * @return array<string, mixed>
     */
    private function buyer(array $customers): array
    {
        // Roughly one order in four is a guest checkout.
        $guest = mt_rand(1, 100) <= 25;
        $people = $guest ? self::guests() : self::customers();
        $person = $people[mt_rand(0, count($people) - 1)];

        return [
            'user_id' => $guest ? null : ($customers[$person['email']] ?? null),
            'email' => $person['email'],
            'phone' => $person['phone'],
            'ip' => '198.51.100.'.mt_rand(2, 250),
            'address' => [
                'name' => $person['name'],
                'line1' => $person['line1'],
                'line2' => $person['line2'] ?? '',
                'city' => $person['city'],
                'state' => $person['state'],
                'postcode' => $person['postcode'],
                'country' => $person['country'],
            ],
        ];
    }

    /**
     * Where an order has got to, given how long ago it was placed. Old orders
     * are nearly all finished; today's are mostly still waiting on somebody,
     * which is what puts a real number on the dashboard's to-do list.
     *
     * @return array<string, mixed>
     */
    private function lifecycle(int $daysAgo, Carbon $placedAt): array
    {
        $roll = mt_rand(1, 100);

        $state = match (true) {
            $daysAgo >= 12 => match (true) {
                $roll <= 86 => 'completed',
                $roll <= 92 => 'refunded',
                $roll <= 97 => 'cancelled',
                default => 'failed',
            },
            $daysAgo >= 5 => match (true) {
                $roll <= 62 => 'completed',
                $roll <= 88 => 'shipped',
                $roll <= 95 => 'cancelled',
                default => 'failed',
            },
            $daysAgo >= 2 => match (true) {
                $roll <= 55 => 'processing',
                $roll <= 85 => 'shipped',
                $roll <= 95 => 'pending',
                default => 'failed',
            },
            default => match (true) {
                $roll <= 45 => 'pending',
                $roll <= 92 => 'processing',
                default => 'failed',
            },
        };

        $blank = [
            'status' => 'pending', 'payment_status' => 'unpaid',
            'paid_at' => null, 'shipped_at' => null, 'completed_at' => null, 'cancelled_at' => null,
            'tracking_number' => null, 'admin_note' => null, 'refunded' => false,
        ];

        $paidAt = $this->notFuture($placedAt->copy()->addMinutes(mt_rand(1, 240)));
        $shippedAt = $this->notFuture($paidAt->copy()->addDays(mt_rand(1, 2)));

        $resolved = match ($state) {
            'completed' => [
                'status' => 'completed', 'payment_status' => 'paid',
                'paid_at' => $paidAt, 'shipped_at' => $shippedAt,
                'completed_at' => $this->notFuture($shippedAt->copy()->addDays(mt_rand(2, 5))),
                'tracking_number' => $this->tracking(),
            ],
            'shipped' => [
                'status' => 'shipped', 'payment_status' => 'paid',
                'paid_at' => $paidAt, 'shipped_at' => $shippedAt,
                'tracking_number' => $this->tracking(),
            ],
            'processing' => [
                'status' => 'processing', 'payment_status' => 'paid',
                'paid_at' => $paidAt,
            ],
            'refunded' => [
                'status' => 'refunded', 'payment_status' => 'refunded',
                'paid_at' => $paidAt, 'shipped_at' => $shippedAt,
                'tracking_number' => $this->tracking(),
                'admin_note' => $this->refundReason(),
                'refunded' => true,
            ],
            'cancelled' => [
                'status' => 'cancelled', 'payment_status' => 'unpaid',
                'cancelled_at' => $this->notFuture($placedAt->copy()->addHours(mt_rand(2, 40))),
                'admin_note' => 'Cancelled at the customer\'s request before dispatch.',
            ],
            // A payment that never went through. Recent ones still sit in the
            // queue waiting for somebody to chase; old ones were given up on.
            default => $daysAgo >= 7
                ? [
                    'status' => 'cancelled', 'payment_status' => 'failed',
                    'cancelled_at' => $this->notFuture($placedAt->copy()->addDays(3)),
                    'admin_note' => 'Payment failed and the customer did not try again.',
                ]
                : [
                    'status' => 'pending', 'payment_status' => 'failed',
                    'admin_note' => 'Card declined by the issuer. Customer emailed about retrying.',
                ],
        };

        $resolved = array_merge($blank, $resolved);

        $resolved['updated_at'] = $resolved['completed_at']
            ?? $resolved['cancelled_at']
            ?? $resolved['shipped_at']
            ?? $resolved['paid_at']
            ?? $placedAt;

        return $resolved;
    }

    /**
     * The gateway's side of the story: one attempt per order, plus a refund
     * row where money went back. This is what the order screen reconciles
     * against, so an order with no transactions behind it proves nothing.
     *
     * @param  array<string, mixed>  $state
     */
    private function transactions(Order $order, array $state, string $gateway): void
    {
        if ($state['payment_status'] === 'unpaid') {
            return;
        }

        $failed = $state['payment_status'] === 'failed';
        $at = $state['paid_at'] ?? $order->created_at->copy()->addMinutes(mt_rand(1, 90));

        $payment = $order->transactions()->create([
            'gateway' => $gateway,
            'type' => 'payment',
            'reference' => $order->order_number,
            'gateway_reference' => $failed ? null : $order->transaction_id,
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'status' => $failed ? 'failed' : 'success',
            'message' => $failed ? 'card_declined: the issuing bank refused the charge' : null,
        ]);

        $payment->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

        if (! $state['refunded']) {
            return;
        }

        $refundedAt = $this->notFuture($at->copy()->addDays(mt_rand(3, 9)));

        $refund = $order->transactions()->create([
            'gateway' => $gateway,
            'type' => 'refund',
            'reference' => $order->order_number.'-R',
            'gateway_reference' => $this->reference($gateway),
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'status' => 'success',
            'message' => 'Full refund issued from the admin.',
        ]);

        $refund->forceFill(['created_at' => $refundedAt, 'updated_at' => $refundedAt])->saveQuietly();
    }

    /**
     * Does anything in the basket physically ship? A download-only order is
     * never charged postage.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  Collection<string, Product>  $products
     */
    private function shippable(array $lines, Collection $products): bool
    {
        foreach ($lines as $line) {
            if ($products->firstWhere('id', $line['product_id'])?->requires_shipping) {
                return true;
            }
        }

        return false;
    }

    /** Mirrors CartService, so demo totals match what the shop would charge today. */
    private function shipping(int $payable, ?Coupon $coupon, bool $shippable): int
    {
        if (! $shippable || $coupon?->givesFreeShipping()) {
            return 0;
        }

        $threshold = to_minor_units(setting('shop_free_shipping_over', 0));

        if ($threshold > 0 && $payable >= $threshold) {
            return 0;
        }

        return to_minor_units(setting('shop_shipping_flat', 0));
    }

    /** Mirrors CartService for the same reason as shipping(). */
    private function tax(int $taxable): int
    {
        if (! setting('shop_tax_enabled', false)) {
            return 0;
        }

        $rate = (float) setting('shop_tax_rate', 0);

        if ($rate <= 0) {
            return 0;
        }

        return setting('shop_tax_inclusive', false)
            ? (int) round($taxable - ($taxable / (1 + $rate / 100)))
            : (int) round($taxable * ($rate / 100));
    }

    /**
     * About one order in eight used a code, and only ever one the order
     * actually qualifies for.
     *
     * @param  Collection<string, Coupon>  $coupons
     */
    private function couponFor(int $subtotal, Collection $coupons): ?Coupon
    {
        if (mt_rand(1, 100) > 13) {
            return null;
        }

        $usable = $coupons->filter(fn (Coupon $coupon) => $coupon->is_active
            && $subtotal >= $coupon->min_order_total
            && ! $coupon->expires_at?->isPast())->values();

        return $usable->isEmpty() ? null : $usable[mt_rand(0, $usable->count() - 1)];
    }

    private function gateway(): string
    {
        $pool = [];

        foreach (self::GATEWAYS as $slug => $weight) {
            $pool = array_merge($pool, array_fill(0, $weight, $slug));
        }

        return $pool[mt_rand(0, count($pool) - 1)];
    }

    private function reference(string $gateway): string
    {
        $prefix = match ($gateway) {
            'stripe' => 'pi_',
            'razorpay' => 'pay_',
            'paypal' => 'PAYID-',
            'cod' => 'COD-',
            default => 'TRN-',
        };

        return $prefix.strtoupper(Str::random(14));
    }

    private function tracking(): string
    {
        return 'TRK'.mt_rand(100000000, 999999999).'GB';
    }

    /** Most people say nothing at checkout; a few say something useful. */
    private function note(): ?string
    {
        $notes = [
            'Please leave with the neighbour at number 12 if I am out.',
            'Gift - no invoice in the box please.',
            'Grind for a V60 if you can, otherwise whole bean is fine.',
            'Second attempt, the first order failed at payment.',
            'Buzzer is broken, please ring me on arrival.',
        ];

        return mt_rand(1, 100) <= 16 ? $notes[mt_rand(0, count($notes) - 1)] : null;
    }

    private function refundReason(): string
    {
        $reasons = [
            'Bag arrived split. Refunded in full, replacement sent free of charge.',
            'Customer ordered the wrong grind and the coffee was unopened. Refunded.',
            'Delivery lost in transit. Refunded pending the carrier claim.',
        ];

        return $reasons[mt_rand(0, count($reasons) - 1)];
    }

    private function notFuture(Carbon $date): Carbon
    {
        return $date->isFuture() ? now() : $date;
    }

    /** @return array<string, int> email => user id */
    private function syncCustomers(): array
    {
        $ids = [];

        foreach (self::customers() as $person) {
            $user = User::withTrashed()->firstOrNew(['email' => $person['email']]);

            $user->fill([
                'name' => $person['name'],
                'role' => User::ROLE_CUSTOMER,
                'phone' => $person['phone'],
                'status' => 'active',
            ]);

            // Hashed from random bytes that are then discarded, so a demo
            // left on a live site is not a set of working logins.
            $user->password = Hash::make(Str::random(64));
            $user->deleted_at = null;
            $user->save();

            // Sign-up dates are spread out so the dashboard's 'new in 30
            // days' hint is a subset rather than the entire customer list.
            $signedUpAt = now()->subDays(mt_rand(2, 400));

            $user->forceFill([
                'created_at' => $signedUpAt,
                'email_verified_at' => $signedUpAt->copy()->addMinutes(mt_rand(2, 90)),
                'last_login_at' => now()->subDays(mt_rand(0, 60)),
                'last_login_ip' => '198.51.100.'.mt_rand(2, 250),
            ])->saveQuietly();

            $ids[$person['email']] = $user->id;
        }

        return $ids;
    }

    /** @return Collection<string, Coupon> */
    private function syncCoupons(): Collection
    {
        $coupons = collect();

        foreach (self::coupons() as $data) {
            $coupons[$data['code']] = Coupon::updateOrCreate(
                ['code' => $data['code']],
                [
                    'description' => $data['description'],
                    'type' => $data['type'],
                    'value' => $data['value'],
                    'min_order_total' => $data['min_order_total'],
                    'max_discount' => $data['max_discount'] ?? null,
                    'usage_limit' => $data['usage_limit'] ?? null,
                    'usage_limit_per_user' => $data['usage_limit_per_user'] ?? null,
                    'starts_at' => isset($data['starts_at']) ? now()->addDays($data['starts_at']) : null,
                    'expires_at' => isset($data['expires_at']) ? now()->addDays($data['expires_at']) : null,
                    'is_active' => $data['is_active'],
                ]
            );
        }

        return $coupons;
    }

    /**
     * Counted from the orders rather than incremented as they are written, so
     * that re-running the seeder cannot inflate the figure.
     */
    private function syncCouponUsage(): void
    {
        foreach (self::couponCodes() as $code) {
            Coupon::where('code', $code)->update([
                'used_count' => Order::where('coupon_code', $code)
                    ->where('order_number', 'like', self::PREFIX.'%')
                    ->count(),
            ]);
        }
    }
}
