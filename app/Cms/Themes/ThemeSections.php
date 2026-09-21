<?php

namespace App\Cms\Themes;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Builder\Control;
use App\Models\Category;
use App\Models\Menu;

/**
 * The parts of the active theme that it offers to the visual builder.
 *
 * A theme's design is its own markup and stylesheet, and rebuilding a mega
 * menu or a product carousel out of generic widgets never quite looks the
 * same. So a theme can instead hand the builder its sections - the header,
 * the hero, a product rail - as widgets with a few settings each. The builder
 * then arranges the theme's real markup rather than an imitation of it.
 *
 * Declared in theme.json:
 *
 *     "sections": {
 *         "rail": {
 *             "label": "Product rail",
 *             "icon": "grid",
 *             "view": "sections.rail",             (default: sections.<key>)
 *             "areas": ["home", "home_bottom"],    (optional; default: anywhere)
 *             "controls": [
 *                 {"type": "text", "key": "title", "label": "Title", "default": "Featured"},
 *                 {"type": "select", "key": "category", "label": "Category", "options": "@shop_categories"}
 *             ]
 *         }
 *     }
 *
 * Starting layouts for regions live in starters.json beside theme.json, so a
 * region opens in the editor looking like the theme rather than empty.
 */
class ThemeSections
{
    /** Control types a theme may declare, mapped to their Control factory. */
    private const CONTROL_TYPES = [
        'text', 'textarea', 'richtext', 'number', 'toggle', 'select',
        'color', 'image', 'link', 'icon', 'slider', 'dimensions',
    ];

    private ?array $manifest = null;

    private ?array $starters = null;

    public function __construct(private ThemeManager $themes) {}

    /** @return array<string, array> Normalised section definitions, keyed by section key. */
    public function all(): array
    {
        $sections = [];

        foreach ((array) ($this->manifest()['sections'] ?? []) as $key => $section) {
            if (! is_array($section) || ! preg_match('/^[a-z0-9_\-]+$/', (string) $key)) {
                continue;
            }

            $sections[$key] = [
                'key' => $key,
                'label' => (string) ($section['label'] ?? ucfirst(str_replace(['_', '-'], ' ', $key))),
                'icon' => (string) ($section['icon'] ?? 'grid'),
                'description' => $section['description'] ?? null,
                'view' => (string) ($section['view'] ?? 'sections.'.$key),
                'areas' => isset($section['areas']) ? array_values((array) $section['areas']) : null,
                'controls' => array_values(array_filter((array) ($section['controls'] ?? []), 'is_array')),
            ];
        }

        return $sections;
    }

    public function get(?string $key): ?array
    {
        return $key !== null ? ($this->all()[$key] ?? null) : null;
    }

    public function hasAny(): bool
    {
        return $this->all() !== [];
    }

    /**
     * One section's controls. With $prefixed, keys are stored the way the
     * shared theme-section widget keeps them ("rail__title").
     *
     * @return Control[]
     */
    public function controls(string $key, bool $prefixed = false): array
    {
        $controls = [];

        foreach ($this->get($key)['controls'] ?? [] as $definition) {
            $type = $definition['type'] ?? 'text';
            $name = (string) ($definition['key'] ?? '');

            if (! in_array($type, self::CONTROL_TYPES, true) || ! preg_match('/^[a-z0-9_]+$/', $name)) {
                continue;
            }

            $label = (string) ($definition['label'] ?? ucfirst(str_replace('_', ' ', $name)));
            $name = $prefixed ? ThemeSectionKeys::prefixed($key, $name) : $name;

            $control = match ($type) {
                'select' => Control::select($name, $label, $this->options($definition['options'] ?? [])),
                'slider' => Control::slider($name, $label),
                'dimensions' => Control::dimensions($name, $label),
                default => Control::{$type}($name, $label),
            };

            if (isset($definition['tab'])) {
                $control->tab((string) $definition['tab']);
            }

            if (isset($definition['section'])) {
                $control->section((string) $definition['section']);
            }

            if (isset($definition['selector'], $definition['property'])) {
                $control->selector(
                    (string) $definition['selector'],
                    (string) $definition['property'],
                    $definition['template'] ?? null
                );
            } elseif (isset($definition['selectors']) && is_array($definition['selectors'])) {
                foreach ($definition['selectors'] as $sel) {
                    if (isset($sel['selector'], $sel['property'])) {
                        $control->selector(
                            (string) $sel['selector'],
                            (string) $sel['property'],
                            $sel['template'] ?? null
                        );
                    }
                }
            }

            if (isset($definition['units']) && is_array($definition['units'])) {
                $control->units($definition['units']);
            }

            if (in_array($type, ['number', 'slider'], true)) {
                isset($definition['min']) && $control->min((float) $definition['min']);
                isset($definition['max']) && $control->max((float) $definition['max']);
                isset($definition['step']) && $control->step((float) $definition['step']);
            }

            if (! empty($definition['responsive'])) {
                $control->responsive(true);
            }

            if (array_key_exists('default', $definition)) {
                $control->default($definition['default']);
            }

            if (isset($definition['help'])) {
                $control->help((string) $definition['help']);
            }

            $controls[] = $control;
        }

        return $controls;
    }

    /** Default settings for one section, unprefixed. */
    public function defaults(string $key): array
    {
        $defaults = [];

        foreach ($this->controls($key) as $control) {
            $definition = $control->toArray();

            if ($definition['default'] !== null) {
                $defaults[$definition['key']] = $definition['default'];
            }
        }

        return $defaults;
    }

