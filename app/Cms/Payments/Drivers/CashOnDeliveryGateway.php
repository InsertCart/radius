<?php

namespace App\Cms\Payments\Drivers;

use App\Cms\Payments\PaymentResult;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Cash on delivery. No provider is involved: the order is confirmed straight
 * away and left unpaid until someone marks it collected in the admin panel.
 */
class CashOnDeliveryGateway extends AbstractGateway
{
    public function slug(): string
    {
        return 'cod';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function initiate(Order $order): PaymentResult
    {
        $order->forceFill([
            'payment_status' => 'awaiting',
            'payment_gateway' => $this->slug(),
            'status' => 'processing',
        ])->save();

        $result = PaymentResult::pending(
            $this->credential('instructions') ?: 'Pay in cash when your order is delivered.',
            $order->order_number
        );

        $this->logTransaction($order, 'pending', $result);

        return $result;
    }

    public function handleReturn(Request $request, Order $order): PaymentResult
    {
        return PaymentResult::pending('Payment is collected on delivery.', $order->order_number);
    }

    /** Shown on the order confirmation and in the confirmation email. */
    public function instructions(): string
    {
        return (string) $this->credential('instructions', 'Pay in cash when your order is delivered.');
    }

    /** An optional surcharge, in minor units, added at checkout. */
    public function extraFee(): int
    {
        return to_minor_units($this->credential('extra_fee', 0));
    }
}
