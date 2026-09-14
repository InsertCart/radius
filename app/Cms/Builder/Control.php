<?php

namespace App\Cms\Builder;

/**
 * A single field in the editor's settings panel.
 *
 * Controls are declared by blocks and rendered generically by the JavaScript
 * panel, so adding a new setting to a block never means touching the editor.
 *
 * Every control is created through a named constructor, which keeps the block
 * schemas readable:
 *
 *     Control::text('title', 'Title')->default('Hello')->placeholder('...')
 *     Control::color('bg', 'Background')->selector('{{WRAPPER}}', 'background-color')
 */
class Control
{
    public const TAB_CONTENT = 'content';
    public const TAB_STYLE = 'style';
    public const TAB_ADVANCED = 'advanced';

    private array $definition;

    private function __construct(string $type, string $key, string $label)
    {
        $this->definition = [
            'type' => $type,
            'key' => $key,
            'label' => $label,
            'tab' => self::TAB_CONTENT,
            'default' => null,
            'responsive' => false,
        ];
    }

    // Named constructors ---------------------------------------------------

    public static function text(string $key, string $label): self
    {
        return new self('text', $key, $label);
    }

    public static function textarea(string $key, string $label): self
    {
        return new self('textarea', $key, $label);
    }

    /** Rich text, edited inline on the canvas as well as in the panel. */
    public static function richtext(string $key, string $label): self
    {
        return new self('richtext', $key, $label);
    }

    public static function number(string $key, string $label): self
    {
        return new self('number', $key, $label);
    }

    /** A draggable slider with a unit selector (px, %, em, rem, vh). */
    public static function slider(string $key, string $label): self
    {
        $control = new self('slider', $key, $label);
        $control->definition['min'] = 0;
        $control->definition['max'] = 100;
        $control->definition['step'] = 1;
        $control->definition['units'] = ['px'];

        return $control;
    }

    public static function select(string $key, string $label, array $options = []): self
    {
        $control = new self('select', $key, $label);
        $control->definition['options'] = $options;

        return $control;
    }

    /** A row of icon buttons: alignment, flex direction and the like. */
    public static function choose(string $key, string $label, array $options = []): self
    {
        $control = new self('choose', $key, $label);
        $control->definition['options'] = $options;

        return $control;
    }

    public static function toggle(string $key, string $label): self
    {
        $control = new self('toggle', $key, $label);
        $control->definition['default'] = false;

        return $control;
    }

    public static function color(string $key, string $label): self
    {
        return new self('color', $key, $label);
    }

    /** Solid colour or gradient, used for section and column backgrounds. */
    public static function background(string $key, string $label): self
    {
        return new self('background', $key, $label);
    }

    public static function image(string $key, string $label): self
    {
        return new self('image', $key, $label);
    }

    public static function gallery(string $key, string $label): self
    {
        return new self('gallery', $key, $label);
    }

    public static function icon(string $key, string $label): self
    {
        return new self('icon', $key, $label);
    }

    /** A URL plus its "open in new tab" and "nofollow" options. */
    public static function link(string $key, string $label): self
    {
        return new self('link', $key, $label);
    }

    /** Font family, size, weight, line height, letter spacing and transform. */
    public static function typography(string $key, string $label): self
    {
        return new self('typography', $key, $label);
    }

    /** Top/right/bottom/left, with a link-values toggle. */
    public static function dimensions(string $key, string $label): self
    {
        $control = new self('dimensions', $key, $label);
        $control->definition['units'] = ['px', 'em', 'rem', '%'];

        return $control;
    }

    public static function border(string $key, string $label): self
    {
        return new self('border', $key, $label);
    }

    public static function shadow(string $key, string $label): self
    {
        return new self('shadow', $key, $label);
    }

    /** A repeatable group of controls: slides, tabs, list items. */
    public static function repeater(string $key, string $label, array $fields = []): self
    {
        $control = new self('repeater', $key, $label);
        $control->definition['fields'] = array_map(
            fn (Control $field) => $field->toArray(),
            $fields
        );
        $control->definition['default'] = [];

        return $control;
    }

    /** Read-only explanatory text in the panel. */
    public static function notice(string $key, string $label): self
    {
        return new self('notice', $key, $label);
    }

    /** A visual divider between groups of controls. */
    public static function separator(string $key = 'sep'): self
    {
        return new self('separator', $key.'_'.uniqid(), '');
    }

    /** Picks a CMS record: a menu, a category, a form. */
    public static function source(string $key, string $label, string $source): self
    {
        $control = new self('source', $key, $label);
        $control->definition['source'] = $source;

        return $control;
    }

    public static function code(string $key, string $label): self
    {
        return new self('code', $key, $label);
    }

    // Fluent configuration -------------------------------------------------

    public function default(mixed $value): self
    {
        $this->definition['default'] = $value;

        return $this;
    }

    public function tab(string $tab): self
    {
        $this->definition['tab'] = $tab;

        return $this;
    }

    public function section(string $label): self
    {
        $this->definition['section'] = $label;

        return $this;
    }

    public function help(string $text): self
    {
        $this->definition['help'] = $text;

        return $this;
    }

    public function placeholder(string $text): self
    {
        $this->definition['placeholder'] = $text;

        return $this;
    }

    public function options(array $options): self
    {
        $this->definition['options'] = $options;

        return $this;
    }

    public function min(float $min): self
    {
        $this->definition['min'] = $min;

        return $this;
    }

    public function max(float $max): self
    {
        $this->definition['max'] = $max;

        return $this;
    }

    public function step(float $step): self
    {
        $this->definition['step'] = $step;

        return $this;
    }

    public function units(array $units): self
    {
        $this->definition['units'] = $units;

        return $this;
    }

    /**
     * Give the control its own value per breakpoint. The style compiler then
     * emits the desktop rule plus tablet and mobile media queries.
     */
    public function responsive(bool $responsive = true): self
    {
        $this->definition['responsive'] = $responsive;

        return $this;
    }

    /**
     * Map this control straight to CSS, so changing it needs no re-render:
     * the editor can update the stylesheet in place and the change is instant.
     *
     * {{WRAPPER}} is replaced with the element's generated class, and {{VALUE}}
     * with the control's value.
     *
     *     ->selector('{{WRAPPER}} .cb-heading', 'color')
     *     ->selector('{{WRAPPER}}', 'padding', '{{VALUE}}')
     */
    public function selector(string $selector, string $property, ?string $template = null): self
    {
        $this->definition['selectors'][] = [
            'selector' => $selector,
            'property' => $property,
            'template' => $template ?? '{{VALUE}}',
        ];

        return $this;
    }

    /**
     * Only show this control when another control has a given value, which is
     * what keeps a long settings panel readable.
     *
     *     ->when('layout', 'grid')
     *     ->when('type', ['image', 'video'])
     */
    public function when(string $key, mixed $value): self
    {
        $this->definition['condition'][$key] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return $this->definition;
    }
}
