<?php

namespace App\Http\Middleware\Api;

use App\Cms\Api\ApiManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a group of endpoints the owner may have switched off.
 *
 * Route registration already skips groups that are off, so this is the second
 * lock - for a cached route table written before the toggle changed, and for
 * the case where the group is on but the CMS module behind it has since been
 * disabled.
 *
 * The answer is 404, not 403: an endpoint the owner has not switched on does
 * not exist as far as the caller is concerned.
 */
class EnsureApiFeature
{
    public function __construct(private ApiManager $api) {}

    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        foreach ($features as $feature) {
            if (! $this->api->feature($feature)) {
                return response()->json([
                    'message' => 'This part of the API is not switched on for this site.',
                    'error' => 'feature_disabled',
                ], 404);
            }
        }

        return $next($request);
    }
}
