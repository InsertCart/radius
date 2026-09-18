<?php

namespace App\Console\Commands;

use App\Cms\Updates\UpdateApplier;
use App\Cms\Updates\UpdateChecker;
use App\Cms\Updates\UpdateException;
use Illuminate\Console\Command;

/**
 * The command-line route to the same update the admin panel offers.
 *
 * Worth having even though most buyers will never use it: on the command line
 * there is no request timeout to run into, which makes this the reliable way to
 * install a large release on a slow server.
 *
 * Unlike the web flow, the whole thing runs in one process. That is safe here
 * because the command finishes and exits before anything uses the new code -
 * migrations run through a freshly spawned artisan process.
 */
class CmsUpdateCommand extends Command
{
    protected $signature = 'cms:update
        {--check : Only report whether an update is available}
        {--finish : Migrate and clear caches for a release that was copied in by hand}
        {--force : Skip the confirmation prompt}
        {--no-backup : Do not back up the database first}
        {--overwrite-edited : Replace shipped files that were edited on this site}';

    protected $description = 'Check for and install a new release of the CMS';

    public function handle(UpdateChecker $checker, UpdateApplier $applier): int
    {
        // Before the update-server check: a release unzipped over the site
        // still has to be finished on a site that never uses the update
        // server, or cannot reach it.
        if ($this->option('finish')) {
            return $this->finish($applier, app(\App\Cms\Updates\UpgradeState::class));
        }

        if (! $checker->enabled()) {
            $this->error('Updates are switched off, or CMS_UPDATE_URL is not set in .env.');

            return self::FAILURE;
        }

        try {
            $manifest = $checker->refresh();
        } catch (UpdateException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $current = $checker->currentVersion();

        $this->line("Installed: <info>{$current}</info>");
        $this->line("Available: <info>{$manifest->version}</info>");

        if ($manifest->tags) {
            $this->line('Tags:      '.implode(', ', $manifest->tags));
        }

        if ($manifest->notes) {
            $this->newLine();
            $this->line($manifest->notes);
        }

        if (! $manifest->isNewerThan($current)) {
            $this->newLine();
            $this->info('You are running the latest version.');

            return self::SUCCESS;
        }

        if ($this->option('check')) {
            return self::SUCCESS;
        }

        $this->newLine();
        $checks = $applier->preflight($manifest);

        foreach ($checks as $check) {
            $icon = $check['passed'] ? '<info>OK  </info>' : ($check['fatal'] ? '<error>FAIL</error>' : '<comment>WARN</comment>');
            $this->line("  {$icon} {$check['label']} — {$check['message']}");
        }

        if (! $applier->preflightPasses($checks)) {
            $this->newLine();
            $this->error('This update cannot be applied until the failures above are resolved.');

            return self::FAILURE;
        }

        $edited = $applier->modifiedFiles();

        if ($edited && ! $this->option('overwrite-edited')) {
            $this->newLine();
            $this->warn(count($edited).' shipped file(s) were edited on this site and will be left alone:');

            foreach (array_slice($edited, 0, 10) as $file) {
                $this->line("  {$file}");
            }
        }

        $this->newLine();

        if (! $this->option('force') && ! $this->confirm("Install version {$manifest->version}?", false)) {
            return self::SUCCESS;
        }

        try {
            $this->info('Downloading and verifying...');

            $result = $applier->apply(
                $manifest,
                overwriteEdited: (bool) $this->option('overwrite-edited'),
                backupDatabase: ! $this->option('no-backup'),
            );

            $this->line("  {$result['changed']} file(s) changed.");

            if ($result['db_backup']) {
                $this->line('  Database backed up to '.basename($result['db_backup']).'.');
            }

            $this->info('Running migrations and clearing caches...');

            foreach ($applier->finalize() as $label => $detail) {
                $this->line("  {$label}: ".str_replace("\n", ' ', $detail));
            }

            $applier->stampVersion($result['from'], $result['to']);
            $applier->clearPending();
            $checker->flush();
        } catch (UpdateException $e) {
            $this->newLine();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Updated to version {$manifest->version}.");

        return self::SUCCESS;
    }

    /** Same steps the Finish the update button runs in the admin panel. */
    private function finish(UpdateApplier $applier, \App\Cms\Updates\UpgradeState $state): int
    {
        if (! $state->needsFinishing()) {
            $this->info('Nothing to finish: the database already matches version '.cms_version().'.');

            return self::SUCCESS;
        }

        $from = $state->recordedVersion() ?? 'unknown';

        $this->line("Finishing the update from <info>{$from}</info> to <info>".cms_version().'</info>.');

        try {
            foreach ($applier->finalize() as $label => $detail) {
                $this->line("  {$label}: ".str_replace("\n", ' ', $detail));
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $applier->stampVersion($from, cms_version());

        $this->info('Done. This site is now fully on version '.cms_version().'.');

        return self::SUCCESS;
    }
}
