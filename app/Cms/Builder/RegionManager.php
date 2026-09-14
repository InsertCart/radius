<?php

namespace App\Cms\Builder;

use App\Models\Layout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Bridges hand-written themes and the visual editor.
 *
 * A theme marks the parts of itself that may be replaced:
 *
 *     @region('header')
 *         ... the theme's own header markup ...
 *     @endregion
 *
 * If no layout has been built for that region the directive simply renders the
 * markup inside it, so the theme behaves exactly as it did before. Once an
 * admin designs a header in the editor, that layout is rendered instead.
 *
 * A theme with no @region markers is unaffected: it never offers those parts
 * for editing, and the builder is limited to page content. That is what makes
 * the editor safe to add to an existing site.
 */
class RegionManager
{
    /** Regions the CMS understands, whether or not a theme uses them. */
    public const KNOWN_REGIONS = [
        'header' => 'Header',
        'footer' => 'Footer',
        'sidebar' => 'Sidebar',
        'before_content' => 'Before content',
        'after_content' => 'After content',
        'blog_sidebar' => 'Blog sidebar',
        'shop_sidebar' => 'Shop sidebar',
        'popup' => 'Popup',
    ];

    /** Published region layouts for the active theme, keyed by region. */
    private ?array $layouts = null;

    /** Regions whose markup the current theme actually wraps. */
    private array $declared = [];

    public function __construct(
        private LayoutRenderer $renderer,
        private \App\Cms\Themes\ThemeManager $themes,
    ) {}

    /**
     * Regions this theme says it supports, from the "regions" key of
     * theme.json. A theme that declares none still works; it simply offers
     * nothing to override.
     */
    public function availableForTheme(): array
    {
        $theme = $this->themes->active();
        $declared = (array) ($theme?->meta['regions'] ?? []);

        $regions = [];

        foreach ($declared as $key => $value) {
            // Accept both ["header", "footer"] and {"header": "Site header"}.
            [$key, $label] = is_int($key)
                ? [$value, self::KNOWN_REGIONS[$value] ?? ucfirst((string) $value)]
                : [$key, is_array($value) ? ($value['label'] ?? $key) : $value];

            $regions[$key] = $label;
        }

        return $regions;
    }

    /**
     * Pages the CMS renders from a controller rather than from a content
     * record - the cart, the checkout, the blog and shop indexes.
     *
     * These are buildable like a theme region, but they are declared by the
     * CMS rather than by the theme, and one whose module is switched off is
     * not offered at all.
     */
    public function systemAreas(): array
    {
        $areas = [];

        foreach (config('builder.system_areas', []) as $key => $area) {
            $module = $area['module'] ?? null;

            if ($module && modules()->disabled($module)) {
                continue;
            }

            $areas[$key] = $area;
        }

        return $areas;
    }

    /** Everything buildable: theme regions plus system pages. */
    public function allAreas(): array
    {
        $areas = [];

        foreach ($this->availableForTheme() as $key => $label) {
            $areas[$key] = [
                'label' => $label,
                'kind' => 'region',
                'description' => 'Part of your theme\'s layout.',
                'requires_widget' => null,
            ];
        }

        foreach ($this->systemAreas() as $key => $area) {
            $areas[$key] = [
                'label' => $area['label'],
                'kind' => 'system',
                'description' => $area['description'] ?? null,
                'requires_widget' => $area['requires_widget'] ?? null,
                'route' => $area['route'] ?? null,
            ];
        }

        return $areas;
    }

    public function area(string $key): ?array
    {
        return $this->allAreas()[$key] ?? null;
    }

    public function isDeclared(string $region): bool
    {
        return array_key_exists($region, $this->allAreas());
    }

    /** True when an admin has built and published a layout for this region. */
    public function has(string $region): bool
    {
        return isset($this->published()[$region]);
    }

    /**
     * Render a region's layout. Returns an empty string when there is none,
     * which the Blade directive treats as "use the theme's own markup".
     */
    public function render(string $region): string
    {
        $layout = $this->published()[$region] ?? null;

        if (! $layout) {
            return '';
        }

        $html = $this->renderer->render($layout['data']);

        // Region CSS is collected and emitted once in the document head rather
        // than inline, so a header used on every page is not restyled per view.
        if (filled($layout['css'])) {
            app(StyleRegistry::class)->add('region-'.$region, $layout['css']);
        }

        return $html;
    }

    /**
     * @return array<string, array{data: array, css: string}>
     */
    private function published(): array
    {
        if ($this->layouts !== null) {
            return $this->layouts;
        }

        if (! $this->tableExists()) {
            return $this->layouts = [];
        }

        $theme = $this->themes->activeSlug();

        return $this->layouts = Cache::remember(
            "cms.builder.regions.{$theme}",
            3600,
            function () use ($theme) {
                return Layout::query()
                    ->whereNotNull('region')
                    ->where('theme_slug', $theme)
                    ->where('is_enabled', true)
                    ->whereNotNull('published_at')
                    ->get()
                    ->mapWithKeys(fn (Layout $layout) => [
                        $layout->region => [
                            'data' => $layout->tree(),
                            'css' => (string) $layout->compiled_css,
                        ],
                    ])
                    ->all();
            }
        );
    }

    public function flush(): void
    {
        $this->layouts = null;

        foreach (array_keys($this->availableForTheme()) as $region) {
            Cache::forget('cms.builder.regions.'.$this->themes->activeSlug());
        }

        Cache::forget('cms.builder.regions.'.$this->themes->activeSlug());
    }

    /**
     * Note that a theme rendered this region, so the editor can tell an admin
     * which regions are genuinely wired up rather than merely declared.
     */
    public function markRendered(string $region): void
    {
        $this->declared[$region] = true;
    }

    public function renderedRegions(): array
    {
        return array_keys($this->declared);
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('layouts');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
