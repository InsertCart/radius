<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->pinGeneratedUrlsToAppUrl();
    }

    /**
     * Build absolute URLs from APP_URL rather than from the request.
     *
     * By default Laravel takes the host for a generated URL from the incoming
     * request, and the Host header is set by whoever is calling. On a server
     * with a catch-all virtual host - the normal arrangement on shared hosting
     * - anybody can send a request carrying someone else's host and have the
     * application build links from it.
     *
     * That matters most for links the site emails out. A password reset built
     * from an attacker-supplied host points the victim's browser, and the
     * one-time token in the URL, at the attacker's server. Pinning the root to
     * the address the owner configured removes the request from that decision.
     *
     * Skipped only when APP_URL is unset or unusable, so a half-configured
     * install still serves pages instead of erroring. It is applied on the
     * console too: a command has no request to take a host from and already
     * falls back to APP_URL, so pinning it there changes nothing except that
     * queued mail and a web request now agree on one address.
     */
    /**
     * Drop a trailing /public from APP_URL when the site is already answering
     * without it.
     *
     * Because every generated URL is pinned to APP_URL, an APP_URL ending in
     * /public puts that segment into every link, asset, canonical tag and
     * og:url - while the .htaccess upstairs serves the same pages without it.
     * Two addresses for one page, with the page itself naming the longer one as
     * canonical: the worst of both.
     *
     * The shorter form is only adopted when this very request proves it works,
     * by having arrived on it. A site genuinely reachable only at /public - a
     * server with no rewrite rules - keeps receiving requests on that address,
     * so nothing is stripped and its links go on working.
     */
    private function withoutPublicSuffix(string $url, ?string $requestRoot): string
    {
        $canonical = rtrim((string) preg_replace('#/public/?$#', '', $url), '/');

        if ($canonical === rtrim($url, '/') || $canonical === '' || $requestRoot === null) {
            return $url;
        }

        return $requestRoot === $canonical ? $canonical : $url;
    }

    private function pinGeneratedUrlsToAppUrl(): void
    {
        $url = trim((string) config('app.url'));

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        // The request's own root is what proves the shorter address works.
        // A console run has no request, so nothing is assumed there.
        $url = $this->withoutPublicSuffix(
            $url,
            app()->runningInConsole() ? null : rtrim(request()->root(), '/'),
        );

        URL::forceRootUrl($url);

        if (str_starts_with(strtolower($url), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
