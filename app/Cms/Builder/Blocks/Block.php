<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * Base class for every widget the visual editor can place on a page.
 *
 * A block declares two things: the controls that appear in the settings panel,
 * and how it renders. Everything else - drag and drop, the panel UI, style
 * compilation, responsive breakpoints - is handled generically, so adding a
 * widget means writing one class and one Blade view.
 *
 * Rendering is server-side. The editor previews changes by re-rendering the
 * single element over AJAX, while purely visual settings (colour, spacing,
 * typography) are applied instantly as CSS without a round trip.
 */
abstract class Block
{
    /** Machine name, used in the layout tree. */
    abstract public static function type(): string;

    /** Shown in the widget panel. */
    abstract public static function name(): string;

    /** @return Control[] */
    abstract public static function controls(): array;

    /**
     * Blade view rendered for this block, resolved through the theme first so
     * a template can override any widget's markup.
     */
    public static function view(): string
    {
        return 'blocks.'.static::type();
    }

    /** Groups the widget in the panel: basic, media, layout, content, shop. */
    public static function category(): string
    {
        return 'basic';
    }

    /** Icon name from the editor's built-in sprite. */
    public static function icon(): string
    {
        return 'square';
    }

    /** Widgets with a lower number sort first inside their category. */
    public static function order(): int
    {
        return 50;
    }

    /** Keywords the widget search box also matches on. */
    public static function keywords(): array
    {
        return [];
    }

    /**
     * Hide a widget when the module behind it is off: a Products grid is
     * meaningless on a site with no shop.
     */
    public static function requiresModule(): ?string
    {
        return null;
    }

    public static function isAvailable(): bool
    {
        $module = static::requiresModule();

        return $module === null || modules()->enabled($module);
    }

    /**
     * Settings the editor starts a freshly dropped widget with, taken from the
     * controls' declared defaults.
     */
    public static function defaults(): array
    {
        $defaults = [];

        foreach (static::controls() as $control) {
            $definition = $control->toArray();

            if (in_array($definition['type'], ['separator', 'notice'], true)) {
                continue;
            }

            if ($definition['default'] !== null) {
                $defaults[$definition['key']] = $definition['default'];
            }
        }

        return $defaults;
    }

    /**
     * The full schema handed to the editor. Controls are grouped into tabs and
     * sections here so the JavaScript panel does no interpretation of its own.
     */
    public static function schema(): array
    {
        $tabs = [];

        // Every widget also gets the shared Advanced tab - spacing, visibility,
        // custom classes - so a block never has to declare those itself.
        $controls = array_merge(static::controls(), \App\Cms\Builder\SectionSchema::commonControls());

        foreach ($controls as $control) {
            $definition = $control->toArray();
            $tab = $definition['tab'];
            $section = $definition['section'] ?? 'General';

            $tabs[$tab][$section][] = $definition;
        }

        return [
            'type' => static::type(),
            'name' => static::name(),
            'icon' => static::icon(),
            'category' => static::category(),
            'order' => static::order(),
            'keywords' => static::keywords(),
            'defaults' => static::defaults(),
            'tabs' => $tabs,
        ];
    }

    /**
     * Data the Blade view receives on top of the raw settings. Blocks that
     * pull records from the database - a posts grid, a product carousel -
     * override this and query there.
     */
    public function data(array $settings, array $context = []): array
    {
        return [];
    }

    /**
     * Render the block to HTML.
     *
     * $context carries the element id and whether the editor is running, which
     * lets a block render placeholder text instead of nothing when it has not
     * been configured yet.
     */
    public function render(array $settings, array $context = []): string
    {
        $settings = array_merge(static::defaults(), $settings);

        $view = theme_view(static::view(), static::view());

        if (! View::exists($view)) {
            return $this->missingViewNotice();
        }

        return View::make($view, array_merge([
            'settings' => $settings,
            'block' => $this,
            'context' => $context,
            'editing' => (bool) ($context['editing'] ?? false),
        ], $this->data($settings, $context)))->render();
    }

    /** Shown in the editor when a block is placed but not yet filled in. */
    protected function placeholder(string $message, array $context = []): string
    {
        if (! ($context['editing'] ?? false)) {
            return '';
        }

        return '<div class="cb-placeholder">'.e($message).'</div>';
    }

    private function missingViewNotice(): string
    {
        if (! app()->environment('production')) {
            return '<div class="cb-placeholder">Missing view for the "'.e(static::type()).'" block.</div>';
        }

        return '';
    }

    // Helpers shared by block views ---------------------------------------

    /**
     * Drop any URL scheme that can execute code.
     *
     * Escaping alone is not enough here: `javascript:alert(1)` contains
     * nothing HTML-special, so it survives e() intact and still runs when the
     * link is clicked. Only schemes that navigate are allowed through;
     * relative paths, anchors and query strings pass untouched.
     */
    public static function safeUrl(mixed $url): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        // Strip control characters and whitespace an attacker can use to break
        // up a scheme ("java\tscript:") and slip past a naive prefix check.
        $normalised = strtolower(preg_replace('/[\s\x00-\x1F\x7F]/', '', $url));

        if (preg_match('/^[a-z][a-z0-9+.\-]*:/', $normalised, $matches)) {
            $scheme = rtrim($matches[0], ':');

            if (! in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
                return '';
            }
        }

        return $url;
    }

    /** Resolve a media path or absolute URL to something an <img> can use. */
    public static function imageUrl(mixed $value): ?string
    {
        $path = is_array($value) ? ($value['url'] ?? $value['path'] ?? null) : $value;

        if (blank($path)) {
            return null;
        }

        return str_starts_with($path, 'http') || str_starts_with($path, 'data:')
            ? $path
            : Storage::disk(config('cms.media.disk'))->url($path);
    }

    /**
     * Build the attributes for a link control, keeping rel safe when the link
     * opens in a new tab.
     */
    public static function linkAttributes(mixed $link): string
    {
        if (blank($link)) {
            return '';
        }

        $url = static::safeUrl(is_array($link) ? ($link['url'] ?? '') : $link);

        if (blank($url)) {
            return '';
        }

        $attributes = ['href="'.e($url).'"'];

        if (is_array($link)) {
            $rel = [];

            if (! empty($link['target'])) {
                $attributes[] = 'target="_blank"';
                // Without noopener the opened page can reach back through
                // window.opener; noreferrer keeps the referrer out of it too.
                $rel[] = 'noopener';
                $rel[] = 'noreferrer';
            }

            if (! empty($link['nofollow'])) {
                $rel[] = 'nofollow';
            }

            if ($rel !== []) {
                $attributes[] = 'rel="'.implode(' ', array_unique($rel)).'"';
            }
        }

        return implode(' ', $attributes);
    }
}
