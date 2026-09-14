<?php

namespace App\Cms\Builder;

/**
 * Turns a layout tree into a stylesheet.
 *
 * Every element in a layout carries a generated class (.cb-<id>), and each
 * control can declare where its value lands in CSS. Compiling those
 * declarations up front means the rendered page ships plain HTML with one
 * stylesheet rather than a thousand inline style attributes, and it is what
 * lets the editor apply a colour change instantly: it rewrites this same CSS
 * in the preview instead of re-rendering the element.
 *
 * Values are sanitised on the way through. A layout is authored by a trusted
 * admin, but a setting still ends up inside a stylesheet, and "}" in the wrong
 * place would let one escape its rule.
 */
class StyleCompiler
{
    /** Breakpoints the responsive controls compile against. */
    public const BREAKPOINTS = [
        'tablet' => 1024,
        'mobile' => 767,
    ];

    private BlockRegistry $blocks;

    /** Collected rules, keyed by media query then selector. */
    private array $rules = [];

    public function __construct(BlockRegistry $blocks)
    {
        $this->blocks = $blocks;
    }

    /** Compile a whole layout tree to CSS. */
    public function compile(array $tree): string
    {
        $this->rules = [];

        foreach ($tree as $node) {
            $this->walk($node);
        }

        return $this->toCss();
    }

    private function walk(array $node): void
    {
        $id = $node['id'] ?? null;

        if (blank($id)) {
            return;
        }

        $settings = $node['settings'] ?? [];
        $controls = $this->controlsFor($node);

        foreach ($controls as $control) {
            if (empty($control['selectors'])) {
                continue;
            }

            $this->applyControl($id, $control, $settings[$control['key']] ?? null);
        }

        foreach ($node['elements'] ?? [] as $child) {
            $this->walk($child);
        }
    }

    /**
     * Controls belonging to a node: the shared section/column ones, or the
     * declaring block's own for a widget.
     */
    private function controlsFor(array $node): array
    {
        $type = $node['type'] ?? 'widget';

        if ($type === 'section') {
            return $this->flatten(SectionSchema::controls());
        }

        if ($type === 'column') {
            return $this->flatten(SectionSchema::columnControls());
        }

        $block = $this->blocks->find($node['widgetType'] ?? '');

        if (! $block) {
            return [];
        }

        return $this->flatten(array_merge($block::controls(), SectionSchema::commonControls()));
    }

    /** @param Control[] $controls */
    private function flatten(array $controls): array
    {
        return array_map(fn (Control $control) => $control->toArray(), $controls);
    }

    private function applyControl(string $id, array $control, mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            return;
        }

        // A responsive control holds one value per device.
        if (! empty($control['responsive']) && is_array($value) && $this->looksResponsive($value)) {
            foreach (['desktop', 'tablet', 'mobile'] as $device) {
                if (! isset($value[$device]) || $value[$device] === '' || $value[$device] === null) {
                    continue;
                }

                $this->emit($id, $control, $value[$device], $device);
            }

            return;
        }

