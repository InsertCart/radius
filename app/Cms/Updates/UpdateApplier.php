<?php

namespace App\Cms\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Applies a release to the live installation.
 *
 * Two rules govern everything here.
 *
 * The first is that only paths config/updates.php names as shipped are ever
 * written. The database credentials, the uploads, the bought themes and the
 * builder's saved layouts are not in that list, so no release - however
 * malformed - can reach them.
 *
 * The second is that nothing is touched until every check has passed. The
 * download, the checksum, the staging extraction and the whole preflight run
 * while the site is still entirely intact; the file swap is the last thing to
 * happen, and every file it overwrites is copied aside first so it can be put
 * back.
 *
 * The swap deliberately does not finish the job. Replacing app/ and vendor/
 * underneath the process that is executing them leaves this request running a
 * mixture of old and new code, so it stops immediately afterwards and the
 * migrations run in a fresh request - see finalize().
 */
class UpdateApplier
{
    public function __construct(
        private ReleaseDownloader $downloader,
        private ReleaseArchive $archive,
        private BackupService $backups,
    ) {}

    // Preflight -----------------------------------------------------------

    /**
     * Every reason this update might not work, checked before anything moves.
     *
     * @return array<int, array{label: string, passed: bool, message: string, fatal: bool}>
     */
    public function preflight(ReleaseManifest $manifest): array
    {
        $checks = [];
        $current = cms_version();

        $checks[] = $this->check(
            'A newer version is available',
            $manifest->isNewerThan($current),
            $manifest->isNewerThan($current)
                ? "Version {$manifest->version} is newer than the installed {$current}."
                : "Version {$manifest->version} is not newer than the installed {$current}."
        );

        $checks[] = $this->check(
            'Upgrade path',
            $manifest->satisfiesMinimumVersion($current),
            $manifest->satisfiesMinimumVersion($current)
                ? 'This release can be installed directly.'
                : "This release needs version {$manifest->minVersion} or later installed first. You are on {$current}."
        );

        $checks[] = $this->check(
            'PHP version',
            $manifest->satisfiesPhpVersion(),
            $manifest->satisfiesPhpVersion()
                ? 'PHP '.PHP_VERSION.' meets the requirement.'
                : "This release needs PHP {$manifest->minPhp} or later. This server runs ".PHP_VERSION.'.'
        );

        $missing = $manifest->missingExtensions();
        $checks[] = $this->check(
            'PHP extensions',
            $missing === [],
            $missing === []
                ? 'All required extensions are loaded.'
                : 'Missing: '.implode(', ', $missing).'. Enable them in php.ini and restart the web server.'
        );

        foreach (['zip', 'fileinfo'] as $extension) {
            $checks[] = $this->check(
                "The {$extension} extension",
                extension_loaded($extension),
                extension_loaded($extension)
                    ? 'Loaded.'
                    : "Required to unpack a release. Enable extension={$extension} in php.ini and restart the web server."
            );
        }

        $checks[] = $this->check(
            'Checksum published',
            filled($manifest->sha256) || ! config('updates.require_checksum', true),
            filled($manifest->sha256)
                ? 'The release publishes a SHA-256 checksum.'
                : 'This release publishes no checksum, so the download cannot be verified.'
        );

        // Three times the archive: the download, the staging copy, and room for
        // the backup of everything being replaced.
        $needed = ($manifest->size ?: 100 * 1024 * 1024) * 3;
        $free = @disk_free_space(base_path());

        $checks[] = $this->check(
            'Free disk space',
            $free === false || $free > $needed,
            $free === false
                ? 'Could not be determined on this server.'
                : number_format($free / 1073741824, 2).' GB free; about '
                    .number_format($needed / 1048576).' MB is needed.',
            fatal: $free !== false && $free <= $needed
        );

        $unwritable = $this->unwritablePaths();
        $checks[] = $this->check(
            'File permissions',
            $unwritable === [],
            $unwritable === []
                ? 'Every folder an update writes to is writable.'
                : 'Not writable: '.implode(', ', array_slice($unwritable, 0, 6))
                    .(count($unwritable) > 6 ? ' and '.(count($unwritable) - 6).' more' : '')
        );

        $checks[] = $this->check(
            'No update in progress',
            $this->pending() === null,
            $this->pending() === null
                ? 'Ready to start.'
                : 'An update is already part-way through. Finish or roll it back first.'
        );

        // Not fatal - a slow server can still finish - but it is by far the
        // most common reason a large update dies half-way.
        $limit = (int) ini_get('max_execution_time');
        $checks[] = $this->check(
            'Script time limit',
            $limit === 0 || $limit >= 120,
            $limit === 0 ? 'No limit.' : "{$limit} seconds. A large release may need longer.",
            fatal: false
        );

        return $checks;
    }

