<?php

namespace App\Cms\Payments\Drivers;

use App\Cms\Payments\PaymentResult;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Stripe, via hosted Checkout Sessions.
 *
 * Hosted Checkout keeps card data entirely off this server, so a buyer selling
 * on shared hosting never handles a PAN and stays out of the strictest PCI
 * scope.
 */
class StripeGateway extends AbstractGateway
{
    public function slug(): string
    {
        return 'stripe';
    }

    public function initiate(Order $order): PaymentResult
    {
        $secret = $this->credential('secret_key');

        if (blank($secret)) {
            return $this->fail('Stripe is not configured.', order: $order);
        }

        // Stripe's API is form-encoded, including nested line items.
        $payload = [
            'mode' => 'payment',
            'success_url' => $this->returnUrl($order).'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->cancelUrl($order),
            'client_reference_id' => $order->order_number,
            'customer_email' => $order->email,
            'metadata' => ['order_number' => $order->order_number],
        ];

        foreach ($order->items as $index => $item) {
            $payload["line_items[{$index}][quantity]"] = $item->quantity;
            $payload["line_items[{$index}][price_data][currency]"] = strtolower($order->currency);
            $payload["line_items[{$index}][price_data][unit_amount]"] = $item->unit_price;
            $payload["line_items[{$index}][price_data][product_data][name]"] = $item->name;
        }

        // Shipping and tax are separate lines so the Stripe receipt matches
        // the order summary the customer already agreed to.
        $index = $order->items->count();

        foreach (['Shipping' => $order->shipping_total, 'Tax' => $order->tax_total] as $label => $amount) {
            if ($amount <= 0) {
                continue;
            }

            $payload["line_items[{$index}][quantity]"] = 1;
            $payload["line_items[{$index}][price_data][currency]"] = strtolower($order->currency);
            $payload["line_items[{$index}][price_data][unit_amount]"] = $amount;
            $payload["line_items[{$index}][price_data][product_data][name]"] = $label;
            $index++;
        }

        if ($order->discount_total > 0) {
            // Stripe has no negative line item; a one-off coupon is the
            // supported way to show a discount on the hosted page.
            $coupon = $this->createCoupon($order);

            if ($coupon) {
                $payload['discounts[0][coupon]'] = $coupon;
            }
        }

        $response = $this->http()
            ->withToken($secret)
            ->asForm()
            ->post($this->endpoint().'/checkout/sessions', $payload);

        if ($response->failed()) {
            return $this->fail(
                $response->json('error.message') ?? 'Stripe rejected the checkout session.',
                $response->json() ?? [],
                $order
            );
        }

        $session = $response->json();

        $result = PaymentResult::redirect($session['url'], $session['id'], $session);
        $this->logTransaction($order, 'pending', $result, $this->redact($payload), $session);

        return $result;
    }

    /** A single-use, fixed-amount coupon representing this order's discount. */
    private function createCoupon(Order $order): ?string
    {
        $response = $this->http()
            ->withToken($this->credential('secret_key'))
            ->asForm()
            ->post($this->endpoint().'/coupons', [
                'amount_off' => $order->discount_total,
                'currency' => strtolower($order->currency),
                'duration' => 'once',
                'max_redemptions' => 1,
                'name' => $order->coupon_code ?: 'Discount',
            ]);

        return $response->successful() ? $response->json('id') : null;
    }

    public function handleReturn(Request $request, Order $order): PaymentResult
    {
        $sessionId = $request->query('session_id');

        if (blank($sessionId)) {
            return PaymentResult::failed('Stripe did not return a session reference.');
        }

        // Confirm against the API. The customer controls everything in the
        // return URL, so the redirect alone proves nothing.
        $response = $this->http()
            ->withToken($this->credential('secret_key'))
            ->get($this->endpoint().'/checkout/sessions/'.$sessionId);

        if ($response->failed()) {
            return $this->fail('Could not verify the Stripe session.', $response->json() ?? [], $order);
        }

        $session = $response->json();

        if (($session['client_reference_id'] ?? null) !== $order->order_number) {
            return $this->fail('The Stripe session does not belong to this order.', [], $order);
        }

        if (($session['payment_status'] ?? null) !== 'paid') {
            return PaymentResult::pending('Stripe has not confirmed this payment yet.', $sessionId, $session);
        }

        $result = PaymentResult::success(
            $session['payment_intent'] ?? $sessionId,
            'Paid via Stripe.',
            $session,
            (int) ($session['amount_total'] ?? $order->grand_total)
        );

        $this->logTransaction($order, 'success', $result, response: $session);

        return $result;
    }

    public function handleWebhook(Request $request): ?PaymentResult
    {
        if (! $this->verifyWebhookSignature($request)) {
            return PaymentResult::failed('Invalid Stripe webhook signature.');
        }

        $event = $request->json()->all();

        if (($event['type'] ?? null) !== 'checkout.session.completed') {
            return null;
        }

        $session = $event['data']['object'] ?? [];
        $orderNumber = $session['client_reference_id'] ?? null;

        if (blank($orderNumber)) {
            return null;
        }

        $order = Order::where('order_number', $orderNumber)->first();

        if (! $order || $order->isPaid()) {
            return null;
        }

        $order->markPaid($session['payment_intent'] ?? null);

        $result = PaymentResult::success($session['payment_intent'] ?? null, 'Confirmed by Stripe webhook.', $session);
        $this->logTransaction($order, 'success', $result, response: $session);

        return $result;
    }

    /**
     * Stripe signs webhooks with an HMAC over "timestamp.payload". The
     * timestamp is checked too, so a captured request cannot be replayed.
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $secret = $this->credential('webhook_secret');
        $header = $request->header('Stripe-Signature');

        if (blank($secret) || blank($header)) {
            return false;
        }

        $parts = [];

        foreach (explode(',', $header) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, null);
            $parts[$key][] = $value;
        }

        $timestamp = $parts['t'][0] ?? null;

        if (blank($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($parts['v1'] ?? [] as $signature) {
            if ($this->signatureMatches($expected, (string) $signature)) {
                return true;
            }
        }

        return false;
    }

    public function refund(Order $order, ?int $amount = null): PaymentResult
    {
        if (blank($order->transaction_id)) {
            return PaymentResult::failed('This order has no Stripe payment reference to refund.');
        }

        $payload = array_filter([
            'payment_intent' => $order->transaction_id,
            'amount' => $amount,
        ]);

        $response = $this->http()
            ->withToken($this->credential('secret_key'))
            ->asForm()
            ->post($this->endpoint().'/refunds', $payload);

        if ($response->failed()) {
            return $this->fail($response->json('error.message') ?? 'Stripe refused the refund.', $response->json() ?? [], $order);
        }

        $refund = $response->json();
        $result = PaymentResult::success($refund['id'] ?? null, 'Refunded via Stripe.', $refund);

        $this->logTransaction($order, 'success', $result, response: $refund, type: 'refund', amount: $amount ?? $order->grand_total);

        return $result;
    }
}