        $this->emit($id, $control, $value, 'desktop');
    }

    private function looksResponsive(array $value): bool
    {
        foreach (['desktop', 'tablet', 'mobile'] as $device) {
            if (array_key_exists($device, $value)) {
                return true;
            }
        }

        return false;
    }

    private function emit(string $id, array $control, mixed $value, string $device): void
    {
        $media = $device === 'desktop' ? '' : $device;

        foreach ($control['selectors'] as $mapping) {
            $rendered = $this->renderValue($control['type'], $value, $mapping);

            if ($rendered === null) {
                continue;
            }

            $selector = str_replace('{{WRAPPER}}', '.cb-'.$id, $mapping['selector']);
            $selector = $this->sanitiseSelector($selector);

            // A mapping can expand into several declarations, which is how one
            // typography control writes six properties.
            foreach ($rendered as $property => $declaration) {
                $property = is_string($property) ? $property : $mapping['property'];

                $this->rules[$media][$selector][$property] = $declaration;
            }
        }
    }

    /**
     * Turn a control's value into one or more CSS declarations.
     *
     * @return array<string, string>|null
     */
    private function renderValue(string $type, mixed $value, array $mapping): ?array
    {
        $property = $mapping['property'];
        $template = $mapping['template'];

        return match ($type) {
            'dimensions' => $this->dimensions($property, $value),
            'typography' => $this->typography($value),
            'border' => $this->border($value),
            'shadow' => $this->shadow($property, $value),
            'background' => $this->background($value),
            'slider' => $this->slider($property, $value, $template),
            'toggle' => $value ? [$property => $this->substitute($template, '1')] : null,
            default => $this->simple($property, $value, $template),
        };
    }

    private function simple(string $property, mixed $value, string $template): ?array
    {
        if (is_array($value)) {
            return null;
        }

        $clean = $this->sanitiseValue((string) $value);

        return $clean === '' ? null : [$property => $this->substitute($template, $clean)];
    }

    private function slider(string $property, mixed $value, string $template): ?array
    {
        // A slider stores {size, unit}; a bare number is accepted too.
        if (is_array($value)) {
            $size = $value['size'] ?? null;
            $unit = $value['unit'] ?? 'px';
        } else {
            $size = $value;
            $unit = 'px';
        }

        if ($size === null || $size === '') {
            return null;
        }

        if (! is_numeric($size)) {
            return null;
        }

        $unit = in_array($unit, ['px', '%', 'em', 'rem', 'vh', 'vw', 's', 'deg', ''], true) ? $unit : 'px';

        return [$property => $this->substitute($template, $size.$unit)];
    }

    private function dimensions(string $property, mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $unit = in_array($value['unit'] ?? 'px', ['px', 'em', 'rem', '%'], true) ? ($value['unit'] ?? 'px') : 'px';
        $sides = [];

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $side_value = $value[$side] ?? null;
            $sides[] = ($side_value === null || $side_value === '' || ! is_numeric($side_value))
                ? '0'
                : $side_value.$unit;
        }

        // Nothing set at all: skip rather than writing a redundant 0 0 0 0.
        if (count(array_unique($sides)) === 1 && $sides[0] === '0' && ! $this->dimensionsTouched($value)) {
            return null;
        }

        return [$property => implode(' ', $sides)];
    }

    private function dimensionsTouched(array $value): bool
    {
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            if (($value[$side] ?? '') !== '' && $value[$side] !== null) {
                return true;
            }
        }

        return false;
    }

    private function typography(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $declarations = [];

        if (filled($value['family'] ?? null)) {
            $declarations['font-family'] = $this->sanitiseValue($value['family']);
        }

        foreach (['size' => 'font-size', 'line_height' => 'line-height', 'letter_spacing' => 'letter-spacing'] as $key => $property) {
            if (! isset($value[$key])) {
                continue;
            }

            $rendered = $this->slider($property, $value[$key], '{{VALUE}}');

            if ($rendered) {
                $declarations += $rendered;
            }
        }

        if (filled($value['weight'] ?? null)) {
            $declarations['font-weight'] = $this->sanitiseValue((string) $value['weight']);
        }

        if (filled($value['transform'] ?? null)) {
            $declarations['text-transform'] = $this->sanitiseValue($value['transform']);
        }

        if (filled($value['style'] ?? null)) {
            $declarations['font-style'] = $this->sanitiseValue($value['style']);
        }

        if (filled($value['decoration'] ?? null)) {
            $declarations['text-decoration'] = $this->sanitiseValue($value['decoration']);
        }

        return $declarations ?: null;
    }

    private function border(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $declarations = [];
        $style = $value['style'] ?? 'none';

        if ($style === 'none' || $style === '') {
            return null;
        }

        $declarations['border-style'] = $this->sanitiseValue($style);

        if (isset($value['width'])) {
            $rendered = $this->dimensions('border-width', $value['width']);

            if ($rendered) {
                $declarations += $rendered;
            }
        }

        if (filled($value['color'] ?? null)) {
            $declarations['border-color'] = $this->sanitiseValue($value['color']);
        }

        return $declarations;
    }

    private function shadow(string $property, mixed $value): ?array
    {
        if (! is_array($value) || blank($value['color'] ?? null)) {
            return null;
        }

        $parts = [
            (int) ($value['h'] ?? 0).'px',
            (int) ($value['v'] ?? 0).'px',
            (int) ($value['blur'] ?? 10).'px',
            (int) ($value['spread'] ?? 0).'px',
            $this->sanitiseValue($value['color']),
        ];

        if (! empty($value['inset'])) {
            array_unshift($parts, 'inset');
        }

        return [$property => implode(' ', $parts)];
    }

    private function background(mixed $value): ?array
    {
        if (! is_array($value)) {
            return $this->simple('background-color', $value, '{{VALUE}}');
        }

        $type = $value['type'] ?? 'classic';
        $declarations = [];

        if ($type === 'gradient') {
            $from = $this->sanitiseValue($value['gradient_from'] ?? '#ffffff');
            $to = $this->sanitiseValue($value['gradient_to'] ?? '#000000');
            $angle = (int) ($value['gradient_angle'] ?? 180);

            $declarations['background-image'] = "linear-gradient({$angle}deg, {$from} 0%, {$to} 100%)";

            return $declarations;
        }

        if (filled($value['color'] ?? null)) {
            $declarations['background-color'] = $this->sanitiseValue($value['color']);
        }

        if (filled($value['image'] ?? null)) {
            $url = \App\Cms\Builder\Blocks\Block::imageUrl($value['image']);

            if ($url) {
                // The URL is quoted and stripped of quote characters, so a
                // crafted filename cannot break out of the url() function.
                $declarations['background-image'] = "url('".$this->sanitiseUrl($url)."')";
                $declarations['background-size'] = $this->sanitiseValue($value['size'] ?? 'cover');
                $declarations['background-position'] = $this->sanitiseValue($value['position'] ?? 'center center');
                $declarations['background-repeat'] = $this->sanitiseValue($value['repeat'] ?? 'no-repeat');

                if (($value['attachment'] ?? '') === 'fixed') {
                    $declarations['background-attachment'] = 'fixed';
                }
            }
        }

        return $declarations ?: null;
    }

    // Output ---------------------------------------------------------------

    private function toCss(): string
    {
        $css = '';

        // Desktop first, then each breakpoint in descending width, so the
        // narrower rule always wins.
        $css .= $this->blockFor($this->rules[''] ?? []);

        foreach (self::BREAKPOINTS as $device => $width) {
            $block = $this->blockFor($this->rules[$device] ?? []);

            if ($block !== '') {
                $css .= "@media(max-width:{$width}px){".$block.'}';
            }
        }

        return $css;
    }

    private function blockFor(array $selectors): string
    {
        $css = '';

        foreach ($selectors as $selector => $declarations) {
            if ($declarations === []) {
                continue;
            }

            $body = '';

            foreach ($declarations as $property => $value) {
                $body .= $property.':'.$value.';';
            }

            $css .= $selector.'{'.$body.'}';
        }

        return $css;
    }

    private function substitute(string $template, string $value): string
    {
        return str_replace('{{VALUE}}', $value, $template);
    }

    // Sanitising -----------------------------------------------------------

    /**
     * Strip anything that could terminate a declaration or start a new rule.
     * Also removes CSS comment markers, which are a classic way to smuggle
     * content past a naive filter.
     */
    private function sanitiseValue(string $value): string
    {
        $value = str_replace(['{', '}', ';', '<', '>', '\\', '/*', '*/'], '', $value);
        $value = preg_replace('/(expression|javascript:|behaviour:|behavior:|@import)/i', '', $value);

        return trim($value);
    }

    private function sanitiseUrl(string $url): string
    {
        $url = str_replace(['"', "'", '(', ')', '{', '}', ';', '\\', "\n", "\r"], '', $url);

        // Only http(s), root-relative and data-image URLs reach a stylesheet.
        if (! preg_match('#^(https?://|/|data:image/)#i', $url)) {
            return '';
        }

        return trim($url);
    }

    /**
     * Selectors come from block classes, not from stored settings, so the
     * combinators in them are legitimate and must survive. The only
     * user-controlled part is the element id, which the renderer has already
     * constrained to [a-z0-9]. Stripping what could open a new rule is enough.
     */
    private function sanitiseSelector(string $selector): string
    {
        return trim(str_replace(['{', '}', ';', '<'], '', $selector));
    }
}
