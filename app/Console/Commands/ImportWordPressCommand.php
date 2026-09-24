<?php

namespace App\Console\Commands;

use App\Cms\Transfer\ImportOptions;
use App\Cms\Transfer\TransferException;
use App\Cms\Transfer\WordPress\WordPressImporter;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Imports a WordPress export file.
 *
 * The version to use for a real migration. Fetching the images means one HTTP
 * request per picture, and a blog with four thousand of them will be at it for
 * an hour - which is fine here and impossible in a browser.
 */
class ImportWordPressCommand extends Command
{
    use ReportsAnImport;

    protected $signature = 'cms:import-wordpress
                            {file : Path to the WordPress .xml (WXR) export}
                            {--types= : Comma-separated list of what to import; default is everything in the file}
                            {--update : Overwrite records that already exist, instead of skipping them}
                            {--status= : Force everything to draft or published}
                            {--author= : Email of the account to own posts whose writer is not on this site}
                            {--create-authors : Create accounts for WordPress authors this site has not got}
                            {--media : Fetch the image files from the old site}
                            {--dry-run : Read the file and report what it holds, without importing}';

    protected $description = 'Import posts, pages, products and media from a WordPress export';

    public function handle(WordPressImporter $importer): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->components->error('No such file: '.$file);

            return self::FAILURE;
        }

        try {
            $analysis = $importer->inspect($file);

            $this->components->info('Reading an export of '.($analysis['site']['title'] ?: 'a WordPress site'));

            foreach ($analysis['counts'] as $type => $count) {
                $this->components->twoColumnDetail($type, (string) $count);
            }

            if ($analysis['comments'] > 0) {
                $this->components->twoColumnDetail('comments', (string) $analysis['comments']);
            }

            if ($this->option('dry-run')) {
                return self::SUCCESS;
            }

            $author = $this->author();

            if ($author === false) {
                return self::FAILURE;
            }

            if (! $this->option('media')) {
                $this->components->warn('Images will not be fetched. Pass --media to bring the pictures across.');
            }

            $this->newLine();

            $report = $importer->run($file, ImportOptions::fromArray([
                'types' => $this->option('types')
                    ? array_map('trim', explode(',', (string) $this->option('types')))
                    : null,
                'mode' => $this->option('update') ? ImportOptions::UPDATE_EXISTING : ImportOptions::SKIP_EXISTING,
                'status' => $this->option('status'),
                'author_id' => $author,
                'create_authors' => (bool) $this->option('create-authors'),
                'import_media' => true,
                'download_media' => (bool) $this->option('media'),
            ]), timed: false);
        } catch (TransferException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return $this->printReport($report);
    }

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
