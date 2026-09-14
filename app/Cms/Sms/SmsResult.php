<?php

namespace App\Cms\Sms;

/**
 * The outcome of one send attempt, normalised across providers.
 */
final class SmsResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly ?string $reference = null,
        public readonly ?string $error = null,
        public readonly array $raw = [],
    ) {}

    public static function sent(?string $reference = null, array $raw = []): self
    {
        return new self(true, reference: $reference, raw: $raw);
    }

    public static function failed(string $error, array $raw = []): self
    {
        return new self(false, error: $error, raw: $raw);
    }

    public function status(): string
    {
        return $this->successful ? 'sent' : 'failed';
    }
}