    public function preflightPasses(array $checks): bool
    {
        foreach ($checks as $check) {
            if (! $check['passed'] && $check['fatal']) {
                return false;
            }
        }

        return true;
    }

    /** @return string[] shipped paths that exist but cannot be written */
    private function unwritablePaths(): array
    {
        $unwritable = [];

        foreach ($this->shippedPaths() as $relative) {
            $path = base_path($relative);

            if (! file_exists($path)) {
                // It will be created; what matters is the parent.
                $path = dirname($path);
            }

            if (file_exists($path) && ! is_writable($path)) {
                $unwritable[] = $relative;
            }
        }

        return $unwritable;
    }

    /** @return string[] */
    private function shippedPaths(): array
    {
        return array_merge(
            (array) config('updates.paths.replace', []),
            (array) config('updates.paths.merge', []),
        );
    }

    // Applying ------------------------------------------------------------

    /**
     * Download, verify, back up and swap.
     *
     * @return array{to: string, from: string, changed: int, skipped: string[], backup: string, db_backup: ?string}
     *
     * @throws UpdateException
     */
    public function apply(ReleaseManifest $manifest, bool $overwriteEdited = false, bool $backupDatabase = true): array
    {
        if ($this->pending() !== null) {
            throw new UpdateException('An update is already part-way through. Finish or roll it back before starting another.');
        }

        @set_time_limit(0);

        // A browser that gives up half-way must not abort the swap and leave
        // the site in pieces.
        @ignore_user_abort(true);

        $workspace = storage_path('app/private/'.trim((string) config('updates.workspace', 'updates'), '/'));
        $stage = $workspace.DIRECTORY_SEPARATOR.'stage-'.Str::random(10);
        $archivePath = null;

        try {
            $archivePath = $this->downloader->download($manifest, $workspace);

            $extracted = $this->archive->extract($archivePath, $stage);
            $this->archive->verify($stage, $manifest);

            $edited = $overwriteEdited ? [] : $this->modifiedFiles();

            $dbBackup = $backupDatabase ? $this->backups->dumpDatabase('before-'.$manifest->version) : null;

            $backupDir = $this->backups->directory().DIRECTORY_SEPARATOR
                .'files-'.$manifest->version.'-'.now()->format('Ymd-His');

            // ----- point of no return -----
            $result = $this->swap($stage, $extracted['files'], $backupDir, $edited);

            File::put($this->pendingPath(), json_encode([
                'from' => cms_version(),
                'to' => $manifest->version,
                'backup_dir' => $backupDir,
                'db_backup' => $dbBackup,
                'skipped' => $result['skipped'],
                'added' => $result['added'],
                'changed' => $result['changed'],
                'started_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            return [
                'to' => $manifest->version,
                'from' => cms_version(),
                'changed' => $result['changed'],
                'skipped' => $result['skipped'],
                'backup' => $backupDir,
                'db_backup' => $dbBackup,
            ];
        } finally {
            File::deleteDirectory($stage);

            if ($archivePath && is_file($archivePath)) {
                @unlink($archivePath);
            }
        }
    }

    /**
     * Copy the staged tree over the live one.
     *
     * Files whose contents already match are left alone. That is not only
     * faster - between two close releases most of vendor/ is identical - it
     * also keeps the backup to just the files that genuinely changed.
     *
     * @param  string[]  $staged  project-relative paths present in the release
     * @param  string[]  $protect  project-relative paths edited on this site
     * @return array{changed: int, skipped: string[]}
     */
    private function swap(string $stage, array $staged, string $backupDir, array $protect): array
    {
        File::ensureDirectoryExists($backupDir);

        $protect = array_flip($protect);
        $changed = 0;
        $skipped = [];
        $present = [];

        // Files this release introduces. They have no previous version, so
        // there is nothing to back up and nothing a restore could put back
        // over them - rolling back has to delete them instead, or the old tree
        // would be left carrying new files. A stray new migration is the case
        // that really bites: it would run again on the next attempt.
        $added = [];

        foreach ($staged as $relative) {
            $present[$relative] = true;

            $source = $stage.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $target = base_path($relative);

            if (! is_file($source)) {
                continue;
            }

            // A file the site owner has edited is left as it is, and reported,
            // so an update never silently discards someone's customisation.
            if (isset($protect[$relative])) {
                $skipped[] = $relative;

                continue;
            }

            if (is_file($target) && sha1_file($target) === sha1_file($source)) {
                continue;
            }

            if (is_file($target)) {
                $this->backupFile($relative, $backupDir);
            } else {
                $added[] = $relative;
            }

            File::ensureDirectoryExists(dirname($target));

            if (! @copy($source, $target)) {
                throw new UpdateException(
                    "Could not write {$relative}. The update stopped part-way; use Roll back to undo it."
                );
            }

            $changed++;
        }

        $changed += $this->removeOrphans($present, $backupDir);

        return ['changed' => $changed, 'skipped' => $skipped, 'added' => $added];
    }

    /**
     * Delete shipped files the new release no longer contains.
     *
     * Only inside the "replace" group. A stale class left in app/ or an
     * orphaned package in vendor/ breaks autoloading in ways that are horrible
     * to diagnose, so those directories have to end up matching the release
     * exactly. The "merge" group is left alone on purpose: a buyer may have
     * added their own config file, and an update has no business deleting it.
     *
     * @param  array<string, true>  $present
     */
    private function removeOrphans(array $present, string $backupDir): int
    {
        $removed = 0;

        foreach ((array) config('updates.paths.replace', []) as $relative) {
            $path = base_path($relative);

            if (! is_dir($path)) {
                continue;
            }

            foreach (File::allFiles($path, true) as $file) {
                $key = str_replace('\\', '/', Str::after($file->getPathname(), base_path().DIRECTORY_SEPARATOR));
                $key = str_replace('\\', '/', $key);

                if (isset($present[$key])) {
                    continue;
                }

                $this->backupFile($key, $backupDir);

                if (@unlink($file->getPathname())) {
                    $removed++;
                }
            }
        }

        return $removed;
    }

    private function backupFile(string $relative, string $backupDir): void
    {
        $source = base_path($relative);

        if (! is_file($source)) {
            return;
        }

        $target = $backupDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        File::ensureDirectoryExists(dirname($target));

        @copy($source, $target);
    }

    // Pending state -------------------------------------------------------

    public function pendingPath(): string
    {
        return storage_path('app/update-pending.json');
    }

    public function lastUpdatePath(): string
    {
        return storage_path('app/update-last.json');
    }

    /** An update that has swapped files but not yet finished. */
    public function pending(): ?array
    {
        return $this->readState($this->pendingPath());
    }

    /**
     * The most recent completed update, kept so it can still be undone.
     *
     * An update that finished cleanly can still turn out to have broken
     * something, and "it completed successfully" is no comfort at that point.
     * The record and its backup stay until the next update replaces them.
     */
    public function lastUpdate(): ?array
    {
        $last = $this->readState($this->lastUpdatePath());

        // Useless without the files it would restore.
        return $last && is_dir((string) ($last['backup_dir'] ?? '')) ? $last : null;
    }

    private function readState(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) File::get($path), true);

        return is_array($data) ? $data : null;
    }

