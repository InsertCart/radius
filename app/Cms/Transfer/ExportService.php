<?php

namespace App\Cms\Transfer;

use App\Models\Media;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Writes the site's content out as a portable bundle.
 *
 * Three shapes, one pass. A ZIP holds the records and the files and is what a
 * migration wants. A single JSON document holds the records alone and is what
 * you read, diff or check into a repository. A CSV is a spreadsheet, and comes
 * with the honest caveat that a spreadsheet cannot hold a menu tree.
 *
 * Nothing is held in memory but the record being written. A blog with forty
 * thousand posts exports on a 128 MB shared host, which is the whole reason
 * the archive stores one JSON object per line.
 */
class ExportService
{
    public function __construct(private ResourceRegistry $registry) {}

    /**
     * @throws TransferException
     */
    public function run(ExportOptions $options): ExportResult
    {
        $selected = $this->registry->ordered($options->types);

        if ($selected === []) {
            throw new TransferException('Choose at least one kind of content to export.');
        }

        TransferWorkspace::sweep();

        $workdir = TransferWorkspace::scratch('export');
        $writer = new BundleWriter($workdir);
        $context = new ExportContext($options);

        try {
            foreach ($selected as $key => $resource) {
                // Media is written last, because by then every other resource
                // has said which files it needs.
                if ($key === 'media') {
                    continue;
                }

                $writer->open($key);

                foreach ($resource->query($options) as $model) {
                    $writer->add($resource->record($model, $context));
                }

                $writer->close();
            }

            $this->writeMedia($writer, $context, $options, isset($selected['media']));

            if ($options->format === 'csv') {
                return $this->csv($workdir, $writer);
            }

            if ($options->bundlesFiles()) {
                $this->packFiles($writer, $context);
            }

            $manifest = $this->manifest($writer, $options);
            $writer->writeManifest($manifest);

            $stamp = now()->format('Y-m-d-His');

            if ($options->format === 'json') {
                $target = TransferWorkspace::file('exports', 'radius-export-'.$stamp.'.json');
                $writer->json($target, $manifest);
            } else {
                $target = TransferWorkspace::file('exports', 'radius-export-'.$stamp.'.zip');
                $writer->zip($target);
            }

            return new ExportResult($target, basename($target), $writer->counts(), $writer->mediaCount());
        } finally {
            $writer->cleanup();
        }
    }

    /**
     * The media manifest: everything the person ticked, plus every file the
     * rest of the export pointed at.
     *
     * The second half is what makes "just export my posts" work. Somebody
     * moving a blog does not think of the header images as a separate thing to
     * remember, and an import that arrives without them looks broken.
     */
    private function writeMedia(BundleWriter $writer, ExportContext $context, ExportOptions $options, bool $explicit): void
    {
        $resource = $this->registry->get('media');

        if (! $resource) {
            return;
        }

        // A spreadsheet export is a list somebody asked for, not a migration.
        // Quietly adding a media sheet because a post has a header image would
        // turn "export my posts as CSV" into a ZIP of two files.
        if ($options->format === 'csv' && ! $explicit) {
            return;
        }

        $seen = [];

        $writer->open('media');

        if ($explicit) {
            foreach ($resource->query($options) as $model) {
                $writer->add($resource->record($model, $context));
                $seen[$model->path] = true;
            }
        }

        foreach ($context->collected() as $path) {
            if (isset($seen[$path])) {
                continue;
            }

            $seen[$path] = true;

            $media = Media::where('path', $path)->first();

            $writer->add($media
                ? $resource->record($media, $context)
                // A path with no library row behind it - an image set by hand
                // in a settings field. The file still travels; there is just
                // nothing else to say about it.
                : ['path' => $path, 'name' => basename($path)]);
        }

        $writer->close();
    }

    /**
     * Copies the referenced files into the staging folder.
     *
     * Read through the filesystem rather than by path, because the media disk
     * may well be a bucket: the CDN module exists precisely so that it can be.
     */
    private function packFiles(BundleWriter $writer, ExportContext $context): void
    {
        $disk = Storage::disk(config('cms.media.disk'));

        foreach ($context->collected() as $path) {
            try {
                if ($disk->exists($path)) {
                    $writer->addMedia($path, (string) $disk->get($path));
                }
            } catch (\Throwable $e) {
                // A file the database remembers and the disk has lost is a
                // pre-existing problem, not a reason to fail the export.
                continue;
            }
        }
    }

    private function manifest(BundleWriter $writer, ExportOptions $options): array
    {
        return array_filter([
            'format' => (int) config('transfer.format', 1),
            'generator' => config('cms.name', 'Radius'),
            'generator_version' => cms_version(),
            'generated_at' => now()->toIso8601String(),
            'site' => [
                'name' => setting('site_name', config('app.name')),
                'url' => rtrim(url('/'), '/'),
            ],
            'currency' => setting('shop_currency', 'USD'),
            'media_url' => rtrim(Storage::disk(config('cms.media.disk'))->url(''), '/'),
            'types' => $writer->counts(),
            'media_files' => $writer->mediaCount() > 0,
            'filters' => array_filter([
                'status' => $options->status,
                'from' => $options->from,
                'to' => $options->to,
            ]),
        ], fn ($value) => $value !== [] && $value !== null);
    }

    /**
     * The spreadsheet form: one file per type, zipped when there is more than
     * one, because a browser can only be handed a single download.
     */
    private function csv(string $workdir, BundleWriter $writer): ExportResult
    {
        $counts = $writer->counts();
        $folder = $workdir.DIRECTORY_SEPARATOR.'csv';
        File::ensureDirectoryExists($folder);

        $written = [];

        foreach ($counts as $type => $count) {
            $resource = $this->registry->get($type);

            if (! $resource || ! $resource->supportsCsv()) {
                continue;
            }

            $source = $workdir.DIRECTORY_SEPARATOR.'content'.DIRECTORY_SEPARATOR.$type.'.jsonl';

            if (! is_file($source)) {
                continue;
            }

            $target = $folder.DIRECTORY_SEPARATOR.$type.'.csv';
            $out = fopen($target, 'w');

            // Excel reads a CSV as the system codepage unless the file says
            // otherwise, and a byte order mark is the only thing it listens to.
            fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, array_values($resource->csvColumns()));

            $in = fopen($source, 'r');

            while (($line = fgets($in)) !== false) {
                $record = json_decode(trim($line), true);

                if (is_array($record)) {
                    fputcsv($out, $resource->csvRow($record));
                }
            }

            fclose($in);
            fclose($out);

            $written[$type] = $target;
        }

        if ($written === []) {
            throw new TransferException('None of the types you chose can be written as a spreadsheet. Export them as a bundle instead.');
        }

        $stamp = now()->format('Y-m-d-His');

        if (count($written) === 1) {
            $type = array_key_first($written);
            $target = TransferWorkspace::file('exports', Str::slug(setting('site_name', 'radius')).'-'.$type.'-'.$stamp.'.csv');

            File::copy($written[$type], $target);

            return new ExportResult($target, basename($target), [$type => $counts[$type]]);
        }

        $target = TransferWorkspace::file('exports', 'radius-csv-'.$stamp.'.zip');

        $zip = new \ZipArchive;

        if ($zip->open($target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new TransferException('The export file could not be created. Check that storage/ is writable.');
        }

        foreach ($written as $type => $path) {
            $zip->addFile($path, $type.'.csv');
        }

        $zip->close();

        return new ExportResult($target, basename($target), array_intersect_key($counts, $written));
    }
}
