<?php

namespace App\Http\Controllers\Front;

use App\Cms\Payments\PaymentManager;
use App\Cms\Payments\PaymentResult;
use App\Cms\Shop\AddressBook;
use App\Cms\Shop\CartService;
use App\Cms\Shop\CheckoutFields;
use App\Cms\Shop\Countries;
use App\Cms\Shop\OrderService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Checkout and the payment hand-off.
 *
 * The order is written before the customer is sent to a provider, so a payment
 * that succeeds on their side always has something on ours to attach itself
 * to - even if the customer closes the tab on the way back.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private OrderService $orders,
        private PaymentManager $payments,
        private AddressBook $addresses,
        private CheckoutFields $fields,
    ) {}

    public function index(Request $request): RedirectResponse|View
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        if (! setting('shop_guest_checkout', true) && ! auth()->check()) {
            return redirect()->route('login')->with('error', 'Please sign in to check out.');
        }

        if ($issues = $this->cart->validationIssues()) {
            return redirect()->route('cart.index')->with('error', $issues[0]);
        }

        seo()->title('Checkout')->noindex();

        $user = auth()->user();
        $remembers = (bool) setting('shop_save_addresses', true);
        $saved = $remembers && $user ? $user->addresses()->get() : collect();

        // ?address=12 switches which saved address the form opens on. Done as
        // a plain link rather than script, so it works on any browser - and
        // the id is looked up inside this customer's own book.
        $chosen = $saved->firstWhere('id', $request->integer('address'));

        return view(theme_view('shop.checkout', 'theme::shop.checkout'), [
            'summary' => $this->cart->summary(),
            'items' => $this->cart->cart()->items,
            'gateways' => $this->payments->availableFor(),
            'user' => $user,
            'countries' => Countries::selling(),
            // The form opens on the address they used last rather than empty.
            'billing' => $chosen?->toOrderArray()
                ?? ($remembers ? $this->addresses->prefill($user, 'billing') : []),
            'shipping' => $remembers ? $this->addresses->prefill($user, 'shipping') : [],
            'savedAddresses' => $saved,
            'chosenAddressId' => $chosen?->id ?? $user?->defaultAddress('billing')?->id,
            'canSaveAddress' => $remembers && $user !== null,
            'checkoutFields' => $this->fields,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        // Which fields are asked for, and which must be filled, is the shop's
        // choice (Settings -> Checkout); the rules for them come from there.
        $validated = $request->validate($this->fields->rules() + [
            'payment_gateway' => ['required', 'string', 'max:40'],
            'save_address' => ['nullable', 'boolean'],
            'terms' => ['accepted'],
        ], $this->fields->messages());

        // The gateway is checked against what is actually live, so a crafted
        // form cannot select a disabled or unconfigured provider.
        if (! $this->payments->isAvailable($validated['payment_gateway'])) {
            return back()->withInput()->with('error', 'That payment method is not available.');
        }

        $billing = $this->addresses->normalise($validated['billing']);
        $shipping = $request->boolean('ship_to_different') && $this->fields->asksForAddress()
            ? $this->addresses->normalise($validated['shipping'] ?? [])
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
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Kept only once the order exists, so an address is never remembered
        // from a checkout that failed on its way through.
        $this->rememberAddresses($request, $billing, $shipping);

        // The cart is emptied here rather than after payment: the order now
        // owns the items, and leaving the cart full invites a double order.
        $this->orders->clearCart();

        session(['checkout.order' => $order->order_number]);

        return redirect()->route('checkout.pay', $order->order_number);
    }

    /**
     * The door a mobile app opens for payment.
     *
     * An order placed through the API has no browser session behind it, so
     * the app is handed a signed, expiring link to this instead. The
     * signature - Laravel's, over the whole URL including its expiry - is
     * what proves the opener just placed this order; 'signed' middleware has
     * already checked it by the time we are here.
     *
     * All this does is establish the session fact the rest of checkout
     * expects, and then step out of the way, so the payment, the provider's
     * return and the confirmation page are the same code paths the website
     * uses. Nothing about paying is reimplemented for apps.
     */
    public function appHandoff(Request $request, string $order): RedirectResponse
    {
        $found = Order::where('order_number', $order)->firstOrFail();

        $request->session()->put('checkout.order', $found->order_number);

        return redirect()->route('checkout.pay', $found->order_number);
    }

    /** Starts the payment and hands the customer to the provider. */
    public function pay(Request $request, string $order): RedirectResponse|View
    {
        $order = $this->findOrder($order);

        if ($order->isPaid()) {
            return redirect()->route('checkout.success', $order->order_number);
        }

        try {
            $result = $this->payments->driver($order->payment_gateway)->initiate($order);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('cart.index')
                ->with('error', 'We could not start the payment. Please try again or choose another method.');
        }

        return match ($result->status) {
            PaymentResult::REDIRECT => redirect()->away($result->redirectUrl),

            // A self-submitting form, for providers that expect a signed POST.
            PaymentResult::FORM => response()->view('shop.gateway-form', [
                'action' => $result->redirectUrl,
                'fields' => $result->data,
                'gateway' => $order->payment_gateway,
            ]),

            // The provider's JS modal opens over our page.
            PaymentResult::CHECKOUT => view(theme_view('shop.gateway-checkout', 'theme::shop.gateway-checkout'), [
                'order' => $order,
                'gateway' => $order->payment_gateway,
                'options' => $result->data,
            ]),

            PaymentResult::SUCCESS => $this->completeOrder($order, $result),

            // Offline methods: nothing to redirect to, so go straight to the
            // confirmation page with the payment instructions on it.
            PaymentResult::PENDING => redirect()->route('checkout.success', $order->order_number)
                ->with('status', $result->message),

            default => redirect()->route('cart.index')
                ->with('error', $result->message ?? 'The payment could not be started.'),
        };
    }

    /** Where the provider sends the customer back to. */
    public function handleReturn(Request $request, string $gateway, string $order): RedirectResponse
    {
        $order = $this->findOrder($order);

        if ($order->isPaid()) {
            return redirect()->route('checkout.success', $order->order_number);
        }

        if ($order->payment_gateway !== $gateway) {
            return redirect()->route('cart.index')->with('error', 'That payment does not match this order.');
        }

        try {
            $result = $this->payments->driver($gateway)->handleReturn($request, $order);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('checkout.success', $order->order_number)
                ->with('warning', 'We could not confirm your payment automatically. If you were charged, we will update your order shortly.');
        }

        if ($result->isSuccessful()) {
            return $this->completeOrder($order, $result);
        }

        if ($result->isPending()) {
            return redirect()->route('checkout.success', $order->order_number)
                ->with('warning', $result->message ?? 'Your payment is still being confirmed.');
        }

        $order->markFailed($result->message);

        return redirect()->route('checkout.success', $order->order_number)
            ->with('error', $result->message ?? 'The payment was not completed.');
    }

    public function cancel(Request $request, string $gateway, string $order): RedirectResponse
    {
        $order = $this->findOrder($order);

        // The order is kept rather than deleted, so the customer can retry
        // from their account without re-entering everything.
        return redirect()->route('checkout.success', $order->order_number)
            ->with('warning', 'You cancelled the payment. This order is still waiting to be paid.');
    }

    public function success(string $order): View
    {
        $order = $this->findOrder($order);

        seo()->title('Order '.$order->order_number)->noindex();

        return view(theme_view('shop.order-confirmation', 'theme::shop.order-confirmation'), [
            'order' => $order->load('items'),
            'instructions' => $this->offlineInstructions($order),
        ]);
    }

    /**
     * Server-to-server callback. Each driver verifies the provider's signature
     * before this is trusted, and a 200 is always returned so the provider
     * does not retry a message we have deliberately ignored.
     */
    public function webhook(Request $request, string $gateway): JsonResponse
    {
        try {
            $result = $this->payments->driver($gateway)->handleWebhook($request);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['received' => true], 200);
        }

        if ($result?->isFailed()) {
            Log::warning("[webhook:{$gateway}] {$result->message}");

            return response()->json(['error' => 'signature'], 400);
        }

        return response()->json(['received' => true]);
    }

    // Helpers -------------------------------------------------------------

    /**
     * Save what they just typed so the next checkout opens filled in.
     *
     * Guests get it in their session; signed-in customers get it in their
     * address book. Either way it is the whole point of this feature: nobody
     * types their own address twice.
     */
    private function rememberAddresses(Request $request, array $billing, array $shipping): void
    {
        if (! setting('shop_save_addresses', true)) {
            return;
        }

        $user = $request->user();
        $asked = $request->boolean('save_address');

        // Checkout asks for the phone number once, above the address; saving
        // it alongside means the courier label is complete next time too.
        $withPhone = function (array $address) use ($request): array {
            if ($this->fields->shows('phone')) {
                $address['phone'] ??= $request->input('phone');
            }

            return $address;
        };

        $this->addresses->remember($user, $withPhone($billing), 'billing', $asked);

        if ($request->boolean('ship_to_different') && $this->fields->asksForAddress()) {
            $this->addresses->remember($user, $withPhone($shipping), 'shipping', $asked);
        }
    }

    private function completeOrder(Order $order, PaymentResult $result): RedirectResponse
    {
        $order->markPaid($result->reference);

        activity('order.paid', "Order {$order->order_number} was paid.", $order);

        return redirect()->route('checkout.success', $order->order_number)
            ->with('status', 'Thank you. Your payment was received.');
    }

    /**
     * Orders are looked up by their unguessable number, and a signed-in user
     * may only reach their own. Guests fall back to the session written at
     * checkout, so a stranger cannot open someone else's confirmation page.
     */
    private function findOrder(string $orderNumber): Order
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        if (auth()->check()) {
            abort_unless(
                $order->user_id === auth()->id() || auth()->user()->isStaff(),
                403
            );

            return $order;
        }

        abort_unless(session('checkout.order') === $order->order_number, 403);

        return $order;
    }

    /** Bank details or COD wording, for the confirmation page. */
    private function offlineInstructions(Order $order): ?string
    {
        if (! in_array($order->payment_gateway, ['bank', 'wise', 'cod'], true)) {
            return null;
        }

        try {
            $driver = $this->payments->driver($order->payment_gateway);
        } catch (\Throwable $e) {
            return null;
        }

        return method_exists($driver, 'instructions') ? $driver->instructions() : null;
    }
}
