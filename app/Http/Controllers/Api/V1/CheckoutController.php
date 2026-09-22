<?php

namespace App\Http\Controllers\Api\V1;

use App\Cms\Api\ApiManager;
use App\Cms\Api\Resource;
use App\Cms\Payments\PaymentManager;
use App\Cms\Payments\PaymentResult;
use App\Cms\Shop\AddressBook;
use App\Cms\Shop\CartService;
use App\Cms\Shop\Countries;
use App\Cms\Shop\OrderService;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\Concerns\ResolvesApiCart;
use App\Models\Order;
use App\Models\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Checkout, and the hand-off to whoever takes the money.
 *
 * The order is written here, exactly as it is on the website, and the totals
 * are recomputed from the database while it is written - the app sends an
 * address and a choice of payment method, never a price.
 *
 * Payment itself is not reimplemented. An offline method (cash on delivery, a
 * bank transfer) is settled here because there is nothing to redirect to;
 * everything else hands the app a signed, expiring URL to open, which drops
 * the customer into the same payment flow the website uses. That is
 * deliberate: a gateway's return, signature check and webhook are the part
 * you least want two copies of.
 */
class CheckoutController extends ApiController
{
    use ResolvesApiCart;

    /** How long the app has to open the payment link before it stops working. */
    private const HANDOFF_MINUTES = 30;

    public function __construct(
        private CartService $cart,
        private OrderService $orders,
        private PaymentManager $payments,
        private AddressBook $addresses,
        private ApiManager $api,
    ) {}

