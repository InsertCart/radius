<?php

namespace App\Cms\Payments\Drivers;

use App\Cms\Payments\Contracts\PaymentGatewayContract;
use App\Cms\Payments\PaymentResult;
use App\Models\Order;
use App\Models\PaymentGateway as GatewayModel;
use App\Models\PaymentTransaction;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Shared plumbing for every driver: credential access, a configured HTTP
 * client, transaction logging and consistent error handling.
 *
 * Drivers talk to their providers over REST rather than through vendor SDKs.
 * Six SDKs would mean six sets of transitive dependencies to keep in step, and
 * a buyer on shared hosting can be left unable to run composer at all. The
 * REST surface these providers expose is small and stable.
 */
abstract class AbstractGateway implements PaymentGatewayContract
{
    public function __construct(protected GatewayModel $model) {}

    abstract public function slug(): string;

    // Configuration -------------------------------------------------------

    protected function credential(string $key, mixed $default = null): mixed
    {
        return $this->model->credential($key, $default);
    }

    protected function isLive(): bool
    {
        return $this->model->isLive();
    }

    /**
     * Endpoint for the current mode. Entries in config are either a plain
     * string or a live/test pair.
     */
    protected function endpoint(?string $key = null): string
    {
        $endpoint = config('payments.endpoints.'.($key ?? $this->slug()));

        if (is_array($endpoint)) {
            return $endpoint[$this->isLive() ? 'live' : 'test'];
        }

        return (string) $endpoint;
    }

    public function isConfigured(): bool
    {
        return $this->model->isConfigured();
    }

    // HTTP ----------------------------------------------------------------

    protected function http(): PendingRequest
    {
        return Http::timeout(config('payments.http.timeout', 30))
            ->retry(
                config('payments.http.retries', 2),
                config('payments.http.retry_delay_ms', 400),
                // Never retry a request the provider actively rejected: a 4xx
                // means our request was wrong, and retrying a charge risks
                // taking the customer's money twice.
                throw: false
            )
            ->acceptJson();
    }

    // URLs ----------------------------------------------------------------

    protected function returnUrl(Order $order): string
    {
        return route('checkout.return', ['gateway' => $this->slug(), 'order' => $order->order_number]);
    }

    protected function cancelUrl(Order $order): string
    {
        return route('checkout.cancel', ['gateway' => $this->slug(), 'order' => $order->order_number]);
    }

    protected function webhookUrl(): string
    {
        return route('checkout.webhook', ['gateway' => $this->slug()]);
    }

    // Amounts -------------------------------------------------------------

    /** Minor units to the decimal string most REST APIs expect ("19.99"). */
    protected function toDecimal(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, '.', '');
    }

    // Transaction logging -------------------------------------------------

    /** Our own reference, sent to the provider so we can match a webhook back. */
    protected function makeReference(Order $order): string
    {
        return $order->order_number.'-'.Str::lower(Str::random(6));
    }

    protected function logTransaction(
        ?Order $order,
        string $status,
        PaymentResult|string|null $result = null,
        array $request = [],
        array $response = [],
        string $type = 'payment',
        ?int $amount = null,
    ): PaymentTransaction {
        return PaymentTransaction::create([
            'order_id' => $order?->id,
            'gateway' => $this->slug(),
            'type' => $type,
            'reference' => $order?->order_number,
            'gateway_reference' => $result instanceof PaymentResult ? $result->reference : null,
            'amount' => $amount ?? $order?->grand_total ?? 0,
            'currency' => $order?->currency ?? setting('shop_currency', 'USD'),
            'status' => $status,
            'message' => $result instanceof PaymentResult ? $result->message : $result,
            'request_payload' => $this->redact($request) ?: null,
            'response_payload' => $this->redact($response) ?: null,
        ]);
    }

    /**
     * Strips credentials out of anything destined for the database. These
     * payloads are shown in the admin panel, so they must not leak secrets.
     */
    protected function redact(array $payload): array
    {
        $sensitive = ['key_secret', 'secret_key', 'client_secret', 'api_token', 'token',
            'authorization', 'auth_key', 'salt', 'merchant_salt', 'password', 'signature'];

        array_walk_recursive($payload, function (&$value, $key) use ($sensitive) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $value = '[redacted]';
            }
        });

        return $payload;
    }

    protected function fail(string $message, array $context = [], ?Order $order = null): PaymentResult
    {
        Log::warning("[payments:{$this->slug()}] {$message}", $context);

        $result = PaymentResult::failed($message, $context);
        $this->logTransaction($order, 'failed', $result, response: $context);

        return $result;
    }

    // Defaults ------------------------------------------------------------

    public function handleWebhook(Request $request): ?PaymentResult
    {
        return null;
    }

    public function refund(Order $order, ?int $amount = null): PaymentResult
    {
        return PaymentResult::failed(
            $this->model->name.' does not support refunds from the admin panel. Refund it in your provider dashboard, then mark the order refunded here.'
        );
    }

    /**
     * Constant-time comparison for webhook signatures. Using == here would
     * leak the expected signature a byte at a time through timing.
     */
    protected function signatureMatches(string $expected, string $received): bool
    {
        return hash_equals($expected, $received);
    }
}
