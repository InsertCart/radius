<?php

namespace App\Cms\Updates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Database snapshots taken before an update.
 *
 * Written in plain PHP rather than by shelling out to mysqldump, because
 * exec() and proc_open() are disabled on most shared hosting - which is exactly
 * where an update is most likely to go wrong and a backup matters most.
 *
 * The dump is gzipped as it is produced and rows are read in chunks, so a site
 * with a large orders table does not have to fit its own database into the PHP
 * memory limit.
 */
class BackupService
{
    public function directory(): string
    {
        $path = storage_path('app/private/'.trim((string) config('updates.backups.directory', 'backups'), '/'));

        File::ensureDirectoryExists($path);

        return $path;
    }

    /**
     * Dump every table to a gzipped .sql file and return its path.
     *
     * @throws UpdateException
     */
    public function dumpDatabase(string $label = 'update'): string
    {
        $name = 'db-'.$label.'-'.now()->format('Ymd-His').'.sql.gz';
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        $handle = gzopen($path, 'wb9');

        if (! $handle) {
            throw new UpdateException('The backup file could not be created. Check that storage/app is writable.');
        }

        try {
            $database = DB::getDatabaseName();
            $tables = $this->tables();

            gzwrite($handle, "-- ".config('cms.name', 'CMS')." database backup\n");
            gzwrite($handle, "-- Database: {$database}\n");
            gzwrite($handle, '-- Taken: '.now()->toDateTimeString()."\n");
            gzwrite($handle, '-- CMS version: '.cms_version()."\n");

            // The list of tables as they were. A restore uses it to drop
            // anything created afterwards - a migration from an update being
            // rolled back, typically - which the INSERT statements below could
            // never undo on their own.
            gzwrite($handle, '-- TABLES: '.implode(',', $tables)."\n\n");

            // Constraints are relaxed for the duration of a restore so tables
            // can be recreated in any order without foreign keys complaining
            // about rows that have not been inserted yet.
            gzwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            gzwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            foreach ($tables as $table) {
                $this->dumpTable($handle, $table);
            }

            gzwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        } catch (\Throwable $e) {
            gzclose($handle);
            @unlink($path);

            throw new UpdateException('The database backup failed: '.$e->getMessage());
        }

        gzclose($handle);

        $this->prune();

        return $path;
    }

    /** @return string[] */
    private function tables(): array
    {
        return array_map(
            fn ($row) => array_values((array) $row)[0],
            DB::select('SHOW TABLES')
        );
    }

