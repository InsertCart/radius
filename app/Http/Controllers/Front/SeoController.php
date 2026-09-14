<?php

namespace App\Http\Controllers\Front;

use App\Cms\Seo\SitemapGenerator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * sitemap.xml and robots.txt.
 *
 * The sitemap is cached: regenerating it walks every published row, which is
 * wasted work when a crawler fetches it several times a day.
 */
class SeoController extends Controller
{
    public function __construct(private SitemapGenerator $sitemap) {}

    public function sitemap(): Response
    {
        abort_unless(setting('sitemap_enabled', true), 404);

        $xml = Cache::remember('cms.sitemap', now()->addHours(6), fn () => $this->sitemap->generate());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(): Response
    {
        return response($this->sitemap->robots(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
