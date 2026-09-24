<?php

namespace App\Cms\Transfer\Resources;

use App\Cms\Transfer\ExportContext;
use App\Cms\Transfer\ImportContext;
use App\Cms\Transfer\ImportReport;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;

class TagResource extends TransferResource
{
    public function key(): string
    {
        return 'tags';
    }

    public function label(): string
    {
        return 'Tags';
    }

    public function modelClass(): string
    {
        return Tag::class;
    }

    public function module(): ?string
    {
        return 'blog';
    }

    public function hint(): string
    {
        return 'Post tags. Tags a post uses are created automatically anyway.';
    }

    public function record(Model $model, ExportContext $context): array
    {
        /** @var Tag $model */
        return [
            'name' => $model->name,
            'slug' => $model->slug,
            'description' => $model->description,
        ] + $this->timestamps($model);
    }

    public function import(array $record, ImportContext $context): string
    {
        $slug = $record['slug'] ?? null;

        if (blank($slug) && blank($record['name'] ?? null)) {
            return ImportReport::SKIPPED;
        }

        $slug = $slug ?: str($record['name'])->slug()->value();
        $existing = Tag::where('slug', $slug)->first();

        if ($existing && ! $context->options->updatesExisting()) {
            return ImportReport::SKIPPED;
        }

        $tag = $existing ?: new Tag(['slug' => $slug]);

        $tag->fill([
            'name' => $record['name'] ?? $slug,
            'description' => $record['description'] ?? null,
        ]);

        $this->applyTimestamps($tag, $record);
        $tag->save();

        return $this->outcome(! $existing);
    }

    public function csvColumns(): array
    {
        return [
            'name' => 'Name',
            'slug' => 'Slug',
            'description' => 'Description',
        ];
    }
}
