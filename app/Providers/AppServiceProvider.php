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
    private function pinGeneratedUrlsToAppUrl(): void
    {
        $url = trim((string) config('app.url'));

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        URL::forceRootUrl($url);

        if (str_starts_with(strtolower($url), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
