<?php

namespace App\Cms\Api;

use App\Models\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Checks the signature on a request, when the site owner has asked for signed
 * requests.
 *
 * Sending the secret in a header is fine over TLS and is what most apps do.
 * Signing is better, and this is what it buys: the secret itself never leaves
 * the device, a captured request cannot be replayed once its window closes,
 * and nothing in the request - not the path, not the query, not the body -
 * can be altered on the way without the signature failing.
 *
 * The string an app signs is exactly this, newline separated:
 *
 *     <client id>
 *     <HTTP method, upper case>
 *     /<path, no leading slash duplicated, no query>
 *     <query string, exactly as sent, or empty>
 *     <unix timestamp>
 *     <nonce>
 *     <sha256 of the raw body, hex, lower case; of "" for an empty body>
 *
 * signed with HMAC-SHA256 under the app's secret and sent base64 encoded in
 * X-Api-Signature, alongside X-Api-Timestamp and X-Api-Nonce.
 */
class RequestSigner
{
    public function __construct(private ApiManager $api) {}

    /**
     * Why this request's signature is not acceptable, or null when it is.
     *
     * Returns a reason rather than a boolean so the caller can say something
     * useful to whoever is writing the app - these are the errors people hit
     * on day one, and "401" on its own costs an afternoon.
     */
    public function problemWith(Request $request, ApiClient $client): ?string
    {
        $signature = (string) $request->header('X-Api-Signature', '');
        $timestamp = (string) $request->header('X-Api-Timestamp', '');
        $nonce = (string) $request->header('X-Api-Nonce', '');

        if ($signature === '' || $timestamp === '' || $nonce === '') {
            return 'This site requires signed requests. Send X-Api-Signature, X-Api-Timestamp and X-Api-Nonce.';
        }

        if (! ctype_digit($timestamp)) {
            return 'X-Api-Timestamp must be a unix timestamp in seconds.';
        }

        $window = $this->api->signatureWindow();

        if (abs(time() - (int) $timestamp) > $window) {
            return "The request is outside the {$window} second signing window. Check the device clock.";
        }

        // A nonce may be used once inside the window. Anything replaying a
        // captured request lands here, however perfect its signature.
        if (strlen($nonce) < 8 || strlen($nonce) > 128) {
            return 'X-Api-Nonce must be between 8 and 128 characters.';
        }

        if (! $this->claimNonce($client, $nonce, $window)) {
            return 'This request has already been used.';
        }

        $expected = $this->sign($this->canonical($request, $client), $client->signingSecret());

        if (! hash_equals($expected, $signature)) {
            return 'The signature does not match the request.';
        }

        return null;
    }

    /** The exact string a client has to sign. */
    public function canonical(Request $request, ApiClient $client): string
    {
        return implode("\n", [
            $client->client_id,
            strtoupper($request->method()),
            '/'.ltrim($request->path(), '/'),
            (string) $request->server('QUERY_STRING', ''),
            (string) $request->header('X-Api-Timestamp', ''),
            (string) $request->header('X-Api-Nonce', ''),
            hash('sha256', (string) $request->getContent()),
        ]);
    }

    public function sign(string $canonical, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $canonical, $secret, true));
    }

    /**
     * Take the nonce, if nobody else has.
     *
     * Cache::add is atomic, so two copies of the same captured request racing
     * each other cannot both win. It is only remembered for the signing
     * window, because outside that the timestamp check has already refused it.
     */
    private function claimNonce(ApiClient $client, string $nonce, int $window): bool
    {
        $key = 'api.nonce:'.$client->client_id.':'.hash('sha256', $nonce);

        return Cache::add($key, true, $window + 5);
    }
}
