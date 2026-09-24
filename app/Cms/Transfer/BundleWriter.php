<?php

namespace App\Cms\Transfer;

use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Assembles an export on disk.
 *
 * Records are written one JSON object per line rather than as one big array,
 * so a site with forty thousand posts is exported with one post in memory at a
 * time. The same shape reads back the same way. That is the whole reason for
 * the format: the CMS is built for shared hosting, where a 256 MB limit is
 * normal and json_encode() of an entire site is not.
 *
 * The single-file .json export is assembled from the same lines at the end.
 * It is the readable format, offered for small sites and for anybody who wants
 * to look at what they are about to import.
 */
class BundleWriter
{
    /** @var resource|null */
    private $handle = null;

    private string $current = '';

    private array $counts = [];

    private array $media = [];

    public function __construct(private string $workdir)
    {
        File::ensureDirectoryExists($workdir.DIRECTORY_SEPARATOR.'content');
    }

    public function open(string $type): void
    {
        $this->close();

        $this->current = $type;
        $this->counts[$type] ??= 0;
        $this->handle = fopen($this->workdir.DIRECTORY_SEPARATOR.'content'.DIRECTORY_SEPARATOR.$type.'.jsonl', 'w');
    }

    public function add(array $record): void
    {
        if (! $this->handle) {
            return;
        }

        // Unicode and slashes are left as they are: a bundle is read by people
        // as well as by this importer, and escaped Devanagari helps nobody.
        fwrite($this->handle, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)."\n");

        $this->counts[$this->current]++;
    }

    public function close(): void
    {
        if ($this->handle) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return array_filter($this->counts);
    }

    /**
     * Stages one media file for the archive.
     *
     * Read through the filesystem rather than copied by path, because the disk
     * may be a bucket on the other side of the internet - the whole point of
     * the CDN module - and there is no local file to copy in that case.
     */
    public function addMedia(string $path, string $contents): void
    {
        $target = $this->workdir.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.$path;

        File::ensureDirectoryExists(dirname($target));
        File::put($target, $contents);

        $this->media[] = $path;
    }

    public function mediaCount(): int
    {
        return count($this->media);
    }

    public function writeManifest(array $manifest): void
    {
        File::put(
            $this->workdir.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Packs everything staged into a ZIP at $target.
     *
     * @throws TransferException
     */
    public function zip(string $target): string
    {
        $this->close();

        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new TransferException('The export file could not be created. Check that storage/ is writable.');
        }

        foreach (File::allFiles($this->workdir) as $file) {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());
            $zip->addFile($file->getPathname(), $relative);
        }

        $zip->close();

        return $target;
    }

    /** Writes the readable single-document form instead. */
    public function json(string $target, array $manifest): string
    {
        $this->close();

        $content = [];

        foreach (array_keys($this->counts()) as $type) {
            $content[$type] = [];

            foreach ($this->read($type) as $record) {
                $content[$type][] = $record;
            }
        }

        File::put($target, json_encode([
            'manifest' => $manifest,
            'content' => $content,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

        return $target;
    }

    private function read(string $type): \Generator
    {
        $path = $this->workdir.DIRECTORY_SEPARATOR.'content'.DIRECTORY_SEPARATOR.$type.'.jsonl';

        if (! is_file($path)) {
            return;
        }

        $handle = fopen($path, 'r');

        while (($line = fgets($handle)) !== false) {
            $record = json_decode(trim($line), true);

            if (is_array($record)) {
                yield $record;
            }
        }

        fclose($handle);
    }

    public function cleanup(): void
    {
        $this->close();
        File::deleteDirectory($this->workdir);
    }
}
