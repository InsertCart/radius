<?php

namespace App\Cms\Transfer\Resources;

use App\Cms\Transfer\ExportContext;
use App\Cms\Transfer\ImportContext;
use App\Cms\Transfer\ImportReport;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;

/**
 * The media library.
 *
 * A record names a file; whether the file travels with it depends on the
 * format. A ZIP carries the bytes. A JSON export carries the address the old
 * site served them from instead, so the pictures can still be fetched later if
 * that site is still up.
 *
 * Only originals are exported. Thumbnails are regenerated on the way in, which
 * keeps the archive a third of the size and means an import ends up with the
 * sizes *this* site is configured for rather than the old one's.
 */
class MediaResource extends TransferResource
{
    public function key(): string
    {
        return 'media';
    }

    public function label(): string
    {
        return 'Media library';
    }

    public function modelClass(): string
    {
        return Media::class;
    }

    public function module(): ?string
    {
        return 'media';
    }

    public function hint(): string
    {
        return 'Uploaded files. Images used by the content you tick are always included, even without this.';
    }

    public function record(Model $model, ExportContext $context): array
    {
        /** @var Media $model */
        $context->file($model->path);

        return array_filter([
            'name' => $model->name,
            'file_name' => $model->file_name,
            'mime_type' => $model->mime_type,
            'extension' => $model->extension,
            'size' => $model->size,
            'path' => $model->path,
            'width' => $model->width,
            'height' => $model->height,
            'alt' => $model->alt,
            'title' => $model->title,
            // The address this site serves it from, so an export without the
            // files can still be filled in from the old server.
            'url' => $model->url,
        ] + $this->timestamps($model), fn ($value) => $value !== null);
    }

    public function import(array $record, ImportContext $context): string
    {
        $path = $record['path'] ?? null;
        $url = $record['url'] ?? null;

        if (blank($path) && blank($url)) {
            return ImportReport::SKIPPED;
        }

        $media = $context->media($path, $url, count: false);

        if (! $media) {
            // Not a failure worth shouting about when the person deliberately
            // exported records without files and left downloading switched off.
            return ImportReport::SKIPPED;
        }

        $created = $context->justCreated();

        if (! $created && ! $context->options->updatesExisting()) {
            return ImportReport::SKIPPED;
        }

        // The descriptive fields are the part worth carrying across on an
        // update: alt text is work somebody did, and re-uploading the file
        // would only make a second copy.
        $media->fill(array_filter([
            'name' => $record['name'] ?? null,
            'alt' => $record['alt'] ?? null,
            'title' => $record['title'] ?? null,
        ], fn ($value) => filled($value)))->save();

        return $this->outcome($created);
    }

    public function csvColumns(): array
    {
        return [
            'name' => 'Name',
            'file_name' => 'File',
            'path' => 'Path',
            'mime_type' => 'Type',
            'size' => 'Bytes',
            'alt' => 'Alt text',
            'url' => 'URL',
            'created_at' => 'Uploaded',
        ];
    }
}
