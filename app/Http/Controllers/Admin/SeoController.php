<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Seo\SeoManager;
use App\Http\Controllers\Controller;
use App\Models\SeoMeta;
use App\Models\SeoRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * SEO overrides for routes with no content model behind them, plus the
 * redirect map used when migrating from an older site.
 */
class SeoController extends Controller
{
    public function index(): View
    {
        $routes = $this->manageableRoutes();

        return view('admin.seo.index', [
            'routes' => $routes,
            'meta' => SeoMeta::whereIn('route_key', array_keys($routes))->get()->keyBy('route_key'),
            'schemaTypes' => SeoManager::SCHEMA_TYPES,
            'sitemapUrl' => route('sitemap'),
            'robotsUrl' => route('robots'),
        ]);
    }

    public function updateMeta(Request $request, string $routeKey): RedirectResponse
    {
        abort_unless(array_key_exists($routeKey, $this->manageableRoutes()), 404);

        $validated = $request->validate([
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'og_image' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'schema_type' => ['nullable', 'string', 'in:'.implode(',', array_keys(SeoManager::SCHEMA_TYPES))],
            'noindex' => ['nullable', 'boolean'],
        ]);

        SeoMeta::updateOrCreate(
            ['route_key' => $routeKey],
            array_merge($validated, ['noindex' => $request->boolean('noindex')])
        );

        activity('seo.meta_updated', "Updated the SEO settings for \"{$routeKey}\".");

        return back()->with('status', 'SEO settings saved.');
    }

    public function redirects(Request $request): View
    {
        return view('admin.seo.redirects', [
            'redirects' => SeoRedirect::when($request->filled('q'),
                fn ($q) => $q->where('source', 'like', '%'.$request->string('q').'%'))
                ->orderByDesc('hits')
                ->paginate(30)
                ->withQueryString(),
            'filters' => $request->only('q'),
        ]);
    }

    public function storeRedirect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:255', 'unique:seo_redirects,source'],
            'destination' => ['required', 'string', 'max:255'],
            'status_code' => ['required', 'in:301,302,307,308'],
        ]);

        // A redirect pointing at itself would loop until the browser gives up.
        if (trim($validated['source'], '/') === trim($validated['destination'], '/')) {
            return back()->with('error', 'The source and destination are the same.');
        }

        SeoRedirect::create($validated + ['is_active' => true]);

        Cache::forget('cms.seo.redirects');

        activity('seo.redirect_created', "Added a redirect from /{$validated['source']}.");

        return back()->with('status', 'Redirect added.');
    }

    public function destroyRedirect(SeoRedirect $redirect): RedirectResponse
    {
        $redirect->delete();

        Cache::forget('cms.seo.redirects');

        return back()->with('status', 'Redirect removed.');
    }

    /** Tell the search engines the sitemap has changed. */
    public function pingSitemap(): RedirectResponse
    {
        $sitemap = route('sitemap');

        try {
            Http::timeout(10)->get('https://www.google.com/ping', ['sitemap' => $sitemap]);
            Http::timeout(10)->get('https://www.bing.com/ping', ['sitemap' => $sitemap]);
        } catch (\Throwable $e) {
            return back()->with('warning', 'Could not reach the search engines. Submit the sitemap manually in Search Console.');
        }

        return back()->with('status', 'Sitemap submitted.');
    }

    /**
     * Routes that carry SEO settings but have no editable content model.
     * Module-owned entries drop out when their module is off.
     */
    private function manageableRoutes(): array
    {
        $routes = [
            'home' => ['label' => 'Homepage', 'url' => url('/')],
        ];

        if (modules()->enabled('blog')) {
            $routes['blog.index'] = ['label' => 'Blog index', 'url' => route('blog.index')];
        }

        if (modules()->enabled('shop')) {
            $routes['shop.index'] = ['label' => 'Shop index', 'url' => route('shop.index')];
        }

        if (modules()->enabled('contact')) {
            $routes['contact'] = ['label' => 'Contact page', 'url' => route('contact')];
        }

        return $routes;
    }
}
