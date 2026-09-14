<?php

namespace App\Cms\Payments\Drivers;

use App\Cms\Payments\PaymentResult;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Razorpay, via the Orders API and the Checkout JS modal.
 *
 * Razorpay does not redirect: it opens a modal over our own checkout page and
 * posts the signed result back. The signature over "order_id|payment_id" is
 * what proves the payment really happened, so it is verified before anything
 * is marked paid.
 */
class RazorpayGateway extends AbstractGateway
{
    public function slug(): string
    {
        return 'razorpay';
    }

    public function initiate(Order $order): PaymentResult
    {
        $keyId = $this->credential('key_id');
        $keySecret = $this->credential('key_secret');

        if (blank($keyId) || blank($keySecret)) {
            return $this->fail('Razorpay is not configured.', order: $order);
        }

        $payload = [
            // Razorpay works in paise, which is exactly how the shop stores
            // money, so no conversion is needed here.
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'receipt' => $order->order_number,
            'notes' => ['order_number' => $order->order_number],
        ];

        $response = $this->http()
            ->withBasicAuth($keyId, $keySecret)
            ->post($this->endpoint().'/orders', $payload);

        if ($response->failed()) {
            return $this->fail(
                $response->json('error.description') ?? 'Razorpay rejected the order.',
                $response->json() ?? [],
                $order
            );
        }

        $razorpayOrder = $response->json();

        // Options handed straight to the Checkout script on the front end.
        $options = [
            'key' => $keyId,
            'amount' => $razorpayOrder['amount'],
            'currency' => $razorpayOrder['currency'],
            'name' => setting('site_name', config('app.name')),
            'description' => 'Order '.$order->order_number,
            'order_id' => $razorpayOrder['id'],
            'prefill' => [
                'name' => data_get($order->billing_address, 'name', ''),
                'email' => $order->email,
                'contact' => (string) $order->phone,
            ],
            'notes' => ['order_number' => $order->order_number],
            'theme' => ['color' => setting('primary_color', '#2563eb')],
            'callback_url' => $this->returnUrl($order),
            'cancel_url' => $this->cancelUrl($order),
        ];

        $result = PaymentResult::checkout($options, $razorpayOrder['id'], $razorpayOrder);
        $this->logTransaction($order, 'pending', $result, $payload, $razorpayOrder);

        return $result;
    }

    public function handleReturn(Request $request, Order $order): PaymentResult
    {
        $paymentId = $request->input('razorpay_payment_id');
        $razorpayOrderId = $request->input('razorpay_order_id');
        $signature = $request->input('razorpay_signature');

        if (blank($paymentId) || blank($razorpayOrderId) || blank($signature)) {
            return PaymentResult::failed('Razorpay did not return a complete payment reference.');
        }

        $expected = hash_hmac('sha256', $razorpayOrderId.'|'.$paymentId, (string) $this->credential('key_secret'));

        if (! $this->signatureMatches($expected, $signature)) {
            return $this->fail('The Razorpay signature did not verify.', ['order_id' => $razorpayOrderId], $order);
        }

        // The signature proves the payload is genuine; this second call
        // confirms the payment is actually captured and for the right amount.
        $response = $this->http()
            ->withBasicAuth($this->credential('key_id'), $this->credential('key_secret'))
            ->get($this->endpoint().'/payments/'.$paymentId);

        if ($response->failed()) {
            return $this->fail('Could not verify the Razorpay payment.', $response->json() ?? [], $order);
        }

        $payment = $response->json();

        if (! in_array($payment['status'] ?? '', ['captured', 'authorized'], true)) {
            return PaymentResult::pending('Razorpay has not captured this payment yet.', $paymentId, $payment);
        }

        if ((int) ($payment['amount'] ?? 0) !== (int) $order->grand_total) {
            return $this->fail('The Razorpay payment amount does not match the order total.', $payment, $order);
        }

        $result = PaymentResult::success($paymentId, 'Paid via Razorpay.', $payment, (int) $payment['amount']);
        $this->logTransaction($order, 'success', $result, response: $payment);

        return $result;
    }

    public function handleWebhook(Request $request): ?PaymentResult
    {
        $secret = $this->credential('webhook_secret');
        $signature = $request->header('X-Razorpay-Signature');

        if (blank($secret) || blank($signature)) {
            return null;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! $this->signatureMatches($expected, $signature)) {
            return PaymentResult::failed('Invalid Razorpay webhook signature.');
        }

        $event = $request->json()->all();

        if (! in_array($event['event'] ?? '', ['payment.captured', 'order.paid'], true)) {
            return null;
        }

        $entity = data_get($event, 'payload.payment.entity', []);
        $orderNumber = data_get($entity, 'notes.order_number');

        if (blank($orderNumber)) {
            return null;
        }

        $order = Order::where('order_number', $orderNumber)->first();

        if (! $order || $order->isPaid()) {
            return null;
        }

        $order->markPaid($entity['id'] ?? null);

        $result = PaymentResult::success($entity['id'] ?? null, 'Confirmed by Razorpay webhook.', $event);
        $this->logTransaction($order, 'success', $result, response: $event);

        return $result;
    }

    public function refund(Order $order, ?int $amount = null): PaymentResult
    {
        if (blank($order->transaction_id)) {
            return PaymentResult::failed('This order has no Razorpay payment reference to refund.');
        }

        $response = $this->http()
            ->withBasicAuth($this->credential('key_id'), $this->credential('key_secret'))
            ->post($this->endpoint().'/payments/'.$order->transaction_id.'/refund',
                array_filter(['amount' => $amount])
            );

        if ($response->failed()) {
            return $this->fail($response->json('error.description') ?? 'Razorpay refused the refund.', $response->json() ?? [], $order);
        }

        $refund = $response->json();
        $result = PaymentResult::success($refund['id'] ?? null, 'Refunded via Razorpay.', $refund);

        $this->logTransaction($order, 'success', $result, response: $refund, type: 'refund', amount: $amount ?? $order->grand_total);

        return $result;
    }
}
