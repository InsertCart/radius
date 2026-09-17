<?php

namespace App\Cms\Builder;

use Illuminate\Support\Facades\Log;

/**
 * Walks a layout tree and produces HTML.
 *
 * The same renderer serves the public site and the editor preview. In edit
 * mode it adds data attributes and drop zones so the JavaScript can identify
 * and manipulate each element; on the public site none of that is emitted, and
 * a visitor receives ordinary markup with one stylesheet.
 */
class LayoutRenderer
{
    private bool $editing = false;

    public function __construct(private BlockRegistry $blocks) {}

    public function editing(bool $editing = true): self
    {
        $clone = clone $this;
        $clone->editing = $editing;

        return $clone;
    }

    /** Render a whole tree. */
    public function render(array $tree, array $context = []): string
    {
        $html = '';

        foreach ($tree as $node) {
            $html .= $this->renderNode($node, $context);
        }

        return $html;
    }

    public function renderNode(array $node, array $context = []): string
    {
        return match ($node['type'] ?? 'widget') {
            'section' => $this->renderSection($node, $context),
            'column' => $this->renderColumn($node, $context),
            default => $this->renderWidget($node, $context),
        };
    }

    // Sections -------------------------------------------------------------

    private function renderSection(array $node, array $context): string
    {
        $id = $this->id($node);
        $settings = $node['settings'] ?? [];

        $tag = $this->safeTag($settings['html_tag'] ?? 'section');

        $classes = $this->classes('cb-section', $id, $settings, [
            'cb-section--full' => ($settings['content_width'] ?? 'boxed') === 'full',
            'cb-stack-tablet' => ($settings['stack_on'] ?? 'tablet') === 'tablet',
            'cb-stack-mobile' => ($settings['stack_on'] ?? 'tablet') === 'mobile',
        ]);

        $inner = '';

        foreach ($node['elements'] ?? [] as $column) {
            $inner .= $this->renderColumn($column, $context);
        }

        if ($this->editing && $inner === '') {
            $inner = '<div class="cb-empty-column">Drag a widget here</div>';
        }

        $overlay = filled($settings['overlay_color'] ?? null) ? '<div class="cb-overlay"></div>' : '';

        return sprintf(
            '<%s %s>%s<div class="cb-section-inner">%s</div></%s>',
            $tag,
            $this->attributes($id, $settings, 'section'),
            $overlay,
            $inner,
            $tag
        );
    }

    private function renderColumn(array $node, array $context): string
    {
        $id = $this->id($node);
        $settings = $node['settings'] ?? [];

        $inner = '';

        foreach ($node['elements'] ?? [] as $widget) {
            $inner .= $this->renderWidget($widget, $context);
        }

        if ($this->editing && $inner === '') {
            $inner = '<div class="cb-empty-column">Drag a widget here</div>';
        }

        // Column width is inline rather than compiled, because it is the one
        // value the editor drags in real time and a style recompile per frame
        // would be far too slow.
        $width = $this->columnWidth($settings);
        $style = $width !== null ? sprintf(' style="--cb-col-width:%s%%"', $width) : '';

        return sprintf(
            '<div %s%s><div class="cb-column-inner">%s</div></div>',
            $this->attributes($id, $settings, 'column'),
            $style,
            $inner
        );
    }

    private function columnWidth(array $settings): ?string
    {
        $width = $settings['width'] ?? null;

        if (is_array($width)) {
            $width = $width['desktop'] ?? null;
        }

        if (is_array($width)) {
            $width = $width['size'] ?? null;
        }

        return is_numeric($width) ? (string) round((float) $width, 4) : null;
    }

    // Widgets --------------------------------------------------------------

