<?php

namespace App\Cms\Transfer;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Reads an export back, whether it arrived as a ZIP or as the readable single
 * JSON document.
 *
 * Records stream: the archive form is read a line at a time, so importing a
 * large site costs one record of memory rather than all of them. The JSON form
 * is decoded whole, which is the trade it was offered for.
 *
 * Nothing here trusts the file. The archive is refused if it claims more
 * entries than the limits allow, a single entry that unpacks past the media
 * ceiling is abandoned, and a media path that tries to climb out of media/ is
 * simply not found.
 */
class BundleReader
{
    private ?ZipArchive $zip = null;

    private array $manifest = [];

    /** Decoded content for the single-document form. */
    private ?array $inline = null;

    private function __construct(private string $path) {}

    /**
     * @throws TransferException
     */
    public static function open(string $path): self
    {
        if (! is_file($path)) {
            throw new TransferException('That import file could not be read.');
        }

        $reader = new self($path);

        // The extension is a hint; what decides is whether it opens as a ZIP.
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) === true) {
            $reader->zip = $zip;
            $reader->readArchiveManifest();
        } else {
            $reader->readDocument();
        }

        $reader->assertSupported();

        return $reader;
    }

    public function manifest(): array
    {
        return $this->manifest;
    }

    public function sourceUrl(): ?string
    {
        return $this->manifest['site']['url'] ?? null;
    }

    /** @return array<string, int> Type key => how many records the manifest claims. */
    public function types(): array
    {
        $types = $this->manifest['types'] ?? [];

        if ($types === [] && $this->inline !== null) {
            $types = array_map(fn ($records) => is_array($records) ? count($records) : 0, $this->inline);
        }

        return array_filter(array_map('intval', (array) $types));
    }

    public function hasMediaFiles(): bool
    {
        return (bool) ($this->manifest['media_files'] ?? false);
    }

    /**
     * Every record of one type, in the order it was written.
     *
     * @return \Generator<array>
     */
    public function records(string $type): \Generator
    {
        if ($this->inline !== null) {
            foreach ($this->inline[$type] ?? [] as $record) {
                if (is_array($record)) {
                    yield $record;
                }
            }

            return;
        }

        $entry = $this->findEntry('content/'.$type.'.jsonl');

        if ($entry !== null) {
            $stream = $this->zip->getStream($entry);

            if (! $stream) {
                return;
            }

            while (($line = fgets($stream)) !== false) {
                $record = json_decode(trim($line), true);

                if (is_array($record)) {
                    yield $record;
                }
            }

            fclose($stream);

            return;
        }

        // A bundle written by hand may hold a plain array instead.
        $entry = $this->findEntry('content/'.$type.'.json');

        if ($entry !== null) {
            foreach ((array) json_decode((string) $this->zip->getFromName($entry), true) as $record) {
                if (is_array($record)) {
                    yield $record;
                }
            }
        }
    }

    /**
     * Unpacks one media file to a temporary path, and returns it.
     *
     * The caller deletes it. Nothing is written into the media disk from here:
     * that is the sideloader's job, and it runs the same guard an upload does.
     */
    public function extractMedia(string $path): ?string
    {
        if ($this->zip === null || $this->isUnsafe($path)) {
            return null;
        }

        $entry = $this->findEntry('media/'.ltrim($path, '/'));

        if ($entry === null) {
            return null;
        }

        $stream = $this->zip->getStream($entry);

        if (! $stream) {
            return null;
        }

        $target = TransferWorkspace::path('work').DIRECTORY_SEPARATOR.Str::random(12).'-'.basename($path);
        $out = fopen($target, 'w');

        $written = 0;
        $max = (int) config('transfer.download.max_bytes', 25 * 1024 * 1024);

        while (! feof($stream)) {
            $chunk = fread($stream, 8192);

            if ($chunk === false) {
                break;
            }

            $written += strlen($chunk);

            // A single entry that unpacks to more than one upload's worth is
            // not a photograph; it is a zip bomb.
            if ($max > 0 && $written > $max) {
                fclose($out);
                fclose($stream);
                @unlink($target);

                return null;
            }

            fwrite($out, $chunk);
        }

        fclose($out);
        fclose($stream);

        return $target;
    }

    public function close(): void
    {
        $this->zip?->close();
        $this->zip = null;
    }

    // Internals -----------------------------------------------------------

    /** @throws TransferException */
    private function readArchiveManifest(): void
    {
        $max = (int) config('transfer.max_archive_entries', 60000);

        if ($this->zip->numFiles > $max) {
            throw new TransferException('That archive holds more files than this site will unpack.');
        }

        $entry = $this->findEntry('manifest.json');

        if ($entry === null) {
            throw new TransferException('That ZIP is not a Radius export: it has no manifest.json.');
        }

        $decoded = json_decode((string) $this->zip->getFromName($entry), true);

        if (! is_array($decoded)) {
            throw new TransferException('The export manifest could not be read.');
        }

        $this->manifest = $decoded;
    }

    /** @throws TransferException */
    private function readDocument(): void
    {
        $decoded = json_decode((string) File::get($this->path), true);

        if (! is_array($decoded)) {
            throw new TransferException('That file is neither a Radius export archive nor readable JSON.');
        }

        // Both shapes are accepted: the document this exporter writes, and a
        // bare { "posts": [...] } somebody assembled themselves.
        $this->manifest = is_array($decoded['manifest'] ?? null) ? $decoded['manifest'] : [];
        $this->inline = is_array($decoded['content'] ?? null) ? $decoded['content'] : $decoded;
    }

    /** @throws TransferException */
    private function assertSupported(): void
    {
        $supported = (int) config('transfer.format', 1);
        $format = (int) ($this->manifest['format'] ?? $supported);

        if ($format > $supported) {
            throw new TransferException(
                'That export was made by a newer version of Radius (bundle format '.$format.'). Update this site first.'
            );
        }
    }

    /**
     * Finds an entry whatever it is wrapped in.
     *
     * An export unzipped and zipped again by a person picks up a folder - the
     * archive then holds "my-export/manifest.json" - and refusing that would
     * be pedantry, not safety.
     */
    private function findEntry(string $wanted): ?string
    {
        if ($this->zip === null) {
            return null;
        }

        if ($this->zip->locateName($wanted) !== false) {
            return $wanted;
        }

        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $raw = (string) $this->zip->getNameIndex($i);
            $name = str_replace(chr(92), '/', $raw);

            if ($name === $wanted || str_ends_with($name, '/'.$wanted)) {
                return $raw;
            }
        }

        return null;
    }

    private function isUnsafe(string $path): bool
    {
        $normalised = str_replace(chr(92), '/', $path);

        return $normalised === ''
            || str_starts_with($normalised, '/')
            || str_contains($normalised, '../')
            || preg_match('/^[a-zA-Z]:/', $normalised) === 1;
    }
}
