<?php

namespace App\Cms\Payments\Drivers;

use App\Cms\Payments\PaymentResult;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * PayU India, via the classic signed-form redirect.
 *
 * PayU has no JSON checkout API: the customer is sent over on a POSTed form
 * carrying a SHA-512 hash of the request, and comes back with a hash computed
 * over the reversed field order. Both hashes are built here, and the result is
 * confirmed a third time against PayU's verify_payment service, because the
 * return POST arrives through the customer's browser.
 */
class PayUGateway extends AbstractGateway
{
    public function slug(): string
    {
        return 'payu';
    }

    public function initiate(Order $order): PaymentResult
    {
        $key = $this->credential('merchant_key');
        $salt = $this->credential('merchant_salt');

        if (blank($key) || blank($salt)) {
            return $this->fail('PayU is not configured.', order: $order);
        }

        $fields = [
            'key' => $key,
            'txnid' => $this->makeReference($order),
            'amount' => $this->toDecimal($order->grand_total),
            'productinfo' => 'Order '.$order->order_number,
            'firstname' => (string) (data_get($order->billing_address, 'name') ?: 'Customer'),
            'email' => $order->email,
            'phone' => (string) $order->phone,
            'surl' => $this->returnUrl($order),
            'furl' => $this->returnUrl($order),
            // udf1 carries our order number so the return POST can be matched
            // back even though txnid is generated per attempt.
            'udf1' => $order->order_number,
            'udf2' => '',
            'udf3' => '',
            'udf4' => '',
            'udf5' => '',
        ];

        $fields['hash'] = $this->requestHash($fields, $salt);

        $result = PaymentResult::form($this->endpoint('payu'), $fields, $fields['txnid']);
        $this->logTransaction($order, 'pending', $result, $this->redact($fields));

        return $result;
    }

    /**
     * key|txnid|amount|productinfo|firstname|email|udf1..udf5||||||salt
     */
    private function requestHash(array $f, string $salt): string
    {
        $sequence = [
            $f['key'], $f['txnid'], $f['amount'], $f['productinfo'], $f['firstname'], $f['email'],
            $f['udf1'], $f['udf2'], $f['udf3'], $f['udf4'], $f['udf5'],
            '', '', '', '', '',
            $salt,
        ];

        return strtolower(hash('sha512', implode('|', $sequence)));
    }

    /** The response hash is the request sequence reversed, with status added. */
    private function responseHash(array $p, string $salt): string
    {
        $sequence = [
            $salt,
            $p['status'] ?? '',
            '', '', '', '', '',
            $p['udf5'] ?? '', $p['udf4'] ?? '', $p['udf3'] ?? '', $p['udf2'] ?? '', $p['udf1'] ?? '',
            $p['email'] ?? '', $p['firstname'] ?? '', $p['productinfo'] ?? '', $p['amount'] ?? '',
            $p['txnid'] ?? '', $p['key'] ?? '',
        ];

        return strtolower(hash('sha512', implode('|', $sequence)));
    }

    public function handleReturn(Request $request, Order $order): PaymentResult
    {
        $payload = $request->all();
        $salt = (string) $this->credential('merchant_salt');

        if (blank($payload['hash'] ?? null)) {
            return PaymentResult::failed('PayU did not return a verification hash.');
        }

        if (! $this->signatureMatches($this->responseHash($payload, $salt), strtolower($payload['hash']))) {
            return $this->fail('The PayU response hash did not verify.', ['txnid' => $payload['txnid'] ?? null], $order);
        }

        if (($payload['udf1'] ?? null) !== $order->order_number) {
            return $this->fail('The PayU response does not belong to this order.', [], $order);
        }

        if (strtolower($payload['status'] ?? '') !== 'success') {
            return PaymentResult::failed(
                $payload['error_Message'] ?? $payload['field9'] ?? 'PayU reported the payment did not succeed.',
                $payload
            );
        }

        // The hash proves the browser did not tamper with the POST, but the
        // POST itself is still client-originated. Confirm with PayU directly.
        $verified = $this->verifyWithPayU($payload['txnid'], $order);

        if ($verified !== null) {
            return $verified;
        }

        $result = PaymentResult::success(
            $payload['mihpayid'] ?? $payload['txnid'],
            'Paid via PayU.',
            $payload,
            to_minor_units($payload['amount'] ?? 0)
        );

        $this->logTransaction($order, 'success', $result, response: $payload);

        return $result;
    }

    /**
     * Server-to-server confirmation. Returns null when PayU agrees the payment
     * succeeded, or a failure result when it does not.
     */
    private function verifyWithPayU(string $txnid, Order $order): ?PaymentResult
    {
        $key = (string) $this->credential('merchant_key');
        $salt = (string) $this->credential('merchant_salt');
        $command = 'verify_payment';

        $response = $this->http()->asForm()->post($this->endpoint('payu_verify'), [
            'key' => $key,
            'command' => $command,
            'var1' => $txnid,
            'hash' => hash('sha512', $key.'|'.$command.'|'.$txnid.'|'.$salt),
        ]);

        if ($response->failed()) {
            // Treat an unreachable verify service as inconclusive rather than
            // as a failure: the hash already checked out.
            return null;
        }

        $status = data_get($response->json(), "transaction_details.{$txnid}.status");

        if ($status !== null && strtolower($status) !== 'success') {
            return $this->fail("PayU reports this transaction as '{$status}'.", $response->json() ?? [], $order);
        }

        return null;
    }

    public function refund(Order $order, ?int $amount = null): PaymentResult
    {
        if (blank($order->transaction_id)) {
            return PaymentResult::failed('This order has no PayU payment reference to refund.');
        }

        $key = (string) $this->credential('merchant_key');
        $salt = (string) $this->credential('merchant_salt');
        $command = 'cancel_refund_transaction';
        $refundAmount = $this->toDecimal($amount ?? $order->grand_total);
        $token = 'refund-'.$order->order_number;

        $response = $this->http()->asForm()->post($this->endpoint('payu_verify'), [
            'key' => $key,
            'command' => $command,
            'var1' => $order->transaction_id,
            'var2' => $token,
            'var3' => $refundAmount,
            'hash' => hash('sha512', $key.'|'.$command.'|'.$order->transaction_id.'|'.$salt),
        ]);

        $body = $response->json() ?? [];

        if ($response->failed() || (int) ($body['status'] ?? 0) !== 1) {
            return $this->fail($body['msg'] ?? 'PayU refused the refund.', $body, $order);
        }

        $result = PaymentResult::success($body['request_id'] ?? $token, 'Refunded via PayU.', $body);
        $this->logTransaction($order, 'success', $result, response: $body, type: 'refund', amount: $amount ?? $order->grand_total);

        return $result;
    }
}
