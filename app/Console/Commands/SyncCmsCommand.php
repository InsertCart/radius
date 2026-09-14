<?php

namespace App\Console\Commands;

use App\Cms\Modules\ModuleManager;
use App\Cms\Payments\PaymentManager;
use App\Cms\Settings\SettingsRepository;
use App\Cms\Themes\ThemeManager;
use Illuminate\Console\Command;

/**
 * Reconciles the database with what config declares.
 *
 * Run after an upgrade that adds a module, a payment gateway or a setting:
 * the new rows appear without touching any value the site owner has changed.
 */
class SyncCmsCommand extends Command
{
    protected $signature = 'cms:sync {--themes : Only re-scan the themes folder}';

    protected $description = 'Sync modules, payment gateways, themes and default settings from config';

    public function handle(
        ModuleManager $modules,
        PaymentManager $payments,
        ThemeManager $themes,
        SettingsRepository $settings,
    ): int {
        if ($this->option('themes')) {
            $count = $themes->sync();
            $this->info("Found {$count} theme(s) on disk.");

            return self::SUCCESS;
        }

        $this->info('Syncing modules...');
        $this->line('  '.$modules->sync().' new module(s) registered.');

        $this->info('Syncing payment gateways...');
        $this->line('  '.$payments->sync().' new gateway(s) registered.');

        $this->info('Syncing themes...');
        $this->line('  '.$themes->sync().' theme(s) found.');

        $this->info('Writing missing default settings...');
        $this->line('  '.$settings->seedMissingDefaults().' setting(s) written.');

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
