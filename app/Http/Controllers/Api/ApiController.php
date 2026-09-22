<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What every API controller shares: the envelope, and the two things the
 * middleware left behind.
 *
 * Successful answers are {"data": ...}, optionally with {"meta": ...} beside
 * it. Failures are {"message": ..., "error": "<code>"}, which is the shape
 * Laravel's own validation errors already take, so an app has one thing to
 * parse rather than two.
 */
abstract class ApiController extends Controller
{
    /** The app this request came from. Guaranteed by VerifyApiClient. */
    protected function client(Request $request): ApiClient
    {
        return $request->attributes->get('api_client');
    }

    /** The token this request carried, if it was signed in. */
    protected function token(Request $request): ?ApiToken
    {
        $token = $request->attributes->get('api_token');

        return $token instanceof ApiToken ? $token : null;
    }

    protected function user(Request $request): ?User
    {
        return $request->user();
    }

    protected function data(mixed $data, array $extra = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data] + $extra, $status);
    }

    /** A paginated payload already shaped by Resource::paginated(). */
    protected function page(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    protected function message(string $message, array $extra = [], int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message] + $extra, $status);
    }

    protected function fail(string $message, int $status = 422, string $code = 'error'): JsonResponse
    {
        return response()->json(['message' => $message, 'error' => $code], $status);
    }

    /**
     * How many items a list returns.
     *
     * Capped whatever the caller asks for: an unbounded per_page is how one
     * request turns into the whole products table.
     */
    protected function perPage(Request $request, int $max = 50): int
    {
        $requested = (int) $request->input('per_page', setting('posts_per_page', 12));

        return max(1, min($requested, $max));
    }
}
