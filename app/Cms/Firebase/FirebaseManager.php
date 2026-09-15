<?php

namespace App\Cms\Firebase;

use App\Models\PushDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase integration: client SDK config for the browser, and server-side
 * push through Cloud Messaging v1.
 *
 * FCM v1 needs an OAuth token signed with the service account's private key.
 * That is a plain RS256 JWT, which openssl_sign can produce directly - so the
 * whole google/apiclient dependency tree is avoided for what amounts to
 * thirty lines of signing code.
 */
class FirebaseManager
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function isEnabled(): bool
    {
        return modules()->enabled('firebase') && setting('firebase_enabled', false);
    }

    /**
     * The config object handed to the Firebase JS SDK. Every value here is
     * public by design - Firebase security rules, not secrecy, protect the
     * project.
     */
    public function clientConfig(): array
    {
        return array_filter([
            'apiKey' => setting('firebase_api_key'),
            'authDomain' => setting('firebase_auth_domain'),
            'projectId' => setting('firebase_project_id'),
            'storageBucket' => setting('firebase_storage_bucket'),
            'messagingSenderId' => setting('firebase_messaging_sender_id'),
            'appId' => setting('firebase_app_id'),
            'measurementId' => setting('firebase_measurement_id'),
        ]);
    }

    public function isClientConfigured(): bool
    {
        $config = $this->clientConfig();

        return filled($config['apiKey'] ?? null) && filled($config['projectId'] ?? null);
    }

    public function vapidKey(): ?string
    {
        return setting('firebase_vapid_key') ?: null;
    }

    // Service account -----------------------------------------------------

    public function credentialsPath(): string
    {
        return env('FIREBASE_CREDENTIALS') ?: storage_path('app/firebase/service-account.json');
    }

    public function hasServiceAccount(): bool
    {
        return is_file($this->credentialsPath());
    }

    private function serviceAccount(): ?array
    {
        if (! $this->hasServiceAccount()) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->credentialsPath()), true);

        return is_array($data) && isset($data['client_email'], $data['private_key']) ? $data : null;
    }

    /**
     * Exchanges a self-signed JWT for a short-lived access token, cached just
     * inside its one-hour lifetime.
     */
    private function accessToken(): ?string
    {
        $account = $this->serviceAccount();

        if (! $account) {
            return null;
        }

        return Cache::remember('firebase.access_token', now()->addMinutes(50), function () use ($account) {
            $jwt = $this->signJwt($account);

            if (blank($jwt)) {
                return null;
            }

            $response = Http::timeout(20)->asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->failed()) {
                Log::warning('[firebase] Could not obtain an access token.', ['body' => $response->body()]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    private function signJwt(array $account): ?string
    {
        $now = time();

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $account['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $payload = $this->base64Url(json_encode($header)).'.'.$this->base64Url(json_encode($claims));
        $signature = '';

        if (! openssl_sign($payload, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
            Log::warning('[firebase] Could not sign the service-account JWT. Check the private key.');

            return null;
        }

        return $payload.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    // Sending -------------------------------------------------------------

    /**
     * Send a notification to one registration token.
     *
     * @return bool True when FCM accepted the message.
     */
    public function sendToToken(string $token, string $title, string $body, array $data = [], ?string $link = null): bool
    {
        $projectId = setting('firebase_project_id');
        $accessToken = $this->accessToken();

        if (blank($projectId) || blank($accessToken)) {
            return false;
        }

        $message = [
            'message' => array_filter([
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                // FCM requires every data value to be a string.
                'data' => array_map('strval', $data) ?: null,
                'webpush' => array_filter([
                    'notification' => array_filter([
                        'title' => $title,
                        'body' => $body,
                        // site_favicon_url() resolves against the media disk;
                        // url() did not, so an uploaded favicon produced an
                        // address that 404'd. Falls back to the shipped PNG.
                        'icon' => filled(setting('site_favicon'))
                            ? site_favicon_url()
                            : brand_asset('push_icon'),
                    ]),
                    'fcm_options' => $link ? ['link' => $link] : null,
                ]) ?: null,
            ]),
        ];

        $response = Http::timeout(20)
            ->withToken($accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $message);

        if ($response->failed()) {
            // 404 UNREGISTERED means the browser revoked the token; drop it so
            // the list does not fill with dead subscriptions.
            if ($response->status() === 404) {
                PushDevice::where('token_hash', hash('sha256', $token))->delete();
            }

            Log::warning('[firebase] Push failed.', ['status' => $response->status(), 'body' => $response->json()]);

            return false;
        }

        return true;
    }

    /**
     * Send to many devices. FCM v1 has no true multicast, so this loops; for
     * large audiences a queued job should call this in batches.
     *
     * @return array{sent: int, failed: int}
     */
    public function sendToDevices(iterable $devices, string $title, string $body, array $data = [], ?string $link = null): array
    {
        $sent = 0;
        $failed = 0;

        foreach ($devices as $device) {
            $token = $device instanceof PushDevice ? $device->token : (string) $device;

            $this->sendToToken($token, $title, $body, $data, $link) ? $sent++ : $failed++;
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /** Send to every registered device. */
    public function broadcast(string $title, string $body, array $data = [], ?string $link = null): array
    {
        return $this->sendToDevices(PushDevice::cursor(), $title, $body, $data, $link);
    }

    /** Register (or refresh) a browser's push token. */
    public function registerDevice(string $token, ?int $userId = null, string $platform = 'web'): PushDevice
    {
        return PushDevice::updateOrCreate(
            ['token_hash' => hash('sha256', $token)],
            [
                'token' => $token,
                'user_id' => $userId,
                'platform' => $platform,
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
                'last_used_at' => now(),
            ]
        );
    }

    /** What the admin panel shows about the current Firebase setup. */
    public function status(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'client_configured' => $this->isClientConfigured(),
            'service_account' => $this->hasServiceAccount(),
            'credentials_path' => $this->credentialsPath(),
            'vapid_key' => filled($this->vapidKey()),
            'devices' => PushDevice::count(),
        ];
    }
}