    /** @param resource $handle */
    private function dumpTable($handle, string $table): void
    {
        $quoted = '`'.str_replace('`', '``', $table).'`';

        $create = (array) DB::selectOne("SHOW CREATE TABLE {$quoted}");
        $sql = $create['Create Table'] ?? $create['Create View'] ?? null;

        if (! $sql) {
            return;
        }

        gzwrite($handle, "\n-- Table: {$table}\n");
        gzwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n");
        gzwrite($handle, $sql.";\n\n");

        // Views have no rows of their own.
        if (isset($create['Create View'])) {
            return;
        }

        $chunk = max(50, (int) config('updates.backups.dump_chunk', 500));
        $offset = 0;

        while (true) {
            $rows = DB::select("SELECT * FROM {$quoted} LIMIT {$chunk} OFFSET {$offset}");

            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                gzwrite($handle, $this->insertStatement($quoted, (array) $row));
            }

            $offset += $chunk;
        }
    }

    private function insertStatement(string $quotedTable, array $row): string
    {
        $columns = array_map(fn ($c) => '`'.str_replace('`', '``', $c).'`', array_keys($row));

        $values = array_map(function ($value) {
            if ($value === null) {
                return 'NULL';
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }

            // Quoted through PDO so that binary blobs, newlines and quotes in
            // page content all survive a round trip intact.
            return DB::getPdo()->quote((string) $value);
        }, array_values($row));

        return 'INSERT INTO '.$quotedTable.' ('.implode(', ', $columns).') VALUES ('.implode(', ', $values).");\n";
    }

    /**
     * Restore a gzipped dump.
     *
     * Statements are executed one at a time as they are read, so restoring does
     * not require the whole dump in memory either.
     *
     * @throws UpdateException
     */
    public function restoreDatabase(string $path): void
    {
        if (! is_file($path)) {
            throw new UpdateException('That backup file no longer exists.');
        }

        $handle = gzopen($path, 'rb');

        if (! $handle) {
            throw new UpdateException('The backup file could not be opened.');
        }

        $statement = '';
        $knownTables = null;

        try {
            DB::unprepared('SET FOREIGN_KEY_CHECKS=0');

            while (! gzeof($handle)) {
                $line = gzgets($handle);

                if ($line === false) {
                    break;
                }

                $trimmed = trim($line);

                if (str_starts_with($trimmed, '-- TABLES:')) {
                    $knownTables = array_filter(array_map('trim', explode(',', substr($trimmed, 10))));
                    $this->dropTablesAddedSince($knownTables);

                    continue;
                }

                if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                    continue;
                }

                $statement .= $line;

                // Statements are written one per line by dumpTable, so a line
                // ending in a semicolon ends the statement.
                if (str_ends_with($trimmed, ';')) {
                    DB::unprepared($statement);
                    $statement = '';
                }
            }
        } catch (\Throwable $e) {
            throw new UpdateException('The database could not be restored: '.$e->getMessage());
        } finally {
            gzclose($handle);

            try {
                DB::unprepared('SET FOREIGN_KEY_CHECKS=1');
            } catch (\Throwable $e) {
                // Nothing useful to do; the connection is about to be dropped.
            }
        }
    }

    /**
     * Drop tables that did not exist when the backup was taken.
     *
     * The dump can recreate and refill everything it captured, but it knows
     * nothing about tables created later, so those would survive a restore and
     * leave the schema ahead of the code. The common case is a migration from
     * the very update being rolled back: left in place, it would collide with
     * itself the next time the update is attempted.
     *
     * @param  string[]  $knownTables
     */
    private function dropTablesAddedSince(array $knownTables): void
    {
        if ($knownTables === []) {
            return;
        }

        foreach ($this->tables() as $table) {
            if (in_array($table, $knownTables, true)) {
                continue;
            }

            try {
                DB::unprepared('DROP TABLE IF EXISTS `'.str_replace('`', '``', $table).'`');
            } catch (\Throwable $e) {
                Log::warning("[updates] Could not drop {$table} during restore: ".$e->getMessage());
            }
        }
    }

    /** @return array<int, array{name: string, path: string, size: int, created_at: \Illuminate\Support\Carbon}> */
    public function all(): array
    {
        $backups = [];

        foreach (File::glob($this->directory().DIRECTORY_SEPARATOR.'db-*.sql.gz') as $path) {
            $backups[] = [
                'name' => basename($path),
                'path' => $path,
                'size' => (int) filesize($path),
                'created_at' => \Illuminate\Support\Carbon::createFromTimestamp(filemtime($path)),
            ];
        }

        usort($backups, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    /** Resolve a backup by filename, refusing anything outside the folder. */
    public function find(string $name): ?string
    {
        // basename() strips any traversal attempt in the submitted name before
        // it is ever joined to a path.
        $path = $this->directory().DIRECTORY_SEPARATOR.basename($name);

        return is_file($path) && str_ends_with($path, '.sql.gz') ? $path : null;
    }

    public function delete(string $name): bool
    {
        $path = $this->find($name);

        return $path ? @unlink($path) : false;
    }

    /** Keep the newest few; old dumps are dead weight on a small hosting plan. */
    private function prune(): void
    {
        $keep = max(1, (int) config('updates.backups.keep', 5));

        foreach (array_slice($this->all(), $keep) as $old) {
            try {
                @unlink($old['path']);
            } catch (\Throwable $e) {
                Log::warning('[updates] Could not remove old backup '.$old['name']);
            }
        }
    }
}
