<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Media\UploadRejected;
use App\Cms\Shop\DownloadService;
use App\Cms\Support\HtmlSanitizer;
use App\Http\Controllers\Admin\Concerns\HandlesSeoFields;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    use HandlesSeoFields;

    /** Editor HTML is re-checked here; the browser is not a trusted filter. */
    public function __construct(
        private HtmlSanitizer $sanitizer,
        private DownloadService $downloads,
    ) {}

    public function index(Request $request): View
    {
        $products = Product::with('categories')
            ->when($request->filled('q'), fn ($q) => $q->search($request->string('q')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($q) => $q->whereHas('categories',
                fn ($c) => $c->where('categories.id', $request->integer('category'))))
            ->when($request->boolean('low_stock'), fn ($q) => $q->where('manage_stock', true)
                ->where('stock', '<=', (int) setting('shop_low_stock_threshold', 5)))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => Category::shop()->orderBy('name')->pluck('name', 'id'),
            'filters' => $request->only(['q', 'status', 'category', 'low_stock']),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', $this->formData(new Product([
            'status' => 'draft',
            'type' => 'simple',
            'manage_stock' => true,
            'requires_shipping' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $product = DB::transaction(function () use ($validated, $request) {
            $product = Product::create($this->attributes($validated, $request));
            $product->categories()->sync($validated['categories'] ?? []);
            $this->syncVariants($product, $request);

            return $product;
        });

        activity('product.created', "Created the product \"{$product->name}\".", $product);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', $this->formData($product->load('variants', 'categories')));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate($this->rules($product));

        DB::transaction(function () use ($product, $validated, $request) {
            $product->fill($this->attributes($validated, $request, $product))->save();
            $product->categories()->sync($validated['categories'] ?? []);
            $this->syncVariants($product, $request);
        });

        activity('product.updated', "Updated the product \"{$product->name}\".", $product);

        return back()->with('status', 'Product saved.');
    }

    public function show(Product $product): RedirectResponse
    {
        return redirect()->route('admin.products.edit', $product);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $product->delete();

        activity('product.deleted', "Deleted the product \"{$name}\".");

        return redirect()->route('admin.products.index')->with('status', 'Product moved to trash.');
    }

    // Helpers -------------------------------------------------------------

    private function rules(?Product $product = null): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'alpha_dash'],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product?->id)],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'string', 'max:255'],

            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date', 'after_or_equal:sale_starts_at'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],

            'type' => ['required', 'in:simple,variable,digital'],
            'manage_stock' => ['nullable', 'boolean'],
            'stock' => ['nullable', 'integer'],
            'allow_backorder' => ['nullable', 'boolean'],
            // The path itself is never posted. It is set from the uploaded
            // file, so a form cannot be edited to point a product at some
            // other file on the disk.
            'digital_upload' => array_merge(['nullable'], $this->downloads->uploadRules()),
            'remove_digital_file' => ['nullable', 'boolean'],

            'weight' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'string', 'max:60'],
            'requires_shipping' => ['nullable', 'boolean'],

            'status' => ['required', 'in:draft,published,archived'],
            'is_featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'categories' => ['nullable', 'array'],
            'categories.*' => ['exists:categories,id'],

            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:190'],
            'variants.*.sku' => ['nullable', 'string', 'max:80'],
            'variants.*.options' => ['nullable', 'string', 'max:500'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer'],
        ], $this->seoRules());
    }

    private function attributes(array $validated, Request $request, ?Product $product = null): array
    {
        $isDigital = $validated['type'] === 'digital';

        return array_merge([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? null,
            'sku' => $validated['sku'] ?? null,
            'short_description' => $validated['short_description'] ?? null,
            'description' => $this->sanitizer->clean($validated['description'] ?? null),
            'featured_image' => $validated['featured_image'] ?? null,

            'price' => to_minor_units($validated['price']),
            'sale_price' => filled($validated['sale_price'] ?? null) ? to_minor_units($validated['sale_price']) : null,
            'sale_starts_at' => $validated['sale_starts_at'] ?? null,
            'sale_ends_at' => $validated['sale_ends_at'] ?? null,
            'cost_price' => filled($validated['cost_price'] ?? null) ? to_minor_units($validated['cost_price']) : null,

            'type' => $validated['type'],
            'manage_stock' => $request->boolean('manage_stock'),
            'stock' => $validated['stock'] ?? 0,
            'allow_backorder' => $request->boolean('allow_backorder'),

            'weight' => $validated['weight'] ?? null,
            'dimensions' => $validated['dimensions'] ?? null,
            // A downloadable product is never shipped, whatever the checkbox says.
            'requires_shipping' => $isDigital ? false : $request->boolean('requires_shipping'),

            'status' => $validated['status'],
            'is_featured' => $request->boolean('is_featured'),
            'sort_order' => $validated['sort_order'] ?? 0,
        ], $this->seoAttributes($validated, $request), $this->digitalAttributes($request, $product, $isDigital));
    }

    /**
     * Work out what the product's downloadable file should be after this save.
     *
     * A new upload replaces the old one, the remove checkbox clears it, and
     * anything else leaves it alone - so saving an unrelated field on a digital
     * product does not silently drop its file.
     */
    private function digitalAttributes(Request $request, ?Product $product, bool $isDigital): array
    {
        $existing = $product?->digital_file;

        // Switching a product away from "digital" keeps the file on disk but
        // unlinks it, because the change is easy to make by accident and the
        // file may have taken an hour to upload.
        if (! $isDigital) {
            return ['digital_file' => null, 'digital_name' => null, 'digital_size' => null];
        }

        if ($request->hasFile('digital_upload')) {
            try {
                $stored = $this->downloads->store($request->file('digital_upload'));
            } catch (UploadRejected $e) {
                throw ValidationException::withMessages(['digital_upload' => $e->getMessage()]);
            }

            $this->downloads->delete($existing);

            return $stored;
        }

        if ($request->boolean('remove_digital_file')) {
            $this->downloads->delete($existing);

            return ['digital_file' => null, 'digital_name' => null, 'digital_size' => null];
        }

        return $product
            ? ['digital_file' => $existing, 'digital_name' => $product->digital_name, 'digital_size' => $product->digital_size]
            : ['digital_file' => null, 'digital_name' => null, 'digital_size' => null];
    }

    /**
     * Replace the product's variants with the submitted set: update the rows
     * that came back with an id, create the rest, and delete anything the form
     * no longer lists.
     */
    private function syncVariants(Product $product, Request $request): void
    {
        $submitted = collect($request->input('variants', []))
            ->filter(fn ($variant) => filled($variant['name'] ?? null));

        $keptIds = [];

        foreach ($submitted as $index => $data) {
            $attributes = [
                'name' => $data['name'],
                'sku' => $data['sku'] ?: null,
                'options' => $this->parseOptions($data['options'] ?? ''),
                'price' => filled($data['price'] ?? null) ? to_minor_units($data['price']) : null,
                'stock' => (int) ($data['stock'] ?? 0),
                'sort_order' => $index,
                'is_active' => true,
            ];

            $variant = filled($data['id'] ?? null)
                ? $product->variants()->find($data['id'])
                : null;

            if ($variant) {
                $variant->update($attributes);
            } else {
                $variant = $product->variants()->create($attributes);
            }

            $keptIds[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $keptIds ?: [0])->delete();
    }

    /** Parses "Size: M, Color: Blue" into an options map. */
    private function parseOptions(string $raw): array
    {
        $options = [];

        foreach (explode(',', $raw) as $pair) {
            if (! str_contains($pair, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $pair, 2));

            if ($key !== '' && $value !== '') {
                $options[$key] = $value;
            }
        }

        return $options;
    }

    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => Category::shop()->orderBy('name')->pluck('name', 'id'),
            'selectedCategories' => $product->exists ? $product->categories->pluck('id')->all() : [],
            'schemaTypes' => $this->schemaTypes(),
            'downloadExtensions' => $this->downloads->allowedExtensions(),
            'downloadMaxKb' => $this->downloads->maxUploadKb(),
            // A row can point at a file that has since been removed from disk;
            // the form says so rather than pretending all is well.
            'downloadMissing' => filled($product->digital_file) && ! $this->downloads->exists($product->digital_file),
        ];
    }
}
