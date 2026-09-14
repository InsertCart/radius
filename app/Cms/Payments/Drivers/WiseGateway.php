<?php

namespace App\Cms\Payments\Drivers;

use App\Cms\Payments\PaymentResult;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Wise (formerly TransferWise).
 *
 * Wise is a payouts and multi-currency banking platform, not a card
 * acquirer - there is no hosted checkout that charges a customer on our
 * behalf. So this driver treats it as a bank transfer: the customer is shown
 * the account details, the order is parked as awaiting payment, and the
 * incoming transfer is reconciled afterwards.
 *
 * When an API token is supplied, the driver can look up recent incoming
 * transfers and match one to the order reference, which turns the manual
 * "did they pay?" check into one click in the admin panel.
 */
class WiseGateway extends AbstractGateway
{
    public function slug(): string
    {
        return 'wise';
    }

    public function isConfigured(): bool
    {
        // Only the details shown to the customer are strictly required; the
        // API token is an optional convenience.
        return filled($this->credential('account_details'));
    }

    public function initiate(Order $order): PaymentResult
    {
        $order->forceFill([
            'payment_status' => 'awaiting',
            'payment_gateway' => $this->slug(),
        ])->save();

        $result = PaymentResult::pending(
            'Please transfer the total to the account shown on your order confirmation, quoting reference '
            .$order->order_number.'. Your order ships once the transfer arrives.',
            $order->order_number
        );

        $this->logTransaction($order, 'pending', $result);

        return $result;
    }

    public function handleReturn(Request $request, Order $order): PaymentResult
    {
        // Nothing to verify: the customer never left the site.
        return PaymentResult::pending('Awaiting your bank transfer.', $order->order_number);
    }

    /** The bank details rendered on the confirmation page and in the email. */
    public function instructions(): string
    {
        $lines = array_filter([
            $this->credential('account_holder') ? 'Account holder: '.$this->credential('account_holder') : null,
            (string) $this->credential('account_details'),
        ]);

        return implode("\n", $lines);
    }

    /**
     * Looks for an incoming Wise transfer matching this order's reference and
     * amount. Returns a success result on a match, otherwise stays pending.
     *
     * Called from the admin panel, never automatically: matching money to
     * orders is a decision a human should confirm.
     */
    public function reconcile(Order $order): PaymentResult
    {
        $token = $this->credential('api_token');
        $profileId = $this->credential('profile_id');

        if (blank($token) || blank($profileId)) {
            return PaymentResult::pending('Add a Wise API token and profile ID to reconcile transfers automatically.');
        }

        $response = $this->http()
            ->withToken($token)
            ->get($this->endpoint().'/v1/transfers', [
                'profile' => $profileId,
                'status' => 'outgoing_payment_sent',
                'limit' => 100,
            ]);

        if ($response->failed()) {
            return PaymentResult::failed('Could not reach the Wise API: '.$response->status());
        }

        foreach ($response->json() ?? [] as $transfer) {
            $reference = (string) data_get($transfer, 'details.reference', '');

            if (! str_contains(strtoupper($reference), strtoupper($order->order_number))) {
                continue;
            }

            $amount = to_minor_units(data_get($transfer, 'targetValue', 0));

            if ($amount < $order->grand_total) {
                return PaymentResult::pending(
                    'A transfer was found for '.money($amount).' but the order total is '.money($order->grand_total).'.'
                );
            }

            $result = PaymentResult::success((string) $transfer['id'], 'Matched a Wise transfer.', $transfer, $amount);
            $this->logTransaction($order, 'success', $result, response: $transfer);

            return $result;
        }

        return PaymentResult::pending('No matching Wise transfer found yet.');
    }
}