    /** Everything the checkout screen needs before anybody types anything. */
    public function show(Request $request): JsonResponse
    {
        $token = $this->bindCart($request, $this->cart);
        $user = $request->user();
        $remembers = (bool) setting('shop_save_addresses', true);

        return $this->data([
            'cart' => Resource::cart(
                $this->cart->cart(),
                $this->cart->summary(),
                $this->cart->validationIssues(),
                $token,
            ),
            'guest_checkout' => (bool) setting('shop_guest_checkout', true),
            'requires_shipping' => $this->cart->requiresShipping(),
            'payment_methods' => $this->payments->availableFor()
                ->map(fn (PaymentGateway $gateway) => [
                    'slug' => $gateway->slug,
                    'name' => $gateway->name,
                    'flow' => $gateway->flow(),
                    // 'manual' methods are settled without leaving the app.
                    'offline' => $gateway->flow() === 'manual',
                    'instructions' => $gateway->instructions,
                ])
                ->values()
                ->all(),
            'countries' => Countries::selling(),
            'terms_url' => terms_url(),
            'addresses' => $user && $remembers
                ? $user->addresses()->get()->map(fn ($address) => Resource::address($address))->all()
                : [],
            'prefill' => [
                'email' => $user?->email,
                'phone' => $user?->phone,
                'billing' => $remembers ? $this->addresses->prefill($user, 'billing') : [],
                'shipping' => $remembers ? $this->addresses->prefill($user, 'shipping') : [],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->bindCart($request, $this->cart);

        if ($this->cart->isEmpty()) {
            return $this->fail('Your cart is empty.', 422, 'cart_empty');
        }

        if (! setting('shop_guest_checkout', true) && ! $request->user()) {
            return $this->fail('Please sign in to check out.', 401, 'sign_in_required');
        }

        if ($issues = $this->cart->validationIssues()) {
            return $this->fail($issues[0], 409, 'cart_stale');
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'payment_gateway' => ['required', 'string', 'max:40'],
            'customer_note' => ['nullable', 'string', 'max:1000'],

            'billing.name' => ['required', 'string', 'max:120'],
            'billing.line1' => ['required', 'string', 'max:190'],
            'billing.line2' => ['nullable', 'string', 'max:190'],
            'billing.city' => ['required', 'string', 'max:120'],
            'billing.state' => ['nullable', 'string', 'max:120'],
            'billing.postcode' => ['nullable', 'string', 'max:30'],
            'billing.country' => ['required', 'string', Rule::in(Countries::allowedCodes())],

            'ship_to_different' => ['nullable', 'boolean'],
            'shipping.name' => ['required_if:ship_to_different,1', 'nullable', 'string', 'max:120'],
            'shipping.line1' => ['required_if:ship_to_different,1', 'nullable', 'string', 'max:190'],
            'shipping.city' => ['required_if:ship_to_different,1', 'nullable', 'string', 'max:120'],
            'shipping.country' => ['required_if:ship_to_different,1', 'nullable', 'string', Rule::in(Countries::allowedCodes())],

            'save_address' => ['nullable', 'boolean'],
            'terms' => ['accepted'],
        ], [
            'billing.country.in' => 'We are not able to sell to that country yet.',
            'shipping.country.in' => 'We are not able to deliver to that country yet.',
        ]);

        // Checked against what is actually live, so a crafted request cannot
        // pick a disabled or half-configured provider.
        if (! $this->payments->isAvailable($validated['payment_gateway'])) {
            return $this->fail('That payment method is not available.', 422, 'gateway_unavailable');
        }

        $billing = $this->addresses->normalise($validated['billing']);
        $shipping = $request->boolean('ship_to_different')
            ? $this->addresses->normalise($validated['shipping'])
            : $billing;

        try {
            $order = $this->orders->createFromCart([
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'payment_gateway' => $validated['payment_gateway'],
                'billing_address' => $billing,
                'shipping_address' => $shipping,
                'customer_note' => $validated['customer_note'] ?? null,
            ], $request->user());
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 409, 'order_failed');
        }

        $this->rememberAddresses($request, $billing, $shipping);

        // The order owns the items now; leaving the cart full invites a
        // second, identical order.
        $this->orders->clearCart();

        return $this->data([
            'order' => Resource::order($order->fresh(['items']), full: true),
            // A guest has no account to look the order up against later, so
            // they get a handle to it. See orderToken().
            'order_token' => $request->user() ? null : $this->orderToken($order),
            'payment' => $this->handOff($order),
        ], status: 201);
    }

    /** One of the caller's own orders. */
    public function order(Request $request, string $number): JsonResponse
    {
        $order = $this->findOwnOrder($request, $number);

        if (! $order) {
            return $this->fail('No such order.', 404, 'not_found');
        }

        return $this->data(Resource::order($order->load('items.product'), full: true));
    }

    public function orders(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($this->perPage($request));

        return $this->page(Resource::paginated($orders, fn (Order $order) => Resource::order($order)));
    }

    // Internals -------------------------------------------------------------

    /**
     * How this order gets paid.
     *
     * Offline methods are started here and come back as instructions or as a
     * paid order. Everything else comes back as a URL to open: signed with
     * the application key, good for half an hour, and useless to anybody who
     * did not just place this order.
     */
    private function handOff(Order $order): array
    {
        $gateway = $this->payments->model($order->payment_gateway);

        if ($gateway && $gateway->flow() === 'manual') {
            return $this->settleOffline($order);
        }

        return [
            'type' => 'web',
            'url' => URL::temporarySignedRoute(
                'checkout.app',
                now()->addMinutes(self::HANDOFF_MINUTES),
                ['order' => $order->order_number],
            ),
            'expires_in' => self::HANDOFF_MINUTES * 60,
            // Where the browser lands when it is over, so the app knows when
            // to close the web view and stop watching.
            'success_url' => route('checkout.success', $order->order_number),
            'status_url' => $this->statusUrl($order),
        ];
    }

    private function settleOffline(Order $order): array
    {
        try {
            $result = $this->payments->driver($order->payment_gateway)->initiate($order);
        } catch (\Throwable $e) {
            report($e);

            return [
                'type' => 'failed',
                'message' => 'We could not start the payment. The order is saved; please choose another method.',
            ];
        }

        if ($result->status === PaymentResult::SUCCESS) {
            $order->markPaid($result->reference);
            activity('order.paid', "Order {$order->order_number} was paid.", $order);

            return ['type' => 'paid', 'message' => $result->message ?? 'Payment received.'];
        }

        return [
            'type' => 'instructions',
            'message' => $result->message ?? 'Your order has been placed.',
            'status_url' => $this->statusUrl($order),
        ];
    }

    /** Where the app watches this order's payment state. */
    private function statusUrl(Order $order): string
    {
        $url = url($this->api->prefix().'/'.$this->api->version().'/orders/'.$order->order_number);

        return $order->user_id === null
            ? $url.'?order_token='.$this->orderToken($order)
            : $url;
    }

    /**
     * Save what they just typed, so the next checkout opens filled in. Only
     * once the order exists: an address is never kept from a checkout that
     * failed on its way through.
     */
    private function rememberAddresses(Request $request, array $billing, array $shipping): void
    {
        if (! setting('shop_save_addresses', true)) {
            return;
        }

        $user = $request->user();
        $asked = $request->boolean('save_address');

        $withPhone = function (array $address) use ($request): array {
            $address['phone'] ??= $request->input('phone');

            return $address;
        };

        $this->addresses->remember($user, $withPhone($billing), 'billing', $asked);

        if ($request->boolean('ship_to_different')) {
            $this->addresses->remember($user, $withPhone($shipping), 'shipping', $asked);
        }
    }

    /**
     * An order the caller is entitled to see.
     *
     * A signed-in customer gets their own, and only their own. A guest has no
     * account to check an order against, so they present the handle they were
     * given when they placed it - see orderToken(). Without one, a guest gets
     * nothing, however good their guess at an order number.
     *
     * Staff get nothing special here either. This API has no admin side, and
     * an order that is not the caller's own is not the caller's business.
     */
    private function findOwnOrder(Request $request, string $number): ?Order
    {
        $order = Order::where('order_number', $number)->first();

        if (! $order) {
            return null;
        }

        $user = $request->user();

        if ($user) {
            return $order->user_id === $user->id ? $order : null;
        }

        if ($order->user_id !== null) {
            return null;
        }

        $presented = (string) ($request->header('X-Order-Token') ?: $request->input('order_token', ''));

        return $presented !== '' && hash_equals($this->orderToken($order), $presented)
            ? $order
            : null;
    }

    /**
     * A guest's handle on their own order.
     *
     * Derived from the order number with the application key, so nothing has
     * to be stored and nothing can be forged without the key. It proves the
     * holder placed this order; it grants nothing else.
     */
    private function orderToken(Order $order): string
    {
        return substr(
            hash_hmac('sha256', 'api-order:'.$order->order_number, (string) config('app.key')),
            0,
            32
        );
    }
}
