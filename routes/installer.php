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
| The whole group closes itself once storage/installed exists.
*/

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
