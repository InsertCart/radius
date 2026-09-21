<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Builder\IconLibrary;
use App\Cms\Builder\LayoutRenderer;
use App\Cms\Builder\RegionManager;
use App\Cms\Builder\RegionStarter;
use App\Cms\Builder\SectionSchema;
use App\Cms\Builder\StyleCompiler;
use App\Cms\Support\HtmlSanitizer;
use App\Http\Controllers\Controller;
use App\Models\DesignToken;
use App\Models\Layout;
use App\Models\LayoutPreset;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Serves the visual editor and the endpoints it talks to.
 *
 * The editor is a single page that loads a layout, then communicates through
 * three small endpoints: autosave a draft, publish, and re-render. Rendering
 * stays on the server so a widget's markup is defined once, in Blade, rather
 * than duplicated in JavaScript.
 */
class BuilderController extends Controller
{
    public function __construct(
        private BlockRegistry $blocks,
        private LayoutRenderer $renderer,
        private StyleCompiler $styles,
        private RegionManager $regions,
        private HtmlSanitizer $sanitizer,
    ) {}

    // Opening the editor ---------------------------------------------------

    /** The editor for a content record: a page, post or product. */
    public function edit(Request $request, string $type, int $id): View|RedirectResponse
    {
        $model = $this->resolveModel($type, $id);

        // A product's page is designed once, in the shared template, so that
        // is where "Open the builder" goes - previewing this product. The
        // description-only editor stays reachable with ?description=1, and a
        // product that already has a description layout keeps opening it.
        if ($model instanceof Product
            && ! $request->boolean('description')
            && $this->regions->isDeclared('product')
            && ($model->layout()->first()?->editableTree() ?? []) === []) {
            return redirect()->route('admin.builder.region', ['region' => 'product', 'product' => $model->id]);
        }

        $layout = Layout::forModel($model);

        return view('admin.builder.editor', $this->editorPayload($layout, [
            'title' => $model->title ?? $model->name ?? 'Untitled',
            'subtitle' => ucfirst($type),
            'previewUrl' => route('admin.builder.preview', ['type' => $type, 'id' => $id]),
            'backUrl' => $this->backUrl($type, $model),
            'viewUrl' => method_exists($model, 'url') ? $model->url() : null,
            'restoreUrl' => route('admin.builder.restore-default', $layout),
            'canRestore' => ! $layout->isEmpty(),
        ]));
    }

    /** The editor for a theme region: the header, footer and so on. */
    public function editRegion(Request $request, string $region): View
    {
        abort_unless($this->regions->isDeclared($region), 404,
            'The active theme does not offer a "'.$region.'" region.');

        $layout = Layout::forRegion($region);
        $starter = app(RegionStarter::class);
        $area = $this->regions->area($region);

        $productId = $region === 'product' ? $request->integer('product') ?: null : null;
        $sample = $this->regionContext($region, $productId)['model'] ?? null;
        $query = $productId ? ['product' => $productId] : [];

        // An empty region would open as a blank canvas while the live site
        // clearly shows the theme's version. Open it as a copy of that
        // instead: the theme's own starter, or for the product page the
        // CMS's widget-based one. It is only a draft - nothing changes for
        // visitors until it is published.
        if ($layout->editableTree() === [] && $layout->published_at === null) {
            $seed = $starter->available($region)[0]['key'] ?? null;
            $themed = $seed !== null && str_starts_with($seed, 'theme-');

            if ($themed || $region === 'product') {
                $tree = $starter->build($region, $themed ? $seed : 'classic');

                if ($tree !== []) {
                    $layout->saveDraft($tree, $request->user()->id);
                }
            }
        }

        $payload = $this->editorPayload($layout, [
            'title' => $area['label'] ?? ucfirst($region),
            'subtitle' => $sample instanceof Product
                ? 'Previewing '.$sample->name
                : (($area['kind'] ?? 'region') === 'system' ? 'Site page' : 'Theme region'),
            'previewUrl' => route('admin.builder.preview.region', array_merge(['region' => $region], $query)),
            'backUrl' => $productId && $sample
                ? route('admin.products.edit', $sample)
                : route('admin.builder.index'),
            'viewUrl' => $sample instanceof Product ? $sample->url() : url('/'),
            'widgets' => $this->blocks->panel($region),

            // A region editor opens empty even though the live site clearly has
            // a header, because the theme's own markup is doing that job. The
            // editor has to say so, and offer a way in.
            'region' => $region,
            'area' => $this->regions->area($region),
            'starters' => $starter->available($region),
            'restoreUrl' => route('admin.builder.restore-default', $layout),
            'canRestore' => ! $layout->isEmpty(),
        ]);

        // Re-renders must use the same product as the first preview.
        if ($query !== []) {
            $payload['config']['renderUrl'] = route('admin.builder.render', array_merge(['layout' => $layout], $query));
        }

        return view('admin.builder.editor', $payload);
    }