    /** Marks the pending update finished, keeping it as the undo point. */
    public function clearPending(): void
    {
        $pending = $this->pending();

        if ($pending) {
            $pending['completed_at'] = now()->toIso8601String();
            File::put($this->lastUpdatePath(), json_encode($pending, JSON_PRETTY_PRINT));
        }

        @unlink($this->pendingPath());
    }

    // Rollback ------------------------------------------------------------

    /**
     * Put back every file the swap replaced.
     *
     * The database is only restored when asked for, because migrations that
     * ran successfully are usually worth keeping, and rolling the database
     * back to before them discards anything the site recorded since.
     *
     * @throws UpdateException
     */
    public function rollback(bool $restoreDatabase = false): array
    {
        // A part-finished update is the usual case, but a completed one can be
        // undone too for as long as its backup survives.
        $pending = $this->pending() ?? $this->lastUpdate();

        if (! $pending) {
            throw new UpdateException('There is no update to roll back.');
        }

        $backupDir = (string) ($pending['backup_dir'] ?? '');

        if (! is_dir($backupDir)) {
            throw new UpdateException('The backup for this update is missing, so it cannot be rolled back automatically.');
        }

        @set_time_limit(0);
        @ignore_user_abort(true);

        $restored = 0;

        foreach (File::allFiles($backupDir, true) as $file) {
            $relative = str_replace('\\', '/', Str::after($file->getPathname(), $backupDir.DIRECTORY_SEPARATOR));
            $target = base_path($relative);

            File::ensureDirectoryExists(dirname($target));

            if (@copy($file->getPathname(), $target)) {
                $restored++;
            }
        }

        // Files the release introduced. Restoring cannot remove these - there
        // was no earlier version of them to restore - so they are deleted
        // explicitly, or the rolled-back tree keeps running new code.
        $removed = 0;

        foreach ((array) ($pending['added'] ?? []) as $relative) {
            $relative = str_replace('\\', '/', (string) $relative);

            // Re-checked against the same allowlist that let it be written, so
            // a tampered state file cannot turn rollback into a delete tool.
            if (! $this->archive->isWritablePath($relative)) {
                continue;
            }

            if (is_file(base_path($relative)) && @unlink(base_path($relative))) {
                $removed++;
            }
        }

        if ($restoreDatabase && filled($pending['db_backup'] ?? null) && is_file($pending['db_backup'])) {
            $this->backups->restoreDatabase($pending['db_backup']);
        }

        // The restored config/cms.php already reports the old version; this
        // keeps the install record and the compiled caches agreeing with it.
        $this->stampVersion((string) ($pending['to'] ?? ''), (string) ($pending['from'] ?? ''));

        // Re-taken against the restored tree. The recorded hashes describe the
        // version that was just undone, so leaving them would make every
        // restored file look like a customisation - and the next update would
        // politely refuse to touch any of them, including config/cms.php, and
        // quietly install itself as the wrong version.
        $this->recordChecksums();

        foreach (['config:clear', 'cache:clear', 'view:clear', 'route:clear'] as $command) {
            try {
                \Illuminate\Support\Facades\Artisan::call($command);
            } catch (\Throwable $e) {
                Log::warning("[updates] {$command} failed during rollback: ".$e->getMessage());
            }
        }

        @unlink($this->pendingPath());
        @unlink($this->lastUpdatePath());

        $this->resetOpcache();

        return ['restored' => $restored, 'removed' => $removed, 'version' => $pending['from'] ?? null];
    }

