<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\View\View;

/**
 * The homepage.
 *
 * If the owner has marked a page as the homepage, that page's content is
 * rendered. Otherwise a module-aware default is shown: latest posts, featured
 * products, or a welcome panel on a site with neither.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        $page = Page::published()->where('is_homepage', true)->first();

        if ($page) {
            seo()->forModel($page)->forRoute('home');

            return view(
                $page->template ? theme_view("pages.{$page->template}", 'theme::pages.show') : 'theme::pages.show',
                ['page' => $page]
            );
        }

        seo()->forRoute('home')->schema('WebSite');

        return view('theme::home', [
            'featuredPosts' => $this->featuredPosts(),
            'featuredProducts' => $this->featuredProducts(),
        ]);
    }

    private function featuredPosts()
    {
        if (modules()->disabled('blog')) {
            return collect();
        }

        return Post::published()
            ->with('category')
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();
    }

    private function featuredProducts()
    {
        if (modules()->disabled('shop')) {
            return collect();
        }

        return Product::published()
            ->inStock()
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();
    }
}
