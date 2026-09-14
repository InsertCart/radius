<?php

namespace App\Cms\Sms\Drivers;

use App\Cms\Sms\Contracts\SmsDriverContract;
use App\Cms\Sms\SmsResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes messages to the log instead of sending them. Lets a buyer exercise
 * the whole OTP flow before signing up with a provider.
 */
class LogSmsDriver implements SmsDriverContract
{
    public function name(): string
    {
        return 'Log only (testing)';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(string $to, string $message, array $options = []): SmsResult
    {
        Log::channel(config('logging.default'))->info("[sms] to {$to}: {$message}");

        return SmsResult::sent('log-'.Str::random(12));
    }
}
