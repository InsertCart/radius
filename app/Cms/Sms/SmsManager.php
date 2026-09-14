<?php

namespace App\Cms\Sms;

use App\Cms\Sms\Contracts\SmsDriverContract;
use App\Cms\Sms\Drivers\LogSmsDriver;
use App\Cms\Sms\Drivers\Msg91Driver;
use App\Cms\Sms\Drivers\TwilioDriver;
use App\Models\OtpCode;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Sends transactional SMS through whichever provider the site owner selected,
 * and owns the one-time-code flow built on top of it.
 */
class SmsManager
{
    private const DRIVERS = [
        'msg91' => Msg91Driver::class,
        'twilio' => TwilioDriver::class,
        'log' => LogSmsDriver::class,
    ];

    private ?SmsDriverContract $driver = null;

    public function driver(?string $name = null): SmsDriverContract
    {
        // Only the configured default is memoised; an explicitly named driver
        // is built fresh so the admin "send test" screen can try any provider.
        $isDefault = $name === null;

        if ($isDefault && $this->driver !== null) {
            return $this->driver;
        }

        $name ??= (string) setting('sms_driver', 'msg91');
        $class = self::DRIVERS[$name] ?? LogSmsDriver::class;

        $driver = new $class;

        return $isDefault ? ($this->driver = $driver) : $driver;
    }

    public function isEnabled(): bool
    {
        return modules()->enabled('sms')
            && setting('sms_enabled', false)
            && $this->driver()->isConfigured();
    }

    /**
     * Send a message and record the attempt. Never throws: a failed SMS should
     * not roll back the order or registration that triggered it.
     */
    public function send(string $to, string $message, array $options = []): SmsResult
    {
        $to = $this->normalise($to);

        if (blank($to)) {
            return SmsResult::failed('No valid destination number.');
        }

        if (! $this->isEnabled()) {
            return SmsResult::failed('SMS is not enabled or not configured.');
        }

        try {
            $result = $this->driver()->send($to, $message, $options);
        } catch (\Throwable $e) {
            report($e);
            $result = SmsResult::failed($e->getMessage());
        }

        SmsLog::create([
            'driver' => setting('sms_driver', 'log'),
            'to' => $to,
            'message' => $message,
            'status' => $result->status(),
            'provider_reference' => $result->reference,
            'error' => $result->error,
            'response' => $result->raw ?: null,
        ]);

        return $result;
    }

    // One-time codes ------------------------------------------------------

    /**
     * Issue an OTP for a phone number. Rate limited per number so the endpoint
     * cannot be used to run up someone's SMS bill or spam a stranger.
     *
     * @return array{sent: bool, message: string}
     */
    public function sendOtp(string $phone, string $purpose = 'login', int $ttlMinutes = 10): array
    {
        $phone = $this->normalise($phone);
        $key = 'otp:'.sha1($phone);

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);

            return ['sent' => false, 'message' => "Please wait {$seconds} seconds before requesting another code."];
        }

        RateLimiter::hit($key, 600);

        // Any earlier unused code for this number stops working, so an
        // intercepted older SMS is worthless.
        OtpCode::where('identifier', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'identifier' => $phone,
            'channel' => 'sms',
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        $siteName = setting('site_name', config('app.name'));
        $result = $this->send($phone, "{$code} is your verification code for {$siteName}. It expires in {$ttlMinutes} minutes.", [
            'otp' => $code,
        ]);

        return [
            'sent' => $result->successful,
            'message' => $result->successful
                ? 'We sent a verification code to your phone.'
                : ($result->error ?? 'Could not send the verification code.'),
        ];
    }

    /**
     * Check a submitted code. Attempts are counted so a six-digit code cannot
     * be brute-forced within its lifetime.
     */
    public function verifyOtp(string $phone, string $code, string $purpose = 'login'): bool
    {
        $record = OtpCode::where('identifier', $this->normalise($phone))
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $record || $record->isExpired()) {
            return false;
        }

        if ($record->attempts >= 5) {
            $record->consume();

            return false;
        }

        $record->increment('attempts');

        if (! $record->matches($code)) {
            return false;
        }

        $record->consume();

        return true;
    }

    /**
     * Best-effort E.164 normalisation. Numbers already carrying a country code
     * pass through; bare national numbers are left alone for the provider to
     * interpret, since guessing a country here would be worse than not.
     */
    public function normalise(string $phone): string
    {
        $digits = preg_replace('/[^\d+]/', '', $phone);

        if (blank($digits)) {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }

        return $digits;
    }

    /** Available drivers with their configuration state, for the admin UI. */
    public function catalogue(): array
    {
        $catalogue = [];

        foreach (self::DRIVERS as $slug => $class) {
            $driver = new $class;

            $catalogue[$slug] = [
                'slug' => $slug,
                'name' => $driver->name(),
                'configured' => $driver->isConfigured(),
                'active' => setting('sms_driver') === $slug,
            ];
        }

        return $catalogue;
    }
}
