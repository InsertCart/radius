<?php

namespace App\Cms\Sms\Drivers;

use App\Cms\Sms\Contracts\SmsDriverContract;
use App\Cms\Sms\SmsResult;
use Illuminate\Support\Facades\Http;

/**
 * Twilio, via the Messages REST resource.
 */
class TwilioDriver implements SmsDriverContract
{
    public function name(): string
    {
        return 'Twilio';
    }

    public function isConfigured(): bool
    {
        return filled(setting('twilio_sid'))
            && filled(setting('twilio_token'))
            && filled(setting('twilio_from'));
    }

    public function send(string $to, string $message, array $options = []): SmsResult
    {
        $sid = (string) setting('twilio_sid');

        $response = Http::timeout(20)
            ->withBasicAuth($sid, (string) setting('twilio_token'))
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $to,
                'From' => setting('twilio_from'),
                'Body' => $message,
            ]);

        $body = $response->json() ?? [];

        if ($response->failed()) {
            return SmsResult::failed($body['message'] ?? 'Twilio rejected the message.', $body);
        }

        // Twilio accepts the message before the carrier confirms delivery;
        // 'queued' or 'sent' both mean it left successfully.
        if (in_array($body['status'] ?? '', ['failed', 'undelivered'], true)) {
            return SmsResult::failed($body['error_message'] ?? 'Twilio could not deliver the message.', $body);
        }

        return SmsResult::sent($body['sid'] ?? null, $body);
    }
}
