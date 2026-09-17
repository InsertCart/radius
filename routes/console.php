<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The search index stores what a result displays, a product's price among
// them, and a sale that starts or ends on a date does not save the product.
// A nightly rebuild catches those. Only runs when index search is chosen, and
// only on sites with the scheduler's cron entry in place.
Schedule::command('search:rebuild')
    ->dailyAt('03:30')
    ->when(fn () => search()->engineName() !== 'database')
    ->withoutOverlapping();
