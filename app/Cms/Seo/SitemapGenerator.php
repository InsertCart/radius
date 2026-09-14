<?php

namespace App\Cms\Seo;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Builds sitemap.xml from whatever content modules are switched on.
 *
 * Rows are streamed with a cursor rather than loaded into memory, so a site
 * with tens of thousands of products still generates a sitemap on the modest
 * memory limits typical of shared hosting.
 */
class SitemapGenerator
{
    /** Google's cap per sitemap file. */
    private const MAX_URLS = 50000;

    public function generate(): string
    {
        $xml = new \XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);

        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $count = 0;

        foreach ($this->urls() as $url) {
            if (++$count > self::MAX_URLS) {
                break;
            }

            $xml->startElement('url');
            $xml->writeElement('loc', $url['loc']);

            if (! empty($url['lastmod'])) {
                $xml->writeElement('lastmod', Carbon::parse($url['lastmod'])->toAtomString());
            }

            $xml->writeElement('changefreq', $url['changefreq'] ?? 'weekly');
            $xml->writeElement('priority', (string) ($url['priority'] ?? '0.5'));
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * @return \Generator<array{loc: string, lastmod?: mixed, changefreq?: string, priority?: string}>
     */
    private function urls(): \Generator
    {
        yield [
            'loc' => url('/'),
            'lastmod' => now(),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];

        yield from $this->pageUrls();

        if (modules()->enabled('blog')) {
            yield from $this->blogUrls();
        }

        if (modules()->enabled('shop')) {
            yield from $this->shopUrls();
        }
    }

    private function pageUrls(): \Generator
    {
        $query = Page::published()
            ->where('is_homepage', false)
            ->where('noindex', false)
            ->select(['slug', 'updated_at']);

        foreach ($query->cursor() as $page) {
            yield [
                'loc' => route('page.show', $page->slug),
                'lastmod' => $page->updated_at,
                'changefreq' => 'monthly',
                'priority' => '0.8',
            ];
        }
    }

    private function blogUrls(): \Generator
    {
        yield [
            'loc' => route('blog.index'),
            'lastmod' => Post::published()->max('published_at') ?? now(),
            'changefreq' => 'daily',
            'priority' => '0.9',
        ];

        $posts = Post::published()
            ->where('noindex', false)
            ->select(['slug', 'updated_at'])
            ->orderByDesc('published_at');

        foreach ($posts->cursor() as $post) {
            yield [
                'loc' => route('blog.show', $post->slug),
                'lastmod' => $post->updated_at,
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        $categories = Category::blog()->active()->where('noindex', false)->select(['slug', 'updated_at']);

        foreach ($categories->cursor() as $category) {
            yield [
                'loc' => route('blog.category', $category->slug),
                'lastmod' => $category->updated_at,
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }
    }

    private function shopUrls(): \Generator
    {
        yield [
            'loc' => route('shop.index'),
            'lastmod' => Product::published()->max('updated_at') ?? now(),
            'changefreq' => 'daily',
            'priority' => '0.9',
        ];

        $products = Product::published()
            ->where('noindex', false)
            ->select(['slug', 'updated_at'])
            ->orderByDesc('updated_at');

        foreach ($products->cursor() as $product) {
            yield [
                'loc' => route('shop.show', $product->slug),
                'lastmod' => $product->updated_at,
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }

        $categories = Category::shop()->active()->where('noindex', false)->select(['slug', 'updated_at']);

        foreach ($categories->cursor() as $category) {
            yield [
                'loc' => route('shop.category', $category->slug),
                'lastmod' => $category->updated_at,
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }
    }

    /** robots.txt, with the sitemap line appended automatically. */
    public function robots(): string
    {
        $body = (string) setting('robots_txt', "User-agent: *\nDisallow: /admin");

        if (setting('noindex', false)) {
            $body = "User-agent: *\nDisallow: /";
        }

        if (setting('sitemap_enabled', true) && ! str_contains($body, 'Sitemap:')) {
            $body .= "\n\nSitemap: ".url('sitemap.xml');
        }

        return $body;
    }
}
