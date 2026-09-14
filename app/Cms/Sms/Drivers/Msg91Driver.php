<?php

namespace App\Cms\Sms\Drivers;

use App\Cms\Sms\Contracts\SmsDriverContract;
use App\Cms\Sms\SmsResult;
use Illuminate\Support\Facades\Http;

/**
 * MSG91, via the v5 Flow API.
 *
 * Indian carriers require every template to be pre-registered under DLT, so
 * the DLT template id is sent with each request when one is configured.
 */
class Msg91Driver implements SmsDriverContract
{
    private const ENDPOINT = 'https://control.msg91.com/api/v5';

    public function name(): string
    {
        return 'MSG91';
    }

    public function isConfigured(): bool
    {
        return filled(setting('msg91_auth_key'));
    }

    public function send(string $to, string $message, array $options = []): SmsResult
    {
        $authKey = (string) setting('msg91_auth_key');
        $templateId = $options['template_id'] ?? setting('msg91_dlt_te_id');

        // The Flow API is the supported path when a DLT template exists; the
        // plain SMS endpoint is the fallback for accounts without one.
        $response = filled($templateId)
            ? $this->sendViaFlow($authKey, $to, $templateId, $message, $options)
            : $this->sendViaSms($authKey, $to, $message);

        $body = $response->json() ?? [];

        if ($response->failed() || strtolower((string) ($body['type'] ?? 'success')) === 'error') {
            return SmsResult::failed(
                $body['message'] ?? 'MSG91 rejected the message.',
                $body
            );
        }

        return SmsResult::sent($body['request_id'] ?? ($body['message'] ?? null), $body);
    }

    private function sendViaFlow(string $authKey, string $to, string $templateId, string $message, array $options)
    {
        // Variables named in the DLT template. 'otp' is the near-universal
        // convention, so it is passed through when present.
        $recipient = array_merge(
            ['mobiles' => ltrim($to, '+')],
            array_filter(['otp' => $options['otp'] ?? null, 'message' => $message])
        );

        return Http::timeout(20)
            ->withHeaders(['authkey' => $authKey])
            ->acceptJson()
            ->post(self::ENDPOINT.'/flow/', [
                'template_id' => $templateId,
                'sender' => setting('msg91_sender_id'),
                'short_url' => '0',
                'recipients' => [$recipient],
            ]);
    }

    private function sendViaSms(string $authKey, string $to, string $message)
    {
        return Http::timeout(20)
            ->withHeaders(['authkey' => $authKey])
            ->acceptJson()
            ->get('https://api.msg91.com/api/sendhttp.php', [
                'authkey' => $authKey,
                'mobiles' => ltrim($to, '+'),
                'message' => $message,
                'sender' => setting('msg91_sender_id', 'MSGIND'),
                'route' => setting('msg91_route', '4'),
                'response' => 'json',
            ]);
    }
}
