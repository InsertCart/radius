<?php

namespace App\Http\Middleware;

use App\Cms\Support\PageCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves and stores cached HTML for signed-out visitors. What may be cached,
 * and for whom, is decided in PageCache.
 */
class CachePages
{
    public function __construct(private PageCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->cache->enabled() || ! ($key = $this->cache->keyFor($request))) {
            return $next($request);
        }

        if ($hit = $this->cache->get($key, $request)) {
            return response($hit['content'], 200, [
                'Content-Type' => $hit['type'],
                'X-Page-Cache' => 'HIT',
            ]);
        }

        $response = $next($request);

        $type = (string) $response->headers->get('Content-Type');

        if ($response instanceof IlluminateResponse
            && $response->getStatusCode() === 200
            && str_contains($type, 'text/html')
            && is_string($response->getContent())
            // The page may have started a flash or a cart while rendering.
            && $this->cache->keyFor($request) === $key) {
            $this->cache->put($key, $response->getContent(), $type, $request);
            $response->headers->set('X-Page-Cache', 'MISS');
        }

        return $response;
    }
}