    // Edit tracking -------------------------------------------------------

    public function checksumPath(): string
    {
        return storage_path('app/shipped-checksums.json');
    }

    /**
     * Record what the shipped files look like right now.
     *
     * Compared on the next update to work out which of them the site owner has
     * since edited. Only the paths in track_edits are recorded: those are the
     * ones a buyer plausibly customises, and hashing the whole of vendor/ on
     * every update would cost far more than it could save.
     */
    public function recordChecksums(): void
    {
        $hashes = [];

        foreach ((array) config('updates.paths.track_edits', []) as $relative) {
            $path = base_path($relative);

            if (is_file($path)) {
                $hashes[$relative] = sha1_file($path);

                continue;
            }

            if (! is_dir($path)) {
                continue;
            }

            foreach (File::allFiles($path, true) as $file) {
                $key = str_replace('\\', '/', Str::after($file->getPathname(), base_path().DIRECTORY_SEPARATOR));
                $hashes[$key] = sha1_file($file->getPathname());
            }
        }

        File::put($this->checksumPath(), json_encode($hashes, JSON_PRETTY_PRINT));
    }

    public function hasChecksumBaseline(): bool
    {
        return is_file($this->checksumPath());
    }

    /**
     * Shipped files that no longer match what was installed.
     *
     * With no baseline recorded - the first update after this feature arrives -
     * nothing can be compared, so nothing is claimed to be edited. Reporting
     * every file as modified would block the whole update.
     *
     * @return string[]
     */
    public function modifiedFiles(): array
    {
        if (! $this->hasChecksumBaseline()) {
            return [];
        }

        $recorded = json_decode((string) File::get($this->checksumPath()), true);

        if (! is_array($recorded)) {
            return [];
        }

        $modified = [];

        foreach ($recorded as $relative => $hash) {
            $path = base_path($relative);

            if (is_file($path) && sha1_file($path) !== $hash) {
                $modified[] = $relative;
            }
        }

        sort($modified);

        return $modified;
    }

