<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Updates\BackupService;
use App\Cms\Updates\UpdateApplier;
use App\Cms\Updates\UpdateChecker;
use App\Cms\Updates\UpdateException;
use App\Cms\Updates\UpgradeState;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The update screen.
 *
 * Applying an update happens in two requests on purpose. The first replaces the
 * files and then stops, because it is now running a mixture of the old code it
 * booted with and the new code on disk. The second - a fresh request, entirely
 * new code - runs the migrations and clears the caches.
 */
class UpdateController extends Controller
{
    public function __construct(
        private UpdateChecker $checker,
        private UpdateApplier $applier,
        private BackupService $backups,
    ) {}

    public function index(): View
    {
        $manifest = null;
        $error = null;

        try {
            $manifest = $this->checker->cached();
        } catch (UpdateException $e) {
            $error = $e->getMessage();
        }

        $available = $manifest && $manifest->isNewerThan($this->checker->currentVersion());

        return view('admin.updates.index', [
            'current' => $this->checker->currentVersion(),
            'manifest' => $manifest,
            'available' => $available,
            'checks' => $available ? $this->applier->preflight($manifest) : [],
            'modified' => $this->applier->modifiedFiles(),
            'hasBaseline' => $this->applier->hasChecksumBaseline(),
            'pending' => $this->applier->pending(),
            'lastUpdate' => $this->applier->lastUpdate(),
            'backups' => $this->backups->all(),
            'configured' => $this->checker->enabled(),
            'manifestUrl' => $this->checker->url(),
            'lastChecked' => $this->checker->lastCheckedAt(),
            'error' => $error,
        ]);
    }

    /** The manual "check now" button; bypasses the daily cache. */
    public function check(): RedirectResponse
    {
        try {
            $manifest = $this->checker->refresh();
        } catch (UpdateException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The update check failed. Check the logs for details.');
        }

        return back()->with('status', $manifest->isNewerThan($this->checker->currentVersion())
            ? "Version {$manifest->version} is available."
            : 'You are running the latest version.');
    }

    /**
     * Download, verify, back up and swap the files.
     *
     * Ends in a redirect rather than rendering anything: the code that would
     * render it has just been replaced on disk.
     */
    public function apply(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'confirm' => ['accepted'],
            'overwrite_edited' => ['nullable', 'boolean'],
            'backup_database' => ['nullable', 'boolean'],
        ], [
            'confirm.accepted' => 'Tick the confirmation box before applying an update.',
        ]);

        try {
            $manifest = $this->checker->fetch();

            $checks = $this->applier->preflight($manifest);

            if (! $this->applier->preflightPasses($checks)) {
                return back()->with('error', 'This update cannot be applied yet. Check the list of requirements above.');
            }

            activity('update.started', "Started updating to version {$manifest->version}.");

            $result = $this->applier->apply(
                $manifest,
                overwriteEdited: $request->boolean('overwrite_edited'),
                backupDatabase: $request->boolean('backup_database', true),
            );
        } catch (UpdateException $e) {
            // Written for site owners, so safe to show as it is.
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The update failed. Nothing was changed if the download had not finished; check System → Logs.');
        }

        // The running process is now half old code, half new. Stop here.
        return redirect()->route('admin.updates.finalize');
    }

    /**
     * Second half, running entirely on the new code: migrations, caches, and
     * the version stamp.
     */
    public function finalize(): View|RedirectResponse
    {
        $pending = $this->applier->pending();

        if (! $pending) {
            return redirect()->route('admin.updates.index');
        }

        $from = (string) ($pending['from'] ?? '');
        $to = (string) ($pending['to'] ?? '');

        try {
            $steps = $this->applier->finalize();
        } catch (\Throwable $e) {
            report($e);

            return view('admin.updates.finalize', [
                'failed' => true,
                'message' => $e->getMessage(),
                'from' => $from,
                'to' => $to,
                'steps' => [],
                'skipped' => $pending['skipped'] ?? [],
            ]);
        }

        $this->applier->stampVersion($from, $to);
        $this->applier->clearPending();
        $this->checker->flush();

        activity('update.completed', "Updated from version {$from} to {$to}.");

        return view('admin.updates.finalize', [
            'failed' => false,
            'message' => null,
            'from' => $from,
            'to' => $to,
            'steps' => $steps,
            'skipped' => $pending['skipped'] ?? [],
        ]);
    }

    /**
     * Finish an update whose files arrived without the updater: unzipped over
     * the site with FTP or a hosting file manager, which runs no migrations.
     *
     * Deliberately the same steps as finalize(), minus the bookkeeping for a
     * pending update there is none of. The database is behind the code either
     * way, and catching it up does not depend on how the files got there.
     */
    public function finish(UpgradeState $state): RedirectResponse
    {
        if (! $state->needsFinishing()) {
            return redirect()->route('admin.updates.index')->with('status', 'The database is already up to date.');
        }

        $from = $state->recordedVersion() ?? 'unknown';

        // A release can add or change tables, and this is the last moment at
        // which the previous shape of the data still exists.
        try {
            $this->backups->dumpDatabase('before-'.cms_version());
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            $this->applier->finalize();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The update could not be finished: '.$e->getMessage()
                .' A database backup was taken just before it started.');
        }

        $this->applier->stampVersion($from, cms_version());

        activity('update.finished', "Finished an update from version {$from} to ".cms_version().' that was installed by hand.');

        return redirect()->route('admin.updates.index')
            ->with('status', 'Database migrated and caches cleared. This site is now fully on version '.cms_version().'.');
    }

    public function rollback(Request $request): RedirectResponse
    {
        try {
            $result = $this->applier->rollback($request->boolean('restore_database'));
        } catch (UpdateException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The rollback failed. Restore your backup manually; see System → Logs.');
        }

        activity('update.rolled_back', 'Rolled an update back to version '.($result['version'] ?? 'the previous release').'.');

        return redirect()->route('admin.updates.index')->with('status', sprintf(
            'Rolled back to version %s. %d file(s) restored, %d added by the update removed.',
            $result['version'] ?? 'the previous release', $result['restored'], $result['removed']
        ));
    }

    public function downloadBackup(string $name): StreamedResponse
    {
        $path = $this->backups->find($name);

        abort_unless($path !== null, 404);

        activity('update.backup_downloaded', "Downloaded the backup {$name}.");

        return response()->streamDownload(function () use ($path) {
            $handle = fopen($path, 'rb');

            while (! feof($handle)) {
                echo fread($handle, 1 << 20);
                flush();
            }

            fclose($handle);
        }, basename($path), [
            'Content-Type' => 'application/gzip',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function destroyBackup(string $name): RedirectResponse
    {
        return $this->backups->delete($name)
            ? back()->with('status', 'Backup deleted.')
            : back()->with('error', 'That backup could not be found.');
    }

    /** Take a database backup on demand, without applying anything. */
    public function backupNow(): RedirectResponse
    {
        try {
            $path = $this->backups->dumpDatabase('manual');
        } catch (UpdateException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The backup failed. Check System → Logs.');
        }

        activity('update.backup_created', 'Created a database backup.');

        return back()->with('status', 'Database backed up to '.basename($path).'.');
    }
}
