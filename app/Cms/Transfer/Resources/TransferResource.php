<?php

namespace App\Cms\Transfer\Resources;

use App\Cms\Transfer\ExportContext;
use App\Cms\Transfer\ExportOptions;
use App\Cms\Transfer\ImportContext;
use App\Cms\Transfer\ImportReport;
use App\Models\Layout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One kind of thing that can be exported and imported: posts, products, menus.
 *
 * Records identify each other by slug rather than by id. That is the single
 * decision the whole format turns on. Ids are local facts - post 14 here is
 * somebody else's post 14 - so a bundle keyed by them can only ever be
 * restored onto the site it came from. Keyed by slug, the same file seeds a
 * staging site, moves a blog to a new host, or ships a starter kit.
 */
abstract class TransferResource
{
    /** The key used in the manifest, the archive filenames and the checkboxes. */
    abstract public function key(): string;

    abstract public function label(): string;

    /** @return class-string<Model> */
    abstract public function modelClass(): string;

    /** Hidden when its module is off, the same way the admin sections are. */
    public function module(): ?string
    {
        return null;
    }

    /** One line under the checkbox on the export screen. */
    public function hint(): string
    {
        return '';
    }

    /**
     * Turns one stored record into the array the bundle carries.
     *
     * @return array<string, mixed>
     */
    abstract public function record(Model $model, ExportContext $context): array;

    /**
     * Applies one record from a bundle to this site.
     *
     * Returns one of the ImportReport outcome constants. Throwing is allowed
     * and is caught by the import service, which counts it as a failure and
     * moves to the next record.
     */
    abstract public function import(array $record, ImportContext $context): string;

    /**
     * Called once before this type's records are read, so a resource can
     * clear anything it remembers between runs. The registry hands out one
     * instance per process, and a second import in the same command must not
     * inherit the first one's id maps.
     */
    public function beforeImport(ImportContext $context): void {}

    /**
     * Whether this type needs a second pass once every record exists.
     *
     * Only for links within a type: a category whose parent appears later in
     * the same file cannot be joined up on the way past.
     */
    public function needsLinkPass(): bool
    {
        return false;
    }

    public function link(array $record, ImportContext $context): void {}

    // Export ---------------------------------------------------------------

    /** @return \Generator<Model> */
    public function query(ExportOptions $options): \Generator
    {
        $query = $this->filter($this->baseQuery(), $options);

        foreach ($query->lazyById(200) as $model) {
            yield $model;
        }
    }

    public function count(ExportOptions $options): int
    {
        return $this->filter($this->baseQuery(), $options)->count();
    }

    /** Everything on the site, before the export screen's filters narrow it. */
    protected function baseQuery(): Builder
    {
        $model = $this->modelClass();

        return $model::query();
    }

    /** The column a date range applies to. Null means dates do not narrow this type. */
    protected function dateColumn(): ?string
    {
        return 'created_at';
    }

    /** Whether this type has a status worth filtering on. */
    protected function hasStatus(): bool
    {
        return false;
    }

    protected function filter(Builder $query, ExportOptions $options): Builder
    {
        $ids = $options->idsFor($this->key());

        if ($ids !== []) {
            return $query->whereIn($query->getModel()->getQualifiedKeyName(), $ids);
        }

        if ($options->status && $this->hasStatus()) {
            $query->where('status', $options->status);
        }

        $column = $this->dateColumn();

        if ($column) {
            $query->when($options->from, fn (Builder $q) => $q->whereDate($column, '>=', $options->from));
            $query->when($options->to, fn (Builder $q) => $q->whereDate($column, '<=', $options->to));
        }

        return $query;
    }

    // CSV ------------------------------------------------------------------

    /**
     * The spreadsheet form. Flat by definition, so it carries the columns
     * somebody would actually edit in Excel and nothing structural.
     *
     * @return array<string, string> record key => column heading
     */
    public function csvColumns(): array
    {
        return [];
    }

