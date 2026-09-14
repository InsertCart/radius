<?php

namespace App\Cms\Payments\Contracts;

use App\Cms\Payments\PaymentResult;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Every payment driver implements this. Adding a new provider means writing
 * one class against this interface and one entry in config/payments.php.
 */
interface PaymentGatewayContract
{
    /** Machine name, matching the key in config/payments.php. */
    public function slug(): string;

    /**
     * Start a payment. Returns whatever hand-off the provider needs: a
     * redirect, a signed form, a JS modal config, or an immediate result for
     * offline methods.
     */
    public function initiate(Order $order): PaymentResult;

    /**
     * Handle the customer coming back from the provider. Must confirm the
     * payment against the provider's API rather than trusting the returned
     * parameters, which the customer's browser could have tampered with.
     */
    public function handleReturn(Request $request, Order $order): PaymentResult;

    /**
     * Handle a server-to-server webhook. Implementations must verify the
     * signature before acting, and return null when the event is not one we
     * care about.
     */
    public function handleWebhook(Request $request): ?PaymentResult;

    /** Refund all or part of an order. Amount is in minor units. */
    public function refund(Order $order, ?int $amount = null): PaymentResult;

    /** Whether this driver has enough configuration to be offered at checkout. */
    public function isConfigured(): bool;
}
