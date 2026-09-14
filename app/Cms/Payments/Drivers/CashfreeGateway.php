<?php

namespace App\Cms\Payments\Drivers;

use App\Cms\Payments\PaymentResult;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Cashfree Payments, via the PG v3 Orders API and its hosted checkout page.
 */
class CashfreeGateway extends AbstractGateway
{
    /** Cashfree pins behaviour to a dated API version. */
    private const API_VERSION = '2023-08-01';

    public function slug(): string
    {
        return 'cashfree';
    }

    private function headers(): array
    {
        return [
            'x-client-id' => (string) $this->credential('app_id'),
            'x-client-secret' => (string) $this->credential('secret_key'),
            'x-api-version' => self::API_VERSION,
        ];
    }

    public function initiate(Order $order): PaymentResult
    {
        if (blank($this->credential('app_id')) || blank($this->credential('secret_key'))) {
            return $this->fail('Cashfree is not configured.', order: $order);
        }

        $payload = [
            'order_id' => $this->makeReference($order),
            'order_amount' => (float) $this->toDecimal($order->grand_total),
            'order_currency' => $order->currency,
            'order_note' => 'Order '.$order->order_number,
            'customer_details' => [
                // Cashfree requires a stable customer id; hashing the email
                // keeps it stable for guests without exposing the address.
                'customer_id' => 'cust_'.substr(sha1($order->email), 0, 24),
                'customer_name' => (string) (data_get($order->billing_address, 'name') ?: 'Customer'),
                'customer_email' => $order->email,
                'customer_phone' => (string) ($order->phone ?: '9999999999'),
            ],
            'order_meta' => [
                'return_url' => $this->returnUrl($order).'&cf_order_id={order_id}',
                'notify_url' => $this->webhookUrl(),
            ],
            'order_tags' => ['order_number' => $order->order_number],
        ];

        $response = $this->http()
            ->withHeaders($this->headers())
            ->post($this->endpoint().'/orders', $payload);

        if ($response->failed()) {
            return $this->fail(
                $response->json('message') ?? 'Cashfree rejected the order.',
                $response->json() ?? [],
                $order
            );
        }

        $cashfreeOrder = $response->json();
        $sessionId = $cashfreeOrder['payment_session_id'] ?? null;

        if (blank($sessionId)) {
            return $this->fail('Cashfree did not return a payment session.', $cashfreeOrder, $order);
        }

        // The hosted page is reached through the SDK using the session id, so
        // the front end gets the session rather than a plain URL.
        $result = PaymentResult::checkout([
            'payment_session_id' => $sessionId,
            'mode' => $this->isLive() ? 'production' : 'sandbox',
            'redirect_target' => '_self',
        ], $cashfreeOrder['order_id'] ?? null, $cashfreeOrder);

        $this->logTransaction($order, 'pending', $result, $payload, $cashfreeOrder);

        return $result;
    }

    public function handleReturn(Request $request, Order $order): PaymentResult
    {
        $cashfreeOrderId = $request->query('cf_order_id') ?: $request->query('order_id');

        if (blank($cashfreeOrderId)) {
            return PaymentResult::failed('Cashfree did not return an order reference.');
        }

        $response = $this->http()
            ->withHeaders($this->headers())
            ->get($this->endpoint().'/orders/'.$cashfreeOrderId);

        if ($response->failed()) {
            return $this->fail('Could not verify the Cashfree order.', $response->json() ?? [], $order);
        }

        $cashfreeOrder = $response->json();

        if (data_get($cashfreeOrder, 'order_tags.order_number') !== $order->order_number) {
            return $this->fail('The Cashfree order does not belong to this order.', $cashfreeOrder, $order);
        }

        $status = $cashfreeOrder['order_status'] ?? '';

        if ($status !== 'PAID') {
            return $status === 'ACTIVE'
                ? PaymentResult::pending('Cashfree has not received this payment yet.', $cashfreeOrderId, $cashfreeOrder)
                : PaymentResult::failed('Cashfree reports this order as '.$status.'.', $cashfreeOrder);
        }

        $result = PaymentResult::success(
            $cashfreeOrderId,
            'Paid via Cashfree.',
            $cashfreeOrder,
            to_minor_units($cashfreeOrder['order_amount'] ?? 0)
        );

        $this->logTransaction($order, 'success', $result, response: $cashfreeOrder);

        return $result;
    }

    public function handleWebhook(Request $request): ?PaymentResult
    {
        if (! $this->verifyWebhookSignature($request)) {
            return PaymentResult::failed('Invalid Cashfree webhook signature.');
        }

        $event = $request->json()->all();

        if (($event['type'] ?? '') !== 'PAYMENT_SUCCESS_WEBHOOK') {
            return null;
        }

        $orderNumber = data_get($event, 'data.order.order_tags.order_number');

        if (blank($orderNumber)) {
            return null;
        }

        $order = Order::where('order_number', $orderNumber)->first();

        if (! $order || $order->isPaid()) {
            return null;
        }

        $reference = data_get($event, 'data.order.order_id');
        $order->markPaid($reference);

        $result = PaymentResult::success($reference, 'Confirmed by Cashfree webhook.', $event);
        $this->logTransaction($order, 'success', $result, response: $event);

        return $result;
    }

    /**
     * Cashfree signs webhooks with base64(HMAC-SHA256(timestamp + body)).
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $secret = $this->credential('webhook_secret') ?: $this->credential('secret_key');
        $signature = $request->header('x-webhook-signature');
        $timestamp = $request->header('x-webhook-timestamp');

        if (blank($secret) || blank($signature) || blank($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $timestamp.$request->getContent(), $secret, true));

        return $this->signatureMatches($expected, $signature);
    }

    public function refund(Order $order, ?int $amount = null): PaymentResult
    {
        if (blank($order->transaction_id)) {
            return PaymentResult::failed('This order has no Cashfree reference to refund.');
        }

        $response = $this->http()
            ->withHeaders($this->headers())
            ->post($this->endpoint().'/orders/'.$order->transaction_id.'/refunds', [
                'refund_amount' => (float) $this->toDecimal($amount ?? $order->grand_total),
                'refund_id' => 'rf-'.$order->order_number.'-'.time(),
                'refund_note' => 'Refund for '.$order->order_number,
            ]);

        if ($response->failed()) {
            return $this->fail($response->json('message') ?? 'Cashfree refused the refund.', $response->json() ?? [], $order);
        }

        $refund = $response->json();
        $result = PaymentResult::success($refund['refund_id'] ?? null, 'Refunded via Cashfree.', $refund);

        $this->logTransaction($order, 'success', $result, response: $refund, type: 'refund', amount: $amount ?? $order->grand_total);

        return $result;
    }
}
