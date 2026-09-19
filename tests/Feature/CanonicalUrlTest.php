<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * One address per page.
 *
 * Every generated URL is pinned to APP_URL, so an APP_URL ending in /public put
 * that segment into every link, asset and canonical tag - while the .htaccess
 * above public/ served the very same pages without it. Google saw two addresses
 * for one page, and the page named the longer one as canonical.
 *
 * The shorter form is only adopted when the request proves it works by having
 * arrived on it, so a site that really is reachable only at /public keeps
 * working. That is the case worth pinning down here.
 */
class CanonicalUrlTest extends TestCase
{
    private function decide(string $appUrl, ?string $requestRoot): string
    {
        $method = new ReflectionMethod(AppServiceProvider::class, 'withoutPublicSuffix');

        return $method->invoke(new AppServiceProvider($this->app), $appUrl, $requestRoot);
    }

    public function test_public_is_dropped_when_the_site_answers_without_it(): void
    {
        $this->assertSame(
            'https://example.com',
            $this->decide('https://example.com/public', 'https://example.com')
        );

        $this->assertSame(
            'https://example.com/shop',
            $this->decide('https://example.com/shop/public/', 'https://example.com/shop')
        );
    }

    public function test_public_is_kept_when_that_is_the_only_working_address(): void
    {
        // A server with no rewrite rules: requests arrive on /public, and
        // stripping it would point every link and asset at a 404.
        $this->assertSame(
            'https://example.com/public',
            $this->decide('https://example.com/public', 'https://example.com/public')
        );
    }

    public function test_nothing_is_assumed_without_a_request(): void
    {
        // Queued mail and scheduled work have no request to learn from.
        $this->assertSame(
            'https://example.com/public',
            $this->decide('https://example.com/public', null)
        );
    }

    public function test_an_address_that_never_had_public_is_untouched(): void
    {
        $this->assertSame(
            'https://example.com',
            $this->decide('https://example.com', 'https://example.com')
        );

        // A site genuinely installed in a folder called "public" - not the
        // framework's public/ - must not lose it.
        $this->assertSame(
            'https://example.com/public/shop',
            $this->decide('https://example.com/public/shop', 'https://example.com/public/shop')
        );
    }
}
