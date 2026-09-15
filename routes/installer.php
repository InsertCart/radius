<?php

use App\Http\Controllers\Installer\InstallController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Setup wizard
|--------------------------------------------------------------------------
| What a CodeCanyon buyer sees the first time they open the site: a server
| requirements check, database details, site details and an admin account.
|
| The whole group closes itself once storage/installed exists - the same way
| WordPress refuses to re-run its installer. There is nothing for the site
| owner to delete afterwards.
|
| Two layers do that, and both are deliberate:
|
|  1. Once the lock file is there, these routes are never registered at all.
|     The wizard stops existing rather than merely being guarded, which also
|     means an installed site carries none of its routing cost.
|  2. The 'not-installed' middleware still guards every route in the group.
|     Registration is decided once, at boot; if the routes were cached before
|     setup finished (php artisan route:cache), the cache would still hold
|     them, and the middleware is what closes that window.
*/

// Nothing below is registered once setup has finished.
if (cms_installed()) {
    // One redirect stands in for the whole wizard, so a bookmarked step or a
    // refresh of the final page lands somewhere useful instead of on a 404.
    Route::get('install/{step?}', fn () => redirect()->route('admin.login'))
        ->where('step', '.*');

    return;
}

Route::prefix('install')
    ->name('install.')
    ->middleware('not-installed')
    ->group(function () {
        Route::get('/', fn () => redirect()->route('install.requirements'));

        Route::get('requirements', [InstallController::class, 'requirements'])->name('requirements');

        Route::get('database', [InstallController::class, 'database'])->name('database');
        Route::post('database', [InstallController::class, 'saveDatabase'])->name('database.save');

        Route::get('site', [InstallController::class, 'site'])->name('site');
        Route::post('site', [InstallController::class, 'saveSite'])->name('site.save');

        Route::get('account', [InstallController::class, 'account'])->name('account');
        Route::post('account', [InstallController::class, 'saveAccount'])->name('account.save');

        Route::get('finish', [InstallController::class, 'finish'])->name('finish');
    });