    /** Everything the editor's JavaScript needs on boot. */
    private function editorPayload(Layout $layout, array $meta): array
    {
        // $meta last, so a region editor's own values override the defaults.
        return array_merge([
            'layout' => $layout,
            'tree' => $layout->editableTree(),
            'config' => [
                'layoutId' => $layout->id,
                'saveUrl' => route('admin.builder.draft', $layout),
                'publishUrl' => route('admin.builder.publish', $layout),
                'renderUrl' => route('admin.builder.render', $layout),
                'presetsUrl' => route('admin.builder.presets'),
                'starterUrl' => url(config('cms.admin_prefix', 'admin').'/builder/starter'),
                'mediaUrl' => route('admin.media.browse'),
                'uploadUrl' => route('admin.media.store'),
                'autosaveDelay' => config('builder.editor.autosave_delay', 4000),
                'historyLimit' => config('builder.editor.history_limit', 50),
                'breakpoints' => config('builder.breakpoints'),
                'hasDraft' => $layout->hasUnpublishedChanges(),
            ],
            'widgets' => $this->blocks->panel(),
            'schemas' => $this->blocks->available(),
            'structure' => SectionSchema::schema(),
            'icons' => IconLibrary::grouped(),
            'tokens' => DesignToken::grouped(),
            'fonts' => config('builder.fonts'),
            'presets' => LayoutPreset::sections()->orderBy('sort_order')->get(['id', 'name', 'category', 'data']),
            'region' => null,
            'area' => null,
            'starters' => [],
            'restoreUrl' => null,
            'canRestore' => false,
        ], $meta);
    }

    // Saving ---------------------------------------------------------------

    /** Autosave. Writes the draft only; the live layout is untouched. */
    public function saveDraft(Request $request, Layout $layout): JsonResponse
    {
        $tree = $this->validateTree($request, $layout);

        $layout->saveDraft($tree, $request->user()->id);

        return response()->json([
            'saved' => true,
            'at' => now()->toTimeString(),
        ]);
    }

    /** Make the draft live and recompile its stylesheet. */
    public function publish(Request $request, Layout $layout): JsonResponse
    {
        $tree = $this->validateTree($request, $layout);

        $layout->publish($tree, $request->user()->id);

        activity('layout.published', $this->describe($layout), $layout, [
            'widgets' => $layout->widgetCount(),
        ]);

        return response()->json([
            'published' => true,
            'at' => now()->toTimeString(),
            'css' => $layout->compiled_css,
        ]);
    }

    /**
     * Re-render part of a layout for the canvas.
     *
     * The editor sends one node when a content setting changes, or the whole
     * tree after a structural edit. Style-only changes never come here: the
     * editor rewrites the stylesheet in the preview directly, which is what
     * makes colour and spacing feel instant.
     */
    public function render(Request $request, Layout $layout): JsonResponse
    {
        $request->validate([
            'node' => ['nullable', 'array'],
            'tree' => ['nullable', 'array'],
        ]);

        $renderer = $this->renderer->editing();
        $isAdmin = (bool) $request->user()?->isAdmin();
        $trusted = $this->rawHtmlAlreadyInLayout($layout);
        $context = $this->layoutContext($layout, $request->integer('product') ?: null);

        if ($request->filled('node')) {
            // Cleaned the same way the save path cleans it, so the canvas is a
            // preview of what will be stored rather than of what was typed.
            $node = $this->cleanTree([$request->input('node')], $isAdmin, $trusted)[0];

            return response()->json([
                'html' => $renderer->renderNode($node, $context),
                'css' => $this->styles->compile([$node]),
            ]);
        }

        $tree = $this->cleanTree($request->input('tree', []), $isAdmin, $trusted);

        return response()->json([
            'html' => $renderer->render($tree, $context),
            'css' => $this->styles->compile($tree),
        ]);
    }

    /** Recompile the stylesheet for a tree, with no re-render. */
    public function styles(Request $request, Layout $layout): JsonResponse
    {
        $tree = $request->input('tree', []);

        return response()->json(['css' => $this->styles->compile($tree)]);
    }

