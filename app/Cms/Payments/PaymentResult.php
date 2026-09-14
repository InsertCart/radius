<?php

namespace App\Cms\Payments;

/**
 * What a gateway hands back to the checkout controller.
 *
 * The six providers reach the customer in four different ways - a redirect, a
 * signed self-submitting form, a JavaScript modal, or no hand-off at all for
 * offline methods. Normalising them into one object keeps the checkout
 * controller free of per-gateway branching.
 */
final class PaymentResult
{
    public const REDIRECT = 'redirect';
    public const FORM = 'form';
    public const CHECKOUT = 'checkout';
    public const SUCCESS = 'success';
    public const PENDING = 'pending';
    public const FAILED = 'failed';

    private function __construct(
        public readonly string $status,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $reference = null,
        public readonly ?string $message = null,
        /** Form fields to POST, or options for the provider's JS widget. */
        public readonly array $data = [],
        /** The provider's raw response, stored on the transaction for support. */
        public readonly array $raw = [],
        public readonly ?int $amount = null,
    ) {}

    /** Send the browser to the provider's hosted page. */
    public static function redirect(string $url, ?string $reference = null, array $raw = []): self
    {
        return new self(self::REDIRECT, redirectUrl: $url, reference: $reference, raw: $raw);
    }

    /** Render a self-submitting form that POSTs to the provider. */
    public static function form(string $action, array $fields, ?string $reference = null): self
    {
        return new self(self::FORM, redirectUrl: $action, reference: $reference, data: $fields);
    }

    /** Hand options to the provider's JavaScript modal on our own page. */
    public static function checkout(array $options, ?string $reference = null, array $raw = []): self
    {
        return new self(self::CHECKOUT, reference: $reference, data: $options, raw: $raw);
    }

    /** Payment is confirmed. */
    public static function success(?string $reference = null, ?string $message = null, array $raw = [], ?int $amount = null): self
    {
        return new self(self::SUCCESS, reference: $reference, message: $message, raw: $raw, amount: $amount);
    }

    /** Accepted but not yet settled - offline transfers, delayed capture. */
    public static function pending(?string $message = null, ?string $reference = null, array $raw = []): self
    {
        return new self(self::PENDING, reference: $reference, message: $message, raw: $raw);
    }

    public static function failed(string $message, array $raw = [], ?string $reference = null): self
    {
        return new self(self::FAILED, reference: $reference, message: $message, raw: $raw);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::SUCCESS;
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::FAILED;
    }

    /** True when the customer still has to be handed off somewhere. */
    public function needsHandoff(): bool
    {
        return in_array($this->status, [self::REDIRECT, self::FORM, self::CHECKOUT], true);
    }

    /** The status to persist on the payment_transactions row. */
    public function transactionStatus(): string
    {
        return match ($this->status) {
            self::SUCCESS => 'success',
            self::FAILED => 'failed',
            default => 'pending',
        };
    }
}
