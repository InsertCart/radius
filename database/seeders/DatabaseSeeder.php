<?php

namespace Database\Seeders;

use App\Cms\Modules\ModuleManager;
use App\Cms\Payments\PaymentManager;
use App\Cms\Settings\SettingsRepository;
use App\Cms\Themes\ThemeManager;
use Illuminate\Database\Seeder;

/**
 * Brings a database up to a working baseline: module rows, payment gateway
 * rows, installed themes, default settings and starter content.
 *
 * The installer calls the same pieces, so `php artisan db:seed` is the way to
 * repair an install whose rows have drifted from config.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Syncing modules...');
        app(ModuleManager::class)->sync();

        $this->command?->info('Syncing payment gateways...');
        app(PaymentManager::class)->sync();

        $this->command?->info('Syncing themes...');
        app(ThemeManager::class)->sync();

        $this->command?->info('Writing default settings...');
        $written = app(SettingsRepository::class)->seedMissingDefaults();
        $this->command?->info("  {$written} settings written.");

        $this->call(ContentSeeder::class);
        $this->call(BuilderSeeder::class);
    }
}