    /**
     * Select options, either listed in theme.json or drawn from the site with
     * a named source, so a theme can offer "pick a category" without code.
     */
    private function options(mixed $options): array
    {
        if (is_array($options)) {
            return array_map('strval', $options);
        }

        try {
            return match ($options) {
                '@shop_categories' => Category::shop()->orderBy('name')->pluck('name', 'id')
                    ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])->all(),
                '@blog_categories' => Category::where('type', Category::TYPE_BLOG)->orderBy('name')->pluck('name', 'id')
                    ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])->all(),
                '@menus' => Menu::orderBy('name')->pluck('name', 'slug')->all(),
                default => [],
            };
        } catch (\Throwable $e) {
            return [];
        }
    }

    // Starters -------------------------------------------------------------

    /** Starters the theme ships for a region: [key, label, hint]. */
    public function starters(string $region): array
    {
        return array_map(fn (array $starter) => [
            'key' => 'theme-'.$starter['key'],
            'label' => $starter['label'],
            'hint' => $starter['hint'],
        ], $this->startersFor($region));
    }

    /** Expand one of the theme's starters into a layout tree. */
    public function buildStarter(string $region, string $key): array
    {
        foreach ($this->startersFor($region) as $starter) {
            if ('theme-'.$starter['key'] === $key) {
                return array_map(fn ($section) => $this->expandSection($section), $starter['sections']);
            }
        }

        return [];
    }

    private function startersFor(string $region): array
    {
        $found = [];

        foreach ((array) ($this->startersFile()[$region] ?? []) as $index => $starter) {
            if (! is_array($starter) || ! is_array($starter['sections'] ?? null)) {
                continue;
            }

            $found[] = [
                'key' => preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($starter['key'] ?? $index))) ?: (string) $index,
                'label' => (string) ($starter['label'] ?? 'Your theme\'s design'),
                'hint' => (string) ($starter['hint'] ?? 'Starts from what your theme shows today'),
                'sections' => $starter['sections'],
            ];
        }

        return $found;
    }

    /**
     * A starter section is written compactly:
     *
     *     {"width": "edge", "settings": {...}, "columns": [{"width": 100, "widgets": [
     *         {"section": "rail", "settings": {"title": "Coffee"}},
     *         {"type": "heading", "settings": {"text": "Hello"}}
     *     ]}]}
     *
     * A section with no "columns" holds its "widgets" in a single column.
     */
    private function expandSection(array $section): array
    {
        $columns = is_array($section['columns'] ?? null)
            ? $section['columns']
            : [['width' => 100, 'widgets' => $section['widgets'] ?? []]];

        $edge = ($section['width'] ?? 'edge') === 'edge';

        return [
            'id' => $this->id(),
            'type' => 'section',
            'settings' => array_merge($edge ? [
                'content_width' => 'edge',
                'gap' => ['size' => 0, 'unit' => 'px'],
                'stack_on' => 'tablet',
            ] : [
                'content_width' => 'boxed',
                'gap' => ['size' => 24, 'unit' => 'px'],
                'stack_on' => 'tablet',
            ], (array) ($section['settings'] ?? [])),
            'elements' => array_map(fn ($column) => [
                'id' => $this->id(),
                'type' => 'column',
                'settings' => array_merge(
                    ['width' => ['desktop' => (float) ($column['width'] ?? 100)]],
                    (array) ($column['settings'] ?? [])
                ),
                'elements' => array_map(fn ($widget) => $this->expandWidget((array) $widget), (array) ($column['widgets'] ?? [])),
            ], $columns),
        ];
    }

    private function expandWidget(array $widget): array
    {
        if (isset($widget['section'])) {
            $section = (string) $widget['section'];
            $settings = ['section' => $section];

            foreach (array_merge($this->defaults($section), (array) ($widget['settings'] ?? [])) as $key => $value) {
                $settings[ThemeSectionKeys::prefixed($section, $key)] = $value;
            }

            return [
                'id' => $this->id(),
                'type' => 'widget',
                'widgetType' => 'theme-section',
                'settings' => $settings,
                'elements' => [],
            ];
        }

        $type = (string) ($widget['type'] ?? 'text');
        $class = app(BlockRegistry::class)->find($type);

        return [
            'id' => $this->id(),
            'type' => 'widget',
            'widgetType' => $type,
            'settings' => array_merge($class ? $class::defaults() : [], (array) ($widget['settings'] ?? [])),
            'elements' => [],
        ];
    }

    // Theme assets for the editor canvas -----------------------------------

    /**
     * Stylesheets, scripts and the body class the theme needs for a region to
     * be previewed on its own, outside the theme's layout.
     */
    public function canvasAssets(): array
    {
        $builder = (array) ($this->manifest()['builder'] ?? []);

        $url = fn (string $file) => theme_asset($file);

        return [
            'styles' => array_map($url, array_filter((array) ($builder['styles'] ?? []), 'is_string')),
            'scripts' => array_map($url, array_filter((array) ($builder['scripts'] ?? []), 'is_string')),
            'body_class' => preg_replace('/[^A-Za-z0-9\-_ ]/', '', (string) ($builder['body_class'] ?? '')),
        ];
    }

    // Reading the theme ----------------------------------------------------

    /** theme.json, read from disk so an edit needs no re-sync to show up. */
    private function manifest(): array
    {
        return $this->manifest ??= $this->readJson('theme.json');
    }

    private function startersFile(): array
    {
        return $this->starters ??= $this->readJson('starters.json');
    }

    private function readJson(string $file): array
    {
        $theme = $this->themes->active();

        if (! $theme) {
            return [];
        }

        $path = $theme->path($file);

        if (! is_file($path)) {
            return $file === 'theme.json' ? (array) $theme->meta : [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    private function id(): string
    {
        return substr(bin2hex(random_bytes(5)), 0, 10);
    }
}
