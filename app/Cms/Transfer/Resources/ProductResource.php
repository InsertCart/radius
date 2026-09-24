<?php

namespace App\Cms\Transfer\Resources;

use App\Cms\Support\HtmlSanitizer;
use App\Cms\Transfer\ExportContext;
use App\Cms\Transfer\ImportContext;
use App\Cms\Transfer\ImportReport;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Shop products, with their categories, variants and gallery.
 *
 * Prices travel as the integers they are stored as - minor units, so 1999 is
 * 19.99 - and the manifest records the currency the site was using. Two sites
 * on different currencies will import the numbers unchanged, which is the only
 * honest thing to do: guessing an exchange rate into somebody's price list
 * would be worse than leaving it to them.
 *
 * The file behind a digital product is deliberately not exported. It sits on a
 * disk with no public URL because it is the thing being sold, and quietly
 * packing paid goods into a download an editor can request is not a decision
 * this feature gets to make.
 */
class ProductResource extends TransferResource
{
    public function __construct(private HtmlSanitizer $sanitizer) {}

    public function key(): string
    {
        return 'products';
    }

    public function label(): string
    {
        return 'Products';
    }

    public function modelClass(): string
    {
        return Product::class;
    }

    public function module(): ?string
    {
        return 'shop';
    }

    public function hint(): string
    {
        return 'Products with prices, stock, variants, categories and gallery images. Not the files behind digital products.';
    }

    protected function hasStatus(): bool
    {
        return true;
    }

    protected function baseQuery(): Builder
    {
        return Product::query()->with(['categories', 'variants', 'gallery']);
    }

    public function record(Model $model, ExportContext $context): array
    {
        /** @var Product $model */
        return array_filter([
            'name' => $model->name,
            'slug' => $model->slug,
            'sku' => $model->sku,
            'short_description' => $model->short_description,
            'description' => $model->rawContent(),
            'featured_image' => $context->file($model->featured_image),
            'price' => (int) $model->price,
            'sale_price' => $model->sale_price !== null ? (int) $model->sale_price : null,
            'sale_starts_at' => $model->sale_starts_at?->toIso8601String(),
            'sale_ends_at' => $model->sale_ends_at?->toIso8601String(),
            'cost_price' => $model->cost_price !== null ? (int) $model->cost_price : null,
            'type' => $model->type,
            'manage_stock' => (bool) $model->manage_stock,
            'stock' => (int) $model->stock,
            'allow_backorder' => (bool) $model->allow_backorder,
            'digital_name' => $model->digital_name,
            'weight' => $model->weight,
            'dimensions' => $model->dimensions,
            'requires_shipping' => (bool) $model->requires_shipping,
            'status' => $model->status,
            'is_featured' => (bool) $model->is_featured,
            'sort_order' => (int) $model->sort_order,
            'categories' => $model->categories
                ->map(fn (Category $category) => ['slug' => $category->slug, 'name' => $category->name])
                ->all(),
            'gallery' => $model->gallery->map(fn (Media $media) => [
                'path' => $context->file($media->path),
                'url' => $media->url,
            ])->all(),
            'variants' => $model->variants->map(fn (ProductVariant $variant) => array_filter([
                'name' => $variant->name,
                'sku' => $variant->sku,
                'options' => $variant->options,
                'price' => $variant->price !== null ? (int) $variant->price : null,
                'sale_price' => $variant->sale_price !== null ? (int) $variant->sale_price : null,
                'stock' => (int) $variant->stock,
                'image' => $context->file($variant->image),
                'is_active' => (bool) $variant->is_active,
                'sort_order' => (int) $variant->sort_order,
            ], fn ($value) => $value !== null))->all(),
            'seo' => $this->seo($model, $context),
            'layout' => $this->layout($model, $context),
        ] + $this->timestamps($model), fn ($value) => $value !== null);
    }

