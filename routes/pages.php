<?php

use App\Http\Controllers\Front\PageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catch-all page route
|--------------------------------------------------------------------------
| A CMS page lives at the site root: /about, /privacy, /terms. That means one
| route has to match any single path segment, which would happily swallow
| /login and /register too.
|
| This file is therefore loaded after every other route file, so a real route
| always wins and only genuinely unclaimed paths reach the page lookup.
*/

Route::middleware('installed')->group(function () {
    Route::get('{slug}', [PageController::class, 'show'])
        ->where('slug', '[A-Za-z0-9\-_]+')
        ->name('page.show');
});
