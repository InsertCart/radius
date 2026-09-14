<?php

namespace App\Cms\Sms\Contracts;

use App\Cms\Sms\SmsResult;

/**
 * Every SMS provider implements this. Adding a provider means one class plus
 * one entry in the sms_driver setting's options.
 */
interface SmsDriverContract
{
    public function name(): string;

    /** Send one message. $to should already be in E.164 form. */
    public function send(string $to, string $message, array $options = []): SmsResult;

    /** Whether the provider has enough configuration to send. */
    public function isConfigured(): bool;
}