    // Finalising ----------------------------------------------------------

    /**
     * Everything that must run as the new code, in a request of its own.
     *
     * @return array<string, string>
     */
    public function finalize(): array
    {
        $steps = [];

        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $steps['Database migrations'] = trim(\Illuminate\Support\Facades\Artisan::output()) ?: 'Nothing to migrate.';

        \Illuminate\Support\Facades\Artisan::call('cms:sync');
        $steps['Modules, gateways and settings'] = 'Reconciled with the new release.';

        $this->syncEnvExample();
        $steps['Environment file'] = 'New settings added; your existing values were left alone.';

        foreach (['cache:clear', 'config:clear', 'route:clear', 'view:clear'] as $command) {
            try {
                \Illuminate\Support\Facades\Artisan::call($command);
            } catch (\Throwable $e) {
                Log::warning("[updates] {$command} failed after update: ".$e->getMessage());
            }
        }

        settings()->flush();
        modules()->flush();
        themes()->flush();

        $steps['Caches'] = 'Cleared.';

        $this->recordChecksums();
        $this->resetOpcache();

        return $steps;
    }

    /**
     * Add keys the new release expects to .env, without touching what is there.
     *
     * Values already set - the database password, the gateway secrets - are
     * never rewritten. Only genuinely absent keys are appended, so a release
     * that introduces a setting does not leave the site running on a missing
     * one.
     */
    public function syncEnvExample(): int
    {
        $envPath = base_path('.env');
        $examplePath = base_path('.env.example');

        if (! is_file($envPath) || ! is_file($examplePath)) {
            return 0;
        }

        $env = File::get($envPath);
        $added = [];

        foreach (preg_split('/\R/', File::get($examplePath)) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            $key = trim(explode('=', $line, 2)[0]);

            if ($key === '' || preg_match('/^'.preg_quote($key, '/').'=/m', $env)) {
                continue;
            }

            $added[] = $line;
        }

        if ($added !== []) {
            File::put($envPath, rtrim($env)."\n\n# Added by the {$this->targetVersion()} update\n".implode("\n", $added)."\n");
        }

        return count($added);
    }

    private function targetVersion(): string
    {
        return (string) ($this->pending()['to'] ?? cms_version());
    }

    /**
     * Drop the compiled-code cache.
     *
     * Without this, PHP can go on serving the previous version of a file it
     * already compiled, which produces the very confusing situation of an
     * update that appears to have worked and changed nothing.
     */
    public function resetOpcache(): void
    {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /** Record the new version in the install lock, keeping the upgrade history. */
    public function stampVersion(string $from, string $to): void
    {
        $lock = config('cms.install_lock');
        $data = [];

        if (is_file($lock)) {
            $decoded = json_decode((string) File::get($lock), true);
            $data = is_array($decoded) ? $decoded : [];
        }

        $history = $data['upgrades'] ?? [];
        $history[] = ['from' => $from, 'to' => $to, 'at' => now()->toIso8601String()];

        $data['version'] = $to;
        $data['upgrades'] = array_slice($history, -20);

        File::put($lock, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function check(string $label, bool $passed, string $message, bool $fatal = true): array
    {
        return ['label' => $label, 'passed' => $passed, 'message' => $message, 'fatal' => $fatal];
    }
}
