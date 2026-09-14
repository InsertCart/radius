<?php

namespace App\Cms\Payments\Drivers;

use App\Cms\Payments\PaymentResult;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * PayPal, via the Orders v2 REST API.
 *
 * The flow is create-order, redirect the customer to approve, then capture on
 * return. Capture is what actually takes the money, so the order is only
 * marked paid once PayPal confirms the capture completed.
 */
class PayPalGateway extends AbstractGateway
{
    public function slug(): string
    {
        return 'paypal';
    }

    /**
     * PayPal issues short-lived OAuth tokens. Cached just under their stated
     * lifetime so a busy checkout does not re-authenticate on every request.
     */
    private function accessToken(): ?string
    {
        $clientId = $this->credential('client_id');
        $secret = $this->credential('client_secret');

        if (blank($clientId) || blank($secret)) {
            return null;
        }

        $cacheKey = 'payments.paypal.token.'.md5($clientId.$this->endpoint());

        return Cache::remember($cacheKey, now()->addMinutes(25), function () use ($clientId, $secret) {
            $response = $this->http()
                ->withBasicAuth($clientId, $secret)
                ->asForm()
                ->post($this->endpoint().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            return $response->successful() ? $response->json('access_token') : null;
        });
    }

    public function initiate(Order $order): PaymentResult
    {
        $token = $this->accessToken();

        if (blank($token)) {
            return $this->fail('Could not authenticate with PayPal. Check the client ID and secret.', order: $order);
        }

        $items = $order->items->map(fn ($item) => [
            'name' => mb_substr($item->name, 0, 127),
            'quantity' => (string) $item->quantity,
            'unit_amount' => [
                'currency_code' => $order->currency,
                'value' => $this->toDecimal($item->unit_price),
            ],
        ])->all();

        // PayPal validates that the breakdown adds up to the total exactly,
        // so item_total must be the sum of the lines before tax and shipping.
        $itemTotal = $order->items->sum(fn ($item) => $item->unit_price * $item->quantity);

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->order_number,
                'custom_id' => $order->order_number,
                'invoice_id' => $order->order_number,
                'amount' => [
                    'currency_code' => $order->currency,
                    'value' => $this->toDecimal($order->grand_total),
                    'breakdown' => array_filter([
                        'item_total' => ['currency_code' => $order->currency, 'value' => $this->toDecimal($itemTotal)],
                        'tax_total' => $order->tax_total > 0 ? ['currency_code' => $order->currency, 'value' => $this->toDecimal($order->tax_total)] : null,
                        'shipping' => $order->shipping_total > 0 ? ['currency_code' => $order->currency, 'value' => $this->toDecimal($order->shipping_total)] : null,
                        'discount' => $order->discount_total > 0 ? ['currency_code' => $order->currency, 'value' => $this->toDecimal($order->discount_total)] : null,
                    ]),
                ],
                'items' => $items,
            ]],
            'application_context' => [
                'brand_name' => mb_substr((string) setting('site_name', config('app.name')), 0, 127),
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'return_url' => $this->returnUrl($order),
                'cancel_url' => $this->cancelUrl($order),
            ],
        ];

        $response = $this->http()
            ->withToken($token)
            ->withHeaders(['PayPal-Request-Id' => $this->makeReference($order)])
            ->post($this->endpoint().'/v2/checkout/orders', $payload);

        if ($response->failed()) {
            return $this->fail(
                $response->json('message') ?? 'PayPal rejected the order.',
                $response->json() ?? [],
                $order
            );
        }

        $paypalOrder = $response->json();
        $approveUrl = collect($paypalOrder['links'] ?? [])->firstWhere('rel', 'payer-action')['href']
            ?? collect($paypalOrder['links'] ?? [])->firstWhere('rel', 'approve')['href']
            ?? null;

        if (blank($approveUrl)) {
            return $this->fail('PayPal did not return an approval link.', $paypalOrder, $order);
        }

        $result = PaymentResult::redirect($approveUrl, $paypalOrder['id'], $paypalOrder);
        $this->logTransaction($order, 'pending', $result, $payload, $paypalOrder);

        return $result;
    }

    public function handleReturn(Request $request, Order $order): PaymentResult
    {
        $paypalOrderId = $request->query('token');

        if (blank($paypalOrderId)) {
            return PaymentResult::failed('PayPal did not return an order reference.');
        }

        $token = $this->accessToken();

        if (blank($token)) {
            return $this->fail('Could not authenticate with PayPal to capture the payment.', order: $order);
        }

        $response = $this->http()
            ->withToken($token)
            ->withHeaders(['PayPal-Request-Id' => 'capture-'.$paypalOrderId])
            ->post($this->endpoint()."/v2/checkout/orders/{$paypalOrderId}/capture", new \stdClass);

        $body = $response->json() ?? [];

        // PayPal returns 422 ORDER_ALREADY_CAPTURED if the customer refreshes
        // the return URL. That is a success, not a failure.
        if ($response->status() === 422 && str_contains(json_encode($body), 'ORDER_ALREADY_CAPTURED')) {
            return PaymentResult::success($paypalOrderId, 'Already captured by PayPal.', $body);
        }

        if ($response->failed()) {
            return $this->fail($body['message'] ?? 'PayPal could not capture this payment.', $body, $order);
        }

        $capture = data_get($body, 'purchase_units.0.payments.captures.0', []);

        if (($capture['status'] ?? null) !== 'COMPLETED') {
            return PaymentResult::pending('PayPal has not completed this capture yet.', $paypalOrderId, $body);
        }

        // Guard against a mismatched capture landing on the wrong order.
        if ((data_get($body, 'purchase_units.0.custom_id') ?: data_get($body, 'purchase_units.0.reference_id')) !== $order->order_number) {
            return $this->fail('The PayPal capture does not belong to this order.', $body, $order);
        }

        $result = PaymentResult::success(
            $capture['id'] ?? $paypalOrderId,
            'Paid via PayPal.',
            $body,
            (int) round(((float) data_get($capture, 'amount.value', 0)) * 100)
        );

        $this->logTransaction($order, 'success', $result, response: $body);

        return $result;
    }

    public function handleWebhook(Request $request): ?PaymentResult
    {
        $event = $request->json()->all();

        if (! in_array($event['event_type'] ?? '', ['PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.APPROVED'], true)) {
            return null;
        }

        if (! $this->verifyWebhookSignature($request, $event)) {
            return PaymentResult::failed('Invalid PayPal webhook signature.');
        }

        $orderNumber = data_get($event, 'resource.custom_id')
            ?: data_get($event, 'resource.invoice_id')
            ?: data_get($event, 'resource.purchase_units.0.custom_id');

        if (blank($orderNumber)) {
            return null;
        }

        $order = Order::where('order_number', $orderNumber)->first();

        if (! $order || $order->isPaid()) {
            return null;
        }

        $order->markPaid(data_get($event, 'resource.id'));

        $result = PaymentResult::success(data_get($event, 'resource.id'), 'Confirmed by PayPal webhook.', $event);
        $this->logTransaction($order, 'success', $result, response: $event);

        return $result;
    }

    /**
     * PayPal verifies webhooks server-side rather than with a local HMAC, so
     * this posts the headers back to their verification endpoint.
     */
    private function verifyWebhookSignature(Request $request, array $event): bool
    {
        $webhookId = $this->credential('webhook_id');
        $token = $this->accessToken();

        if (blank($webhookId) || blank($token)) {
            return false;
        }

        $response = $this->http()
            ->withToken($token)
            ->post($this->endpoint().'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id' => $webhookId,
                'webhook_event' => $event,
            ]);

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    public function refund(Order $order, ?int $amount = null): PaymentResult
    {
        if (blank($order->transaction_id)) {
            return PaymentResult::failed('This order has no PayPal capture reference to refund.');
        }

        $token = $this->accessToken();

        if (blank($token)) {
            return PaymentResult::failed('Could not authenticate with PayPal.');
        }

        $payload = $amount ? [
            'amount' => ['value' => $this->toDecimal($amount), 'currency_code' => $order->currency],
        ] : new \stdClass;

        $response = $this->http()
            ->withToken($token)
            ->post($this->endpoint()."/v2/payments/captures/{$order->transaction_id}/refund", $payload);

        if ($response->failed()) {
            return $this->fail($response->json('message') ?? 'PayPal refused the refund.', $response->json() ?? [], $order);
        }

        $refund = $response->json();
        $result = PaymentResult::success($refund['id'] ?? null, 'Refunded via PayPal.', $refund);

        $this->logTransaction($order, 'success', $result, response: $refund, type: 'refund', amount: $amount ?? $order->grand_total);

        return $result;
    }
}
