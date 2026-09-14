<?php

namespace App\Cms\Payments\Drivers;

use App\Cms\Payments\PaymentResult;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Direct bank transfer. The customer is shown the account details and the
 * order waits until the site owner confirms the money arrived.
 */
class BankTransferGateway extends AbstractGateway
{
    public function slug(): string
    {
        return 'bank';
    }

    public function isConfigured(): bool
    {
        return filled($this->credential('instructions'));
    }

    public function initiate(Order $order): PaymentResult
    {
        $order->forceFill([
            'payment_status' => 'awaiting',
            'payment_gateway' => $this->slug(),
        ])->save();

        $result = PaymentResult::pending(
            'Please transfer the total using the bank details shown, quoting reference '.$order->order_number.'.',
            $order->order_number
        );

        $this->logTransaction($order, 'pending', $result);

        return $result;
    }

    public function handleReturn(Request $request, Order $order): PaymentResult
    {
        return PaymentResult::pending('Awaiting your bank transfer.', $order->order_number);
    }

    public function instructions(): string
    {
        return (string) $this->credential('instructions');
    }
}