    // The canvas -----------------------------------------------------------

    /**
     * The document loaded into the editor's iframe.
     *
     * It is rendered through the real theme, so what an admin arranges is what
     * a visitor will see - the same fonts, the same width, the same chrome.
     */
    public function preview(Request $request, string $type, int $id): View
    {
        $model = $this->resolveModel($type, $id);
        $layout = Layout::forModel($model);

        return view('admin.builder.canvas', [
            'content' => $this->renderer->editing()->render($layout->editableTree(), ['model' => $model]),
            'css' => $this->styles->compile($layout->editableTree()),
            'title' => $model->title ?? $model->name ?? '',
            'chrome' => true,
        ]);
    }

    public function previewRegion(Request $request, string $region): View
    {
        $layout = Layout::forRegion($region);
        $context = $this->regionContext($region, $request->integer('product') ?: null);

        return view('admin.builder.canvas', [
            'content' => $this->renderer->editing()->render($layout->editableTree(), $context),
            'css' => $this->styles->compile($layout->editableTree()),
            'title' => ucfirst($region),
            // A theme region is a fragment, so it is previewed without the
            // theme's own header and footer wrapped around it. A site page -
            // the product page, the cart - is a whole page, and without the
            // theme's stylesheet its preview would not look like the live site.
            'chrome' => ($this->regions->area($region)['kind'] ?? 'region') === 'system',
            'region' => $region,
            // A fragment is previewed outside the theme's layout, so the theme
            // says which of its stylesheets the fragment needs.
            'themeAssets' => app(\App\Cms\Themes\ThemeSections::class)->canvasAssets(),
        ]);
    }

    // Supporting screens ---------------------------------------------------

    /** The builder's landing page: regions and recently edited layouts. */
    public function index(): View
    {
        $theme = themes()->active();

        return view('admin.builder.index', [
            'theme' => $theme,
            'regions' => $this->regions->availableForTheme(),
            'systemAreas' => $this->regions->systemAreas(),
            'regionLayouts' => Layout::whereNotNull('region')
                ->where('theme_slug', themes()->activeSlug())
                ->get()
                ->keyBy('region'),
            'recent' => Layout::with('layoutable', 'editor')
                ->whereNotNull('layoutable_id')
                ->whereNotNull('published_at')
                ->latest('updated_at')
                ->limit(12)
                ->get(),
            'presets' => LayoutPreset::orderBy('sort_order')->get(),
        ]);
    }

    /** Saved sections, offered in the editor's preset drawer. */
    public function presets(Request $request): JsonResponse
    {
        return response()->json([
            'data' => LayoutPreset::orderBy('sort_order')->get(['id', 'name', 'category', 'type', 'data']),
        ]);
    }

