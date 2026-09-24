<?php

namespace App\Cms\Transfer;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Applies a bundle to this site.
 *
 * Record by record, not all or nothing. A transaction around forty thousand
 * posts would hold locks for minutes and fall over on the first bad date, and
 * the person would be left with nothing and no idea which row was to blame.
 * Instead every record stands or falls on its own and the report says exactly
 * what happened to each type.
 *
 * Two passes. The first creates everything; the second joins up the links that
 * point inside a type - a category's parent, a page's parent - which cannot be
 * resolved on the way past because the target may not exist yet.
 */
class ImportService
{
    public function __construct(
        private ResourceRegistry $registry,
        private MediaSideloader $sideloader,
    ) {}

    /**
     * Reads a bundle without changing anything, for the confirmation screen.
     *
     * @return array{manifest: array, types: array<string, int>, unknown: string[], media: bool}
     *
     * @throws TransferException
     */
    public function inspect(string $path): array
    {
        $reader = BundleReader::open($path);

        try {
            $types = $reader->types();
            $known = $this->registry->keys();

            return [
                'manifest' => $reader->manifest(),
                'types' => array_intersect_key($types, array_flip($known)),
                // Named so the screen can say "this bundle also holds orders,
                // which this site cannot import" rather than quietly dropping
                // them and leaving somebody to notice next week.
                'unknown' => array_values(array_diff(array_keys($types), $known)),
                'media' => $reader->hasMediaFiles(),
            ];
        } finally {
            $reader->close();
        }
    }

    /**
     * @param  bool  $timed  True when a browser is waiting, which caps the run.
     *
     * @throws TransferException
     */
    public function run(string $path, ImportOptions $options, bool $timed = true): ImportReport
    {
        $reader = BundleReader::open($path);
        $report = new ImportReport;

        $context = new ImportContext($options, $report, $this->sideloader);
        $context->bundle = $reader;
        $context->sourceUrl = $reader->sourceUrl();

        if ($timed) {
            $context->limitTo((int) config('transfer.web_time_limit', 600));
        }

        if (! $reader->hasMediaFiles() && $options->importMedia && ! $options->downloadMedia) {
            $report->warn('This export does not carry its files, so images were linked only where this site already had them. Re-run with "fetch missing images" to pull them from the old site.');
        }

        try {
            $available = $reader->types();

            foreach ($this->registry->available() as $key => $resource) {
                if (! $options->wants($key) || ! isset($available[$key])) {
                    continue;
                }

                $resource->beforeImport($context);

                foreach ($reader->records($key) as $record) {
                    if ($context->outOfTime()) {
                        $report->stoppedEarly = true;
                        break 2;
                    }

                    try {
                        $report->record($key, $resource->import($record, $context));
                    } catch (\Throwable $e) {
                        $report->fail($key, $this->describe($record).': '.$e->getMessage());

                        Log::warning('Import failed for one record.', [
                            'type' => $key,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $this->linkPass($reader, $options, $context);
        } finally {
            $reader->close();
        }

        if ($report->stoppedEarly) {
            $report->warn('The import stopped at the time limit. Run it again to carry on from where it left off, or use "php artisan cms:import" for a large site.');
        }

        activity('content.imported', 'Imported content from a bundle. '.$report->summary());

        return $report;
    }

    /**
     * Joins up parents once every record of a type exists.
     *
     * Cheap: only the two types that nest are re-read, and only their slug and
     * parent are looked at.
     */
    private function linkPass(BundleReader $reader, ImportOptions $options, ImportContext $context): void
    {
        $available = $reader->types();

        foreach ($this->registry->available() as $key => $resource) {
            if (! $resource->needsLinkPass() || ! $options->wants($key) || ! isset($available[$key])) {
                continue;
            }

            foreach ($reader->records($key) as $record) {
                try {
                    $resource->link($record, $context);
                } catch (\Throwable $e) {
                    $context->report->note($key, 'Could not restore the parent of "'.$this->describe($record).'".');
                }
            }
        }
    }

    /** Something to call a record in a message, whatever type it is. */
    private function describe(array $record): string
    {
        foreach (['title', 'name', 'label', 'code', 'slug', 'path'] as $field) {
            if (filled($record[$field] ?? null) && is_string($record[$field])) {
                return Str::limit($record[$field], 60);
            }
        }

        return 'a record';
    }
}
