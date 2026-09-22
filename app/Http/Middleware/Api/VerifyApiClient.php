<?php

namespace App\Http\Middleware\Api;

use App\Cms\Api\ApiManager;
use App\Cms\Api\RequestSigner;
use App\Models\ApiClient;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The front door. Nothing gets past here without naming a registered app and
 * proving it holds that app's secret.
 *
 * This is the answer to "can somebody use the API just by knowing the URL?".
 * They cannot: an unsigned, uncredentialled request gets 401 from here,
 * before any route, controller or model is touched. There is no public mode
 * and no endpoint that skips this middleware - not even the ones that need no
 * customer sign-in, like the product list.
 *
 * It also explains why these routes carry no CSRF token and start no session.
 * A browser cannot authenticate here by accident: credentials arrive in
 * headers an app sets deliberately, never in a cookie the browser attaches on
 * its own, so there is nothing for a cross-site request to ride on.
 */
class VerifyApiClient
{
    public function __construct(
        private ApiManager $api,
        private RequestSigner $signer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Routes are only registered while the module is on; this is the
        // second lock, for a cached route table that has not caught up yet.
        if (! $this->api->enabled()) {
            return $this->refuse('This site does not have an API.', 404, 'api_disabled');
        }

        $key = $this->presentedKey($request);

        if ($key === '') {
            return $this->refuse('Send your app key in the X-Api-Key header.', 401, 'missing_key');
        }

        $client = ApiClient::where('client_id', $key)->first();

        if (! $client) {
            return $this->refuse('That app key is not registered on this site.', 401, 'unknown_key');
        }

        if (! $client->enabled) {
            return $this->refuse('This app has been switched off by the site owner.', 403, 'app_disabled');
        }

        if ($problem = $this->credentialProblem($request, $client)) {
            return $this->refuse($problem, 401, 'bad_credentials');
        }

        $client->touchUsage($request->ip());

        // Handed to the controllers: the token a sign-in issues belongs to
        // the app that asked for it, and only that app can refresh it.
        $request->attributes->set('api_client', $client);

        return $next($request);
    }

    /**
     * Either a matching secret, or a valid signature - whichever the site
     * owner has asked for.
     */
    private function credentialProblem(Request $request, ApiClient $client): ?string
    {
        if ($this->api->signatureRequired()) {
            return $this->signer->problemWith($request, $client);
        }

        $secret = (string) $request->header('X-Api-Secret', '');

        // A signature is accepted even when it is not demanded, so an app can
        // be written the careful way on a site configured the simple way.
        if ($secret === '' && $request->hasHeader('X-Api-Signature')) {
            return $this->signer->problemWith($request, $client);
        }

        if ($secret === '') {
            return 'Send your app secret in the X-Api-Secret header, or sign the request.';
        }

        return $client->secretMatches($secret)
            ? null
            : 'That app secret is not correct.';
    }

    /** The key header, with the Basic-auth form accepted as well. */
    private function presentedKey(Request $request): string
    {
        $key = trim((string) $request->header('X-Api-Key', ''));

        if ($key !== '') {
            return $key;
        }

        // Some HTTP clients make it easier to send Basic credentials than
        // custom headers, so key:secret is read from there too.
        $user = (string) $request->getUser();

        if ($user !== '' && ! $request->hasHeader('X-Api-Secret')) {
            $request->headers->set('X-Api-Secret', (string) $request->getPassword());
        }

        return trim($user);
    }

    private function refuse(string $message, int $status, string $code): JsonResponse
    {
        return response()->json(['message' => $message, 'error' => $code], $status);
    }
}
