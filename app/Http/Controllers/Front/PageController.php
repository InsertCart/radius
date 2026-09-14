<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::published()->where('slug', $slug)->firstOrFail();

        seo()->forModel($page)->breadcrumbs([
            'Home' => url('/'),
            $page->title => $page->url(),
        ]);

        // A page may name a template from the active theme; anything missing
        // falls back to the generic page view.
        $view = $page->template
            ? theme_view("pages.{$page->template}", 'theme::pages.show')
            : 'theme::pages.show';

        return view($view, ['page' => $page]);
    }
}