    public function import(array $record, ImportContext $context): string
    {
        $slug = $record['slug'] ?? null;

        if (blank($slug) && blank($record['name'] ?? null)) {
            return ImportReport::SKIPPED;
        }

        $slug = $slug ?: Str::slug($record['name']);
        $existing = Product::where('slug', $slug)->first();

        if ($existing && ! $context->options->updatesExisting()) {
            return ImportReport::SKIPPED;
        }

        $product = $existing ?: new Product(['slug' => $slug]);

        $product->fill(array_merge([
            'name' => $record['name'] ?? $slug,
            'sku' => $this->sku($record['sku'] ?? null, $product),
            'short_description' => $record['short_description'] ?? null,
            'description' => $this->sanitizer->clean($context->rewrite($record['description'] ?? null)),
            'featured_image' => $context->mediaPath($record['featured_image'] ?? null),
            'price' => max(0, (int) ($record['price'] ?? 0)),
            'sale_price' => isset($record['sale_price']) ? max(0, (int) $record['sale_price']) : null,
            'sale_starts_at' => $this->date($record['sale_starts_at'] ?? null),
            'sale_ends_at' => $this->date($record['sale_ends_at'] ?? null),
            'cost_price' => isset($record['cost_price']) ? max(0, (int) $record['cost_price']) : null,
            'type' => in_array($record['type'] ?? null, ['simple', 'variable', 'digital'], true) ? $record['type'] : 'simple',
            'manage_stock' => (bool) ($record['manage_stock'] ?? true),
            'stock' => (int) ($record['stock'] ?? 0),
            'allow_backorder' => (bool) ($record['allow_backorder'] ?? false),
            'digital_name' => $record['digital_name'] ?? null,
            'weight' => $record['weight'] ?? null,
            'dimensions' => $record['dimensions'] ?? null,
            'requires_shipping' => (bool) ($record['requires_shipping'] ?? true),
            'status' => $context->options->statusFor($record['status'] ?? null) ?? 'draft',
            'is_featured' => (bool) ($record['is_featured'] ?? false),
            'sort_order' => (int) ($record['sort_order'] ?? 0),
        ], $this->seoAttributes($record, $context)));

        $this->applyTimestamps($product, $record);
        $product->save();

        // A digital product whose file did not travel would otherwise be
        // orderable and undeliverable, so it is held back until somebody
        // uploads the file.
        if ($product->isDigital() && blank($product->digital_file) && $product->status === 'published') {
            $product->forceFill(['status' => 'draft'])->save();
            $context->report->note('products', '"'.$product->name.'" is a digital product and its file was not in the export, so it was imported as a draft.');
        }

        $product->categories()->sync($this->categories($record['categories'] ?? []));

        $this->variants($product, $record['variants'] ?? [], $context);
        $this->gallery($product, $record['gallery'] ?? [], $context);

        return $this->outcome(! $existing);
    }

    /** SKUs are unique site-wide, so a clash has to give way rather than fail the row. */
    private function sku(?string $sku, Product $product): ?string
    {
        if (blank($sku)) {
            return null;
        }

        $taken = Product::where('sku', $sku)
            ->when($product->exists, fn ($query) => $query->whereKeyNot($product->getKey()))
            ->withTrashed()
            ->exists();

        return $taken ? null : $sku;
    }

    /** @return int[] */
    private function categories(array $categories): array
    {
        $ids = [];

        foreach ($categories as $category) {
            $slug = is_array($category) ? ($category['slug'] ?? null) : $category;
            $name = is_array($category) ? ($category['name'] ?? null) : $category;

            if (blank($slug) && blank($name)) {
                continue;
            }

            $slug = $slug ? Str::slug($slug) : Str::slug($name);

            $ids[] = Category::firstOrCreate(
                ['type' => Category::TYPE_SHOP, 'slug' => $slug],
                ['name' => $name ?: Str::headline($slug)]
            )->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Variants are replaced rather than merged. They have no stable key across
     * sites - two "Large / Blue" rows are the same variant only because they
     * say the same thing - and merging on that would quietly double a range.
     */
    private function variants(Product $product, array $variants, ImportContext $context): void
    {
        if ($variants === []) {
            return;
        }

        $product->variants()->delete();

        foreach ($variants as $index => $variant) {
            if (blank($variant['name'] ?? null)) {
                continue;
            }

            $product->variants()->create([
                'name' => $variant['name'],
                'sku' => $variant['sku'] ?? null,
                'options' => (array) ($variant['options'] ?? []),
                'price' => isset($variant['price']) ? max(0, (int) $variant['price']) : null,
                'sale_price' => isset($variant['sale_price']) ? max(0, (int) $variant['sale_price']) : null,
                'stock' => (int) ($variant['stock'] ?? 0),
                'image' => $context->mediaPath($variant['image'] ?? null),
                'is_active' => (bool) ($variant['is_active'] ?? true),
                'sort_order' => (int) ($variant['sort_order'] ?? $index),
            ]);
        }
    }

    private function gallery(Product $product, array $gallery, ImportContext $context): void
    {
        if ($gallery === []) {
            return;
        }

        $sync = [];
        $order = 0;

        foreach ($gallery as $item) {
            $path = is_array($item) ? ($item['path'] ?? null) : $item;
            $url = is_array($item) ? ($item['url'] ?? null) : null;

            $media = $context->media($path, $url);

            if ($media) {
                $sync[$media->id] = ['collection' => 'gallery', 'sort_order' => $order++];
            }
        }

        if ($sync !== []) {
            $product->gallery()->sync($sync);
        }
    }

    public function csvColumns(): array
    {
        return [
            'name' => 'Name',
            'slug' => 'Slug',
            'sku' => 'SKU',
            'status' => 'Status',
            'price' => 'Price (minor units)',
            'sale_price' => 'Sale price',
            'stock' => 'Stock',
            'type' => 'Type',
            'categories' => 'Categories',
            'short_description' => 'Short description',
            'featured_image' => 'Featured image',
        ];
    }

    public function csvRow(array $record): array
    {
        $record['categories'] = array_map(fn ($category) => $category['name'] ?? '', (array) ($record['categories'] ?? []));

        return parent::csvRow($record);
    }
}
