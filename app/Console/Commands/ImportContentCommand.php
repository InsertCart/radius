<?php

namespace App\Console\Commands;

use App\Cms\Transfer\ImportOptions;
use App\Cms\Transfer\ImportService;
use App\Cms\Transfer\TransferException;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Applies a Radius bundle from the command line.
 *
 * No time limit here, which is the point: the web importer stops itself after
 * ten minutes so a request cannot hang, and a site of any size needs longer
 * than that.
 */
class ImportContentCommand extends Command
{
    use ReportsAnImport;

    protected $signature = 'cms:import
                            {file : Path to the .zip or .json export}
                            {--types= : Comma-separated list of what to import; default is everything in the file}
                            {--update : Overwrite records that already exist, instead of skipping them}
                            {--status= : Force every imported record to draft or published}
                            {--author= : Email of the account to own content whose author is not on this site}
                            {--create-authors : Create accounts for authors this site has not got}
                            {--no-media : Do not touch the media library}
                            {--download : Fetch images the bundle does not carry from the old site}
                            {--no-layouts : Skip builder layouts}
                            {--dry-run : Read the file and report what it holds, without importing}';

    protected $description = 'Import content from a Radius export bundle';

    public function handle(ImportService $importer): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->components->error('No such file: '.$file);

            return self::FAILURE;
        }

        try {
            if ($this->option('dry-run')) {
                return $this->describe($importer->inspect($file));
            }

            $author = $this->author();

            if ($author === false) {
                return self::FAILURE;
            }

            $report = $importer->run($file, ImportOptions::fromArray([
                'types' => $this->option('types')
                    ? array_map('trim', explode(',', (string) $this->option('types')))
                    : null,
                'mode' => $this->option('update') ? ImportOptions::UPDATE_EXISTING : ImportOptions::SKIP_EXISTING,
                'status' => $this->option('status'),
                'author_id' => $author,
                'create_authors' => (bool) $this->option('create-authors'),
                'import_media' => ! $this->option('no-media'),
                'download_media' => (bool) $this->option('download'),
                'include_layouts' => ! $this->option('no-layouts'),
            ]), timed: false);
        } catch (TransferException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return $this->printReport($report);
    }

    private function describe(array $analysis): int
    {
        $this->components->info('This file holds:');

        foreach ($analysis['types'] as $type => $count) {
            $this->components->twoColumnDetail($type, (string) $count);
        }

        if ($analysis['unknown'] !== []) {
            $this->newLine();
            $this->components->warn('Not read by this version: '.implode(', ', $analysis['unknown']));
        }

        $this->newLine();
        $this->components->twoColumnDetail('image files included', $analysis['media'] ? 'yes' : 'no');

        return self::SUCCESS;
    }

    /** @return int|null|false The owner's id, null for none, or false when the email is wrong. */
    private function author(): int|null|false
    {
        $email = (string) $this->option('author');

        if ($email === '') {
            return User::where('role', User::ROLE_ADMIN)->orderBy('id')->value('id');
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->components->error('No account here has the address '.$email);

            return false;
        }

        return $user->id;
    }
}
