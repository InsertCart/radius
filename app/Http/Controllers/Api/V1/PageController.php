<?php

namespace App\Http\Controllers\Api\V1;

use App\Cms\Api\Resource;
use App\Http\Controllers\Api\ApiController;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Published pages, so an app can show the terms, the privacy notice or an
 * about screen as real content instead of a web view.
 */
class PageController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $pages = Page::published()
            ->when($request->boolean('menu_only'), fn ($query) => $query->where('show_in_menu', true))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate($this->perPage($request));

        return $this->page(Resource::paginated($pages, fn (Page $page) => Resource::page($page)));
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $page = Page::published()->where('slug', $slug)->first();

        if (! $page) {
            return $this->fail('No such page.', 404, 'not_found');
        }

        return $this->data(Resource::page($page, full: true));
    }
}
