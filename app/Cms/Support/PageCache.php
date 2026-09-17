<?php

namespace App\Cms\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * Full-page HTML cache for signed-out visitors (Settings → Advanced → Cache
 * rendered pages).
 *
 * Deliberately narrow, because a page served to the wrong person is far worse
 * than a slow one:
 *
 *  - Only the public content pages listed in ROUTES, never the cart, checkout,
 *    account or anything with a signed or personal URL.
 *  - Only for visitors with nothing of their own on the page: signed out, no
 *    cart, no flash message, no old form input.
 *  - The CSRF token is stored as a placeholder and each visitor gets their own
 *    on the way out, so the forms on a cached page still submit.
 *
 * Invalidation is all-or-nothing: saving any content model, or any setting,
 * moves every page onto a new cache generation. Content changes are rare next
 * to page views, so that trade keeps the cache simple and never stale.
 */
class PageCache
{
    /** Named routes whose output is the same for every signed-out visitor. */
    public const ROUTES = [
        'home', 'page.show', 'contact',
        'blog.index', 'blog.category', 'blog.tag', 'blog.show',
        'shop.index', 'shop.category', 'shop.show',
    ];

    /** Query parameters that may vary a cached page; any other skips the cache. */
    private const QUERY_KEYS = ['page'];

    /**
     * Saved on every visit, sale or sign-in rather than when someone edits
     * the site. Flushing on these would empty the cache constantly.
     */
    private const IGNORED_MODELS = [
        \App\Models\ActivityLog::class, \App\Models\Cart::class, \App\Models\CartItem::class,
        \App\Models\ContactSubmission::class, \App\Models\OtpCode::class, \App\Models\PaymentTransaction::class,
        \App\Models\PushDevice::class, \App\Models\SmsLog::class, \App\Models\Subscriber::class,
        \App\Models\Order::class, \App\Models\OrderItem::class, \App\Models\User::class,
    ];

    private const GENERATION_KEY = 'page-cache:generation';

    private const PLACEHOLDER = '__RADIUS_CSRF_TOKEN__';

    public function enabled(): bool
    {
        return (bool) setting('cache_enabled', false);
    }

    /** The cache key for this request, or null when it must not be cached. */
    public function keyFor(Request $request): ?string
    {
        if (! $request->isMethodSafe() || ! in_array($request->route()?->getName(), self::ROUTES, true)) {
            return null;
        }

        if (array_diff(array_keys($request->query()), self::QUERY_KEYS) !== []) {
            return null;
        }

        if ($request->user() || $this->hasVisitorState($request)) {
            return null;
        }

        $query = $request->query();
        ksort($query);

        return 'page-cache:'.$this->generation().':'.sha1(implode('|', [
            $request->getSchemeAndHttpHost(),
            $request->getBaseUrl().$request->getPathInfo(),
            http_build_query($query),
            app()->getLocale(),
            config('cms.version'),
        ]));
    }

    /** @return array{content: string, type: string}|null */
    public function get(string $key, Request $request): ?array
    {
        $hit = Cache::get($key);

        if (! is_array($hit) || ! isset($hit['content'])) {
            return null;
        }

        $hit['content'] = str_replace(self::PLACEHOLDER, $request->session()->token(), $hit['content']);

        return $hit;
    }

    public function put(string $key, string $content, string $type, Request $request): void
    {
        $token = $request->session()->token();

        Cache::put($key, [
            'content' => $token ? str_replace($token, self::PLACEHOLDER, $content) : $content,
            'type' => $type,
        ], max(10, (int) setting('cache_ttl', 600)));
    }

    /** Retire every cached page. Old entries simply expire. */
    public function flush(): void
    {
        Cache::forever(self::GENERATION_KEY, (int) Cache::get(self::GENERATION_KEY, 0) + 1);
    }

    /** Flush whenever something that could appear on a page is saved or deleted. */
    public function watchModels(): void
    {
        $flush = function (string $event, array $payload) {
            $model = $payload[0] ?? null;

            if ($model instanceof Model && ! in_array($model::class, self::IGNORED_MODELS, true) && $this->enabled()) {
                $this->flush();
            }
        };

        Event::listen(['eloquent.saved: *', 'eloquent.deleted: *', 'eloquent.restored: *'], $flush);
    }

    private function generation(): int
    {
        return (int) Cache::get(self::GENERATION_KEY, 0);
    }

    private function hasVisitorState(Request $request): bool
    {
        $session = $request->session();

        if ($session->get('_flash.old', []) !== [] || $session->get('_flash.new', []) !== [] || $session->has('errors')) {
            return true;
        }

        return modules()->enabled('shop') && app(\App\Cms\Shop\CartService::class)->itemCount() > 0;
    }
}
