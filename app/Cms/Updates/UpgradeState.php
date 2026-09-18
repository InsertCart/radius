<?php

namespace App\Cms\Updates;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\File;

/**
 * Whether the database has caught up with the code that is running.
 *
 * The in-panel updater and `php artisan cms:update` run the migrations
 * themselves. Plenty of buyers update the only way their hosting allows -
 * unzipping the release over the site with an FTP client or the file manager -
 * and nothing runs afterwards. The site is then new code on an old database,
 * which breaks the moment a page reads a column that does not exist yet, with
 * nothing to say why.
 *
 * So the admin panel asks this on every page and offers to finish the job.
 */
class UpgradeState
{
    public function __construct(private Migrator $migrator) {}

    /** Migrations shipped with the code that this database has not run. */
    public function pendingMigrations(): array
    {
        if (! $this->migrator->repositoryExists()) {
            return [];
        }

        $ran = $this->migrator->getRepository()->getRan();

        $files = array_keys($this->migrator->getMigrationFiles(
            $this->migrator->paths() ?: [database_path('migrations')]
        ));

        return array_values(array_diff($files, $ran));
    }

    /**
     * The version recorded when setup or the last update finished. A release
     * unzipped over the site moves the code's version but not this one.
     */
    public function recordedVersion(): ?string
    {
        $lock = config('cms.install_lock');

        if (! is_file($lock)) {
            return null;
        }

        $data = json_decode((string) File::get($lock), true);

        return is_array($data) ? ($data['version'] ?? null) : null;
    }

    public function versionChanged(): bool
    {
        $recorded = $this->recordedVersion();

        return $recorded !== null && $recorded !== cms_version();
    }

    /** True when the files are newer than the database, whatever put them there. */
    public function needsFinishing(): bool
    {
        if (! cms_installed()) {
            return false;
        }

        try {
            return $this->pendingMigrations() !== [] || $this->versionChanged();
        } catch (\Throwable) {
            // A database that cannot even be asked is not a question for a
            // banner on every admin page.
            return false;
        }
    }
}