    /**
     * Save a section as a reusable preset.
     *
     * The section is cleaned exactly as a saved layout is, because it will be
     * dropped into other layouts later and must already be trustworthy.
     */
    public function storePreset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:60'],
            'data' => ['required', 'array'],
            'data.type' => ['required', 'in:section'],
            'data.elements' => ['present', 'array'],
        ]);

        $this->guardTreeSize([$validated['data']]);

        $section = $this->cleanTree([$validated['data']], (bool) $request->user()?->isAdmin(), [])[0];

        $preset = LayoutPreset::create([
            'name' => $validated['name'],
            'category' => $validated['category'] ?? 'custom',
            'type' => 'section',
            'data' => $section,
            'sort_order' => (int) LayoutPreset::max('sort_order') + 1,
        ]);

        activity('layout.preset_saved', 'Saved the section "'.$preset->name.'"', $preset);

        return response()->json([
            'saved' => true,
            'preset' => $preset->only('id', 'name', 'category', 'data'),
        ]);
    }

    /** Delete a saved section. Pages already using it keep their own copy. */
    public function destroyPreset(LayoutPreset $preset): RedirectResponse
    {
        $preset->delete();

        activity('layout.preset_deleted', 'Deleted the saved section "'.$preset->name.'"');

        return back()->with('status', 'Saved section deleted. Pages that already use it are unchanged.');
    }

    /** Turn the builder off for a record, reverting to the classic editor. */
    public function toggle(Request $request, string $type, int $id): RedirectResponse
    {
        $model = $this->resolveModel($type, $id);

        if ($model->usesBuilder()) {
            $model->disableBuilder();
            $message = 'The builder is off for this item. Its classic content is shown again.';
        } else {
            $model->enableBuilder();
            $message = 'The builder is on for this item.';
        }

        return back()->with('status', $message);
    }

    /**
     * A starting tree for a theme region.
     *
     * Returned rather than saved: the editor inserts it into the working
     * tree so it lands on the undo stack and stays a draft until published,
     * exactly like anything else the admin builds.
     */
    public function starter(string $region, string $key): JsonResponse
    {
        abort_unless($this->regions->isDeclared($region), 404);

        $tree = app(RegionStarter::class)->build($region, $key);

        abort_if($tree === [], 404, 'No starter exists for that region.');

        return response()->json(['tree' => $tree]);
    }

    /**
     * Throw away a layout so whatever it was overriding comes back.
     *
     * The recovery path when someone has redesigned themselves into a
     * corner. Nothing is lost: the version being discarded is snapshotted
     * first and can be restored from the history screen.
     */
    public function restoreDefault(Request $request, Layout $layout): RedirectResponse
    {
        $layout->restoreDefault($request->user()->id);
        $this->regions->flush();

        activity('layout.restored_default', $this->describe($layout).' Reverted to the default.', $layout);

        $message = $layout->region
            ? 'Reverted to your theme\x27s default. Your previous design is still in History if you want it back.'
            : 'Reverted to the classic editor content. Your previous design is still in History if you want it back.';

        return redirect()->route('admin.builder.index')->with('status', $message);
    }

    public function revisions(Layout $layout): View
    {
        return view('admin.builder.revisions', [
            'layout' => $layout,
            'revisions' => $layout->revisions()->with('author')->paginate(20),
        ]);
    }

    public function restore(Request $request, Layout $layout, int $revision): RedirectResponse
    {
        $snapshot = $layout->revisions()->findOrFail($revision);

        $layout->restore($snapshot);

        activity('layout.restored', 'Restored a layout from '.$snapshot->created_at->diffForHumans(), $layout);

        return back()->with('status', 'Layout restored.');
    }

    // Helpers --------------------------------------------------------------

    /**
     * Validate an incoming tree: check its size, then clean it. A layout is rendered unescaped - that is what
     * a page builder is - so the markup inside it has to be trustworthy by the
     * time it is stored, and the editor's own JavaScript is not what makes it
     * so. Everything arriving here is a plain HTTP request that anyone with an
     * account can craft by hand.
     */
    private function validateTree(Request $request, ?Layout $layout = null): array
    {
        $request->validate(['tree' => ['present', 'array']]);

        $tree = $request->input('tree', []);

        $this->guardTreeSize($tree);

        return $this->cleanTree(
            $tree,
            (bool) $request->user()?->isAdmin(),
            $layout ? $this->rawHtmlAlreadyInLayout($layout) : []
        );
    }

    /**
     * Refuse a tree too large or too deeply nested to render safely.
     *
     * Size and depth are capped because the tree arrives as JSON from the
     * browser: without limits a crafted request could make the renderer walk
     * an enormous or deeply nested structure.
     */
    private function guardTreeSize(array $tree): void
    {
        $count = 0;
        $walk = function (array $nodes, int $depth) use (&$walk, &$count) {
            if ($depth > config('builder.editor.max_depth', 6)) {
                abort(422, 'This layout is nested more deeply than the builder allows.');
            }

            foreach ($nodes as $node) {
                if (++$count > config('builder.editor.max_nodes', 2000)) {
                    abort(422, 'This layout has more elements than the builder allows.');
                }

                $walk($node['elements'] ?? [], $depth + 1);
            }
        };

        $walk($tree, 1);
    }

    /**
     * Clean every widget's settings against the controls its block declares.
     *
     * Rich text is run through the same sanitiser the classic page and post
     * editors use, so the two ways of writing a paragraph on this site are
     * held to one standard rather than two.
     */
    private function cleanTree(array $nodes, bool $isAdmin, array $trustedHtml): array
    {
        foreach ($nodes as $index => $node) {
            if (isset($node['elements']) && is_array($node['elements'])) {
                $nodes[$index]['elements'] = $this->cleanTree($node['elements'], $isAdmin, $trustedHtml);
            }

            $type = $node['widgetType'] ?? null;

            if (! $type || ! is_array($node['settings'] ?? null)) {
                continue;
            }

            $class = $this->blocks->find($type);

            if (! $class) {
                continue;
            }

            $nodes[$index]['settings'] = $this->cleanSettings(
                $node['settings'],
                self::definitions($class),
                $isAdmin,
                $trustedHtml[$node['id'] ?? ''] ?? []
            );
        }

        return $nodes;
    }

    /**
     * A block's controls as plain definition arrays. Repeater fields are
     * already stored in that shape, so flattening here lets one routine walk
     * top-level controls and repeater rows alike.
     *
     * @param  class-string<\App\Cms\Builder\Blocks\Block>  $class
     */
    private static function definitions(string $class): array
    {
        return array_map(fn ($control) => $control->toArray(), $class::controls());
    }

    private function cleanSettings(array $settings, array $definitions, bool $isAdmin, array $trusted): array
    {
        foreach ($definitions as $definition) {
            $key = $definition['key'] ?? null;

            if (! $key || ! array_key_exists($key, $settings)) {
                continue;
            }

            switch ($definition['type'] ?? '') {
                case 'richtext':
                    $settings[$key] = $this->sanitizer->clean((string) $settings[$key]);
                    break;

                case 'code':
                    // Raw markup, which is the whole point of the HTML widget
                    // and also the one control that can put a <script> on the
                    // public site. Only an administrator may author it.
                    //
                    // An editor saving a page that already contains one keeps
                    // what the administrator wrote - the stored value wins over
                    // whatever was submitted - so an ordinary edit elsewhere on
                    // the page does not quietly destroy it.
                    if (! $isAdmin) {
                        $settings[$key] = array_key_exists($key, $trusted)
                            ? $trusted[$key]
                            : $this->sanitizer->clean((string) $settings[$key]);
                    }
                    break;

                case 'repeater':
                    if (is_array($settings[$key])) {
                        $fields = $definition['fields'] ?? [];

                        foreach ($settings[$key] as $row => $item) {
                            if (is_array($item)) {
                                $settings[$key][$row] = $this->cleanSettings($item, $fields, $isAdmin, []);
                            }
                        }
                    }
                    break;
            }
        }

        return $settings;
    }

    /**
     * The raw-HTML settings an administrator has already stored on this layout,
     * keyed by widget id, so a later edit by someone else can preserve them.
     */
    private function rawHtmlAlreadyInLayout(Layout $layout): array
    {
        $found = [];

        $walk = function (array $nodes) use (&$walk, &$found) {
            foreach ($nodes as $node) {
                $walk($node['elements'] ?? []);

                $type = $node['widgetType'] ?? null;
                $id = $node['id'] ?? null;

                if (! $type || ! $id || ! is_array($node['settings'] ?? null)) {
                    continue;
                }

                $class = $this->blocks->find($type);

                if (! $class) {
                    continue;
                }

                foreach (self::definitions($class) as $definition) {
                    if (($definition['type'] ?? '') === 'code'
                        && array_key_exists($definition['key'], $node['settings'])) {
                        $found[$id][$definition['key']] = $node['settings'][$definition['key']];
                    }
                }
            }
        };

        $walk($layout->data ?? []);
        $walk($layout->draft_data ?? []);

        return $found;
    }

    /** What a layout's widgets are rendered against while it is being edited. */
    private function layoutContext(Layout $layout, ?int $productId = null): array
    {
        if ($layout->layoutable) {
            return ['model' => $layout->layoutable];
        }

        return $layout->region ? $this->regionContext($layout->region, $productId) : [];
    }

    /**
     * The product page template is designed against a real product, so prices,
     * images and stock in the preview look like the live page.
     */
    private function regionContext(string $region, ?int $productId = null): array
    {
        if ($region !== 'product') {
            return [];
        }

        $with = ['categories', 'variants', 'gallery', 'approvedReviews'];

        // The product the editor was opened from, when there is one.
        $product = ($productId ? Product::with($with)->find($productId) : null)
            ?? Product::published()->with($with)->latest()->first()
            ?? Product::with($with)->latest()->first();

        return $product ? ['model' => $product] : [];
    }

    /** @return Model&\App\Models\Concerns\HasLayout */
    private function resolveModel(string $type, int $id): Model
    {
        $class = config("builder.editable.{$type}");

        abort_unless($class && class_exists($class), 404, 'The builder is not available for that content type.');

        return $class::findOrFail($id);
    }

    private function backUrl(string $type, Model $model): string
    {
        return safe_route("admin.{$type}s.edit", $model, route('admin.builder.index'));
    }

    private function describe(Layout $layout): string
    {
        if ($layout->region) {
            return "Published the {$layout->region} region layout.";
        }

        $owner = $layout->layoutable;

        return 'Published a layout for "'.($owner->title ?? $owner->name ?? 'an item').'".';
    }
}
