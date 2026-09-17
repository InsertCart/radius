<?php

/*
|--------------------------------------------------------------------------
| Site search
|--------------------------------------------------------------------------
| Which content the public search covers, and where the file index lives.
| The choices a site owner makes - engine, live results, which types are
| searchable - are settings (Settings -> Search), not config, so they can be
| changed without touching a file. See config/settings.php.
|
| Engines
|   database  Queries the tables directly. Nothing to build, always current.
|   index     Answers from a prebuilt word index under storage/, so a search
|             never scans a table. Built when the engine is switched on and
|             kept current as content is saved.
|
| A developer can add an engine or a searchable type from a service provider:
|
|     search()->extend('meilisearch', fn ($app) => new MeilisearchEngine(...));
|     search()->register('event', ['model' => Event::class, 'label' => 'Events']);
*/

return [

    /*
    | Searchable content, in the order results are grouped on screen.
    |
    | model          An Eloquent model implementing App\Cms\Search\Contracts\Searchable.
    | label          Heading for this group of results.
    | module         Hidden when this module is disabled.
    | setting        Boolean setting that switches the type on or off.
    | results_route  Where "see all results" goes. The route receives ?q=.
    |                Leave null to use the site-wide /search page.
    */

    'types' => [
        'product' => [
            'model' => App\Models\Product::class,
            'label' => 'Products',
            'module' => 'shop',
            'setting' => 'search_products',
            'results_route' => 'shop.index',
        ],
        'post' => [
            'model' => App\Models\Post::class,
            'label' => 'Posts',
            'module' => 'blog',
            'setting' => 'search_posts',
            'results_route' => 'blog.index',
        ],
        'page' => [
            'model' => App\Models\Page::class,
            'label' => 'Pages',
            'module' => 'pages',
            'setting' => 'search_pages',
            'results_route' => null,
        ],
    ],

    'index' => [
        // One folder per content type. Safe to delete: rebuild recreates it.
        'path' => env('SEARCH_INDEX_PATH', storage_path('app/search-index')),

        // Characters of each field that are indexed. Bounds memory and index
        // size on sites with very long articles; the opening of an article
        // is where its subject is named anyway.
        'max_field_length' => 20000,

        // Rows read from the database per batch while rebuilding.
        'chunk' => 200,

        // How many completions the last, half-typed word may expand to.
        'max_prefix_expansions' => 40,
    ],

    // Upper bound on matches considered for one query. A results page never
    // needs the ten-thousandth match, and ranking them all costs time.
    'max_results' => 1000,
];
