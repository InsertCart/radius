<?php

namespace App\Http\Middleware\Api;

use App\Cms\Api\ApiManager;
use App\Cms\Api\TokenIssuer;
use App\Models\ApiClient;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the customer behind a bearer token.
 *
 *     ->middleware('api.auth')            a customer is required
 *     ->middleware('api.auth:optional')   a guest is allowed through
 *
 * The user is set on the guard for this request only - never logged in, so no
 * session is written and no cookie goes back. Everything downstream that asks
 * auth()->user() (the cart, the order service, the account screens' worth of
 * logic these endpoints reuse) therefore works unchanged, and nothing about
 * this request survives it.
 *
 * Staff accounts are refused unless the owner has deliberately allowed them.
 * The API serves the storefront; an admin session does not belong on it.
 */
class AuthenticateApiToken
{
    public function __construct(
        private TokenIssuer $tokens,
        private ApiManager $api,
    ) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $optional = $mode === 'optional';
        $presented = $this->bearer($request);

        if ($presented === '') {
            return $optional
                ? $next($request)
                : $this->refuse('Sign in first: send the access token as a bearer token.', 401, 'not_signed_in');
        }

        $token = $this->tokens->find($presented);

        if (! $token) {
            // Deliberately the same answer for expired, revoked and never
            // existed: the app's move is identical in all three cases, and
            // there is nothing to learn from the difference.
            return $this->refuse('That session has expired. Sign in again.', 401, 'invalid_token');
        }

        // A token is bound to the app it was issued through. Another app's
        // credentials, however valid, cannot carry it.
        $client = $request->attributes->get('api_client');

        if ($client instanceof ApiClient && $token->api_client_id !== $client->id) {
            return $this->refuse('That session belongs to a different app.', 401, 'wrong_app');
        }

        $user = $token->user;

        if (! $user || ! $user->isActive()) {
            $token->revoke();

            return $this->refuse('This account is no longer active.', 403, 'account_inactive');
        }

        if ($user->isStaff() && ! $this->api->allowsStaff()) {
            $token->revoke();

            return $this->refuse('Staff accounts cannot be used through the API.', 403, 'staff_blocked');
        }

        $token->touchUsage($request->ip());

        // For this request only. Auth::login() would start a session; this
        // does not, which is what keeps the API stateless.
        Auth::setUser($user);

        $request->attributes->set('api_token', $token);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function bearer(Request $request): string
    {
        $header = (string) $request->header('Authorization', '');

        if (stripos($header, 'bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return '';
    }

    private function refuse(string $message, int $status, string $code): JsonResponse
    {
        return response()->json(['message' => $message, 'error' => $code], $status);
    }
}