    private function renderWidget(array $node, array $context): string
    {
        $id = $this->id($node);
        $type = $node['widgetType'] ?? '';
        $settings = $node['settings'] ?? [];

        $block = $this->blocks->make($type);

        if (! $block) {
            return $this->unknownWidget($type);
        }

        // A widget whose module has since been switched off should vanish from
        // the public site rather than error, but stay visible in the editor so
        // the admin can see why their layout changed.
        if (! $block::isAvailable()) {
            return $this->editing
                ? $this->notice(sprintf('The "%s" widget needs the %s module, which is switched off.',
                    $block::name(), modules()->name((string) $block::requiresModule())))
                : '';
        }

        try {
            $inner = $block->render($settings, array_merge($context, [
                'id' => $id,
                'editing' => $this->editing,
            ]));
        } catch (\Throwable $e) {
            report($e);

            return $this->editing
                ? $this->notice('This widget could not be rendered: '.$e->getMessage())
                : '';
        }

        return sprintf(
            '<div %s><div class="cb-widget-inner">%s</div></div>',
            $this->attributes($id, $settings, 'widget', $type),
            $inner
        );
    }

    // Shared markup helpers ------------------------------------------------

    private function id(array $node): string
    {
        // Ids come from the editor but end up in class names and selectors, so
        // anything unexpected is replaced rather than trusted.
        $id = (string) ($node['id'] ?? '');

        return preg_match('/^[a-z0-9]{4,32}$/i', $id) ? $id : 'x'.substr(md5(json_encode($node)), 0, 8);
    }

    private function attributes(string $id, array $settings, string $kind, ?string $widgetType = null): string
    {
        $classes = $this->classes('cb-'.$kind, $id, $settings, []);

        if ($kind === 'section') {
            $width = $settings['content_width'] ?? 'boxed';

            if ($width === 'full' || $width === 'edge') {
                $classes[] = 'cb-section--full';
            }

            if ($width === 'edge') {
                $classes[] = 'cb-section--edge';
            }
            $classes[] = 'cb-stack-'.($settings['stack_on'] ?? 'tablet');
        }

        if ($widgetType) {
            $classes[] = 'cb-widget--'.preg_replace('/[^a-z0-9\-]/i', '', $widgetType);
        }

        $attributes = ['class="'.e(implode(' ', array_unique(array_filter($classes)))).'"'];

        if (filled($settings['css_id'] ?? null)) {
            $attributes[] = 'id="'.e(preg_replace('/[^A-Za-z0-9\-_]/', '', $settings['css_id'])).'"';
        }

        if (filled($settings['animation'] ?? null)) {
            $attributes[] = 'data-cb-animation="'.e($settings['animation']).'"';
        }

        // The editor needs to map a DOM node back to a tree node.
        if ($this->editing) {
            $attributes[] = 'data-cb-id="'.e($id).'"';
            $attributes[] = 'data-cb-type="'.e($kind).'"';

            if ($widgetType) {
                $attributes[] = 'data-cb-widget="'.e($widgetType).'"';
            }
        }

        return implode(' ', $attributes);
    }

    /** @return string[] */
    private function classes(string $base, string $id, array $settings, array $conditional): array
    {
        $classes = [$base, 'cb-'.$id];

        foreach ($conditional as $class => $applies) {
            if ($applies) {
                $classes[] = $class;
            }
        }

        foreach (['desktop', 'tablet', 'mobile'] as $device) {
            if (! empty($settings['hide_'.$device])) {
                $classes[] = 'cb-hide-'.$device;
            }
        }

        if (filled($settings['css_classes'] ?? null)) {
            foreach (preg_split('/\s+/', $settings['css_classes']) as $class) {
                $clean = preg_replace('/[^A-Za-z0-9\-_]/', '', $class);

                if ($clean !== '') {
                    $classes[] = $clean;
                }
            }
        }

        return $classes;
    }

    /** Only a small set of wrapper tags is allowed, to keep markup sane. */
    private function safeTag(string $tag): string
    {
        $allowed = ['section', 'div', 'header', 'footer', 'main', 'article', 'aside', 'nav'];

        return in_array(strtolower($tag), $allowed, true) ? strtolower($tag) : 'section';
    }

    private function unknownWidget(string $type): string
    {
        if (! $this->editing) {
            Log::warning("[builder] Unknown widget type [{$type}] in a published layout.");

            return '';
        }

        return $this->notice('Unknown widget: '.$type);
    }

    private function notice(string $message): string
    {
        return '<div class="cb-widget cb-widget--notice"><div class="cb-placeholder">'.e($message).'</div></div>';
    }
}
