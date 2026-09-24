<?php

namespace App\Console\Commands;

use App\Cms\Transfer\ExportOptions;
use App\Cms\Transfer\ExportService;
use App\Cms\Transfer\ResourceRegistry;
use App\Cms\Transfer\TransferException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * The export screen, without the browser.
 *
 * Which matters more than convenience: an export of a site with tens of
 * thousands of records takes longer than any sensible request timeout, and a
 * bundle with the media in it is bigger than most hosts will send. On the
 * command line neither is a problem.
 */
class ExportContentCommand extends Command
{
    protected $signature = 'cms:export
                            {--types= : Comma-separated list of what to export; default is everything}
                            {--ids= : Comma-separated ids to export; needs a single --types}
                            {--status= : Only records with this status}
                            {--from= : Only records on or after this date (YYYY-MM-DD)}
                            {--to= : Only records on or before this date}
                            {--format=zip : zip, json or csv}
                            {--no-media : Leave the image files out of the archive}
                            {--no-layouts : Leave builder layouts out}
                            {--output= : Where to write the file; defaults to the current directory}';

    protected $description = 'Export pages, posts, products and the rest as a portable bundle';

    public function handle(ExportService $exporter, ResourceRegistry $registry): int
    {
        $available = $registry->keys();

        $types = $this->option('types')
            ? array_values(array_filter(array_map('trim', explode(',', (string) $this->option('types')))))
            : $available;

        if ($unknown = array_diff($types, $available)) {
            $this->components->error('Not something this site can export: '.implode(', ', $unknown));
            $this->line('  Available: '.implode(', ', $available));

            return self::FAILURE;
        }

        $ids = $this->ids($types);

        if ($ids === false) {
            return self::FAILURE;
        }

        $this->components->info('Exporting: '.implode(', ', $types));

        try {
            $result = $exporter->run(ExportOptions::fromArray([
                'types' => $types,
                'ids' => $ids,
                'status' => $this->option('status'),
                'from' => $this->option('from'),
                'to' => $this->option('to'),
                'format' => (string) $this->option('format'),
                'include_media_files' => ! $this->option('no-media'),
                'include_layouts' => ! $this->option('no-layouts'),
            ]));
        } catch (TransferException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result->isEmpty()) {
            $this->components->warn('Nothing matched, so no file was written.');

            return self::SUCCESS;
        }

        $destination = $this->destination($result->filename);

        File::ensureDirectoryExists(dirname($destination));
        File::move($result->path, $destination);

        foreach ($result->counts as $type => $count) {
            $this->components->twoColumnDetail($type, (string) $count);
        }

        if ($result->mediaFiles > 0) {
            $this->components->twoColumnDetail('media files', (string) $result->mediaFiles);
        }

        $this->newLine();
        $this->components->info('Written to '.$destination.' ('.$this->size($destination).')');

        return self::SUCCESS;
    }

    /**
     * A named handful of records rather than everything.
     *
     * Only with one type, because an id means nothing without knowing what it
     * is the id of - "--ids=3,4" across posts and products would silently
     * export post 3 and product 3.
     *
     * @param  string[]  $types
     * @return array<string, int[]>|false
     */
    private function ids(array $types): array|false
    {
        $option = trim((string) $this->option('ids'));

        if ($option === '') {
            return [];
        }

        if (count($types) !== 1) {
            $this->components->error('--ids only works with a single --types, so there is no doubt what the ids belong to.');

            return false;
        }

        return [$types[0] => array_values(array_filter(array_map('intval', explode(',', $option))))];
    }

    private function destination(string $filename): string
    {
        $output = (string) $this->option('output');

        if ($output === '') {
            return getcwd().DIRECTORY_SEPARATOR.$filename;
        }

        // A directory keeps the generated name; anything else is taken as the
        // full path the person wants.
        return is_dir($output) ? rtrim($output, '/'.DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename : $output;
    }

    private function size(string $path): string
    {
        $bytes = (int) @filesize($path);
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