    /** @return array<int, scalar|null> */
    public function csvRow(array $record): array
    {
        $row = [];

        foreach (array_keys($this->csvColumns()) as $key) {
            $value = $record[$key] ?? null;

            $row[] = match (true) {
                is_bool($value) => $value ? 'yes' : 'no',
                is_array($value) => implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : '', $value)),
                default => $value,
            };
        }

        return $row;
    }

    public function supportsCsv(): bool
    {
        return $this->csvColumns() !== [];
    }

    // Shared field handling -------------------------------------------------

    /** @var string[] The SEO columns every content model shares. */
    protected const SEO_FIELDS = [
        'meta_title', 'meta_description', 'meta_keywords', 'og_image',
        'canonical_url', 'schema_type', 'schema_data', 'noindex',
    ];

    protected function seo(Model $model, ExportContext $context): array
    {
        $seo = [];

        foreach (self::SEO_FIELDS as $field) {
            $value = $model->getAttribute($field);

            if (filled($value) || $field === 'noindex') {
                $seo[$field] = $field === 'og_image' ? $context->file($value) : $value;
            }
        }

        return $seo;
    }

    /** @return array<string, mixed> attributes ready to fill onto the model */
    protected function seoAttributes(array $record, ImportContext $context): array
    {
        $seo = (array) ($record['seo'] ?? []);
        $attributes = [];

        foreach (self::SEO_FIELDS as $field) {
            if (! array_key_exists($field, $seo)) {
                continue;
            }

            $attributes[$field] = $field === 'og_image'
                ? $context->mediaPath($seo[$field])
                : $seo[$field];
        }

        return $attributes;
    }

    /**
     * The visual layout attached to a page, post or product.
     *
     * Exported as the tree the editor saved, because that is what the builder
     * reads back. A layout carrying absolute addresses from the old site is
     * left as it is here and rewritten on the way in, where the new addresses
     * are actually known.
     */
    protected function layout(Model $model, ExportContext $context): ?array
    {
        if (! $context->options->includeLayouts || ! method_exists($model, 'layout')) {
            return null;
        }

        $layout = $model->layout;

        if (! $layout || blank($layout->data)) {
            return null;
        }

        return [
            'region' => $layout->region,
            'theme_slug' => $layout->theme_slug,
            'data' => $layout->data,
            'is_enabled' => (bool) $layout->is_enabled,
            'published_at' => $layout->published_at?->toIso8601String(),
        ];
    }

    protected function applyLayout(Model $model, array $record, ImportContext $context): void
    {
        $data = $record['layout'] ?? null;

        if (! is_array($data) || ! $context->options->includeLayouts || blank($data['data'] ?? null)) {
            return;
        }

        // Addresses inside the tree are rewritten the same way body HTML is,
        // so an image widget points at this site's copy of the picture.
        $tree = json_decode((string) $context->rewrite(json_encode($data['data'])), true) ?: $data['data'];

        $layout = Layout::firstOrNew([
            'layoutable_type' => $model->getMorphClass(),
            'layoutable_id' => $model->getKey(),
            'region' => $data['region'] ?? null,
        ]);

        $layout->fill([
            'theme_slug' => $data['theme_slug'] ?? null,
            'data' => $tree,
            'draft_data' => $tree,
            'is_enabled' => (bool) ($data['is_enabled'] ?? true),
            'published_at' => $data['published_at'] ?? now(),
        ])->save();
    }

    // Helpers ---------------------------------------------------------------

    protected function timestamps(Model $model): array
    {
        return [
            'created_at' => $model->created_at?->toIso8601String(),
            'updated_at' => $model->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Restores the original creation date.
     *
     * Assigned rather than filled: timestamps are not fillable on any of these
     * models, and the suite runs with preventSilentlyDiscardingAttributes() on,
     * so fill() would throw rather than quietly drop it.
     */
    protected function applyTimestamps(Model $model, array $record): void
    {
        if (filled($record['created_at'] ?? null)) {
            $model->created_at = $this->date($record['created_at']);
        }
    }

    protected function date(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            // A date nobody can parse is not worth failing a post over.
            return null;
        }
    }

    protected function outcome(bool $created): string
    {
        return $created ? ImportReport::CREATED : ImportReport::UPDATED;
    }
}
