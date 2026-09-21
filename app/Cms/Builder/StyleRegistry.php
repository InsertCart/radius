<?php

namespace App\Cms\Builder;

/**
 * Collects the CSS that a request's layouts need, so it can be emitted once in
 * the document head.
 *
 * Regions and page layouts are rendered at different points in the template -
 * the header and footer regions only after the head has been printed - so the
 * CSS cannot simply be echoed where it is produced. Blocks register their
 * stylesheet here as they render, the theme's @builderStyles directive leaves
 * a marker in the head, and InjectBuilderStyles swaps the marker for the
 * collected result once the whole page has rendered.
 *
 * Keys deduplicate: a header used on every page contributes its CSS once.
 */
class StyleRegistry
{
    /** Where @builderStyles stands in the head until the page has rendered. */
    public const MARKER = '<!--cb-builder-styles-->';

    /** @var array<string, string> */
    private array $styles = [];

    /** @var array<string, string> */
    private array $fonts = [];

    public function add(string $key, string $css): void
    {
        if (trim($css) === '') {
            return;
        }

        $this->styles[$key] = $css;
    }

    public function has(string $key): bool
    {
        return isset($this->styles[$key]);
    }

    /** Register a Google font family used by a typography control. */
    public function addFont(string $family, array $weights = ['400', '700']): void
    {
        if (blank($family) || $this->isSystemFont($family)) {
            return;
        }

        $existing = $this->fonts[$family] ?? [];
        $this->fonts[$family] = array_values(array_unique(array_merge($existing, $weights)));
    }

    /** Fonts already on the machine need no stylesheet fetching. */
    private function isSystemFont(string $family): bool
    {
        $system = ['inherit', 'sans-serif', 'serif', 'monospace', 'cursive', 'system-ui',
            'Arial', 'Helvetica', 'Georgia', 'Times New Roman', 'Courier New', 'Verdana', 'Tahoma'];

        return in_array(trim($family), $system, true);
    }

    public function css(): string
    {
        return implode('', $this->styles);
    }

    /**
     * The Google Fonts stylesheet for every family in use, as one request.
     * Returns null when no web font is needed, which is the common case.
     */
    public function fontUrl(): ?string
    {
        if ($this->fonts === []) {
            return null;
        }

        $families = [];

        foreach ($this->fonts as $family => $weights) {
            sort($weights);
            $families[] = 'family='.rawurlencode($family).':wght@'.implode(';', $weights);
        }

        return 'https://fonts.googleapis.com/css2?'.implode('&', $families).'&display=swap';
    }

    /** The complete head block: font link plus the collected stylesheet. */
    public function render(): string
    {
        $html = '';

        // Design tokens first, so a layout referencing var(--cb-color-primary)
        // resolves whatever the site owner has chosen.
        try {
            $tokens = \App\Models\DesignToken::rootCss();
            if ($tokens !== '') {
                $html .= '<style id="cb-tokens">'.$tokens.'</style>';
            }
        } catch (\Throwable $e) {
            // Pre-install, or a missing table: tokens are optional.
        }

        if ($url = $this->fontUrl()) {
            $html .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
            $html .= '<link rel="stylesheet" href="'.e($url).'">';
        }

        $css = $this->css();

        if ($css !== '') {
            $html .= '<style id="cb-styles">'.$css.'</style>';
        }

        return $html;
    }

    /** Reset between requests in a long-running worker. */
    public function flush(): void
    {
        $this->styles = [];
        $this->fonts = [];
    }
}
