<?php

namespace App\Cms\Builder;

/**
 * Controls for the layout skeleton itself.
 *
 * A layout is a tree of sections, each holding columns, each holding widgets.
 * Sections and columns are not widgets - they have no block class - so their
 * settings panels are declared here instead.
 */
class SectionSchema
{
    /** Widths offered when splitting a section into columns. */
    public const COLUMN_PRESETS = [
        '100' => [100],
        '50-50' => [50, 50],
        '33-33-33' => [33.33, 33.33, 33.33],
        '25-25-25-25' => [25, 25, 25, 25],
        '66-33' => [66.66, 33.33],
        '33-66' => [33.33, 66.66],
        '25-75' => [25, 75],
        '75-25' => [75, 25],
        '20-60-20' => [20, 60, 20],
        '25-50-25' => [25, 50, 25],
    ];

    /** @return Control[] */
    public static function controls(): array
    {
        return array_merge([
            // Layout ------------------------------------------------------
            Control::choose('content_width', 'Content width', [
                'boxed' => ['label' => 'Boxed', 'icon' => 'boxed'],
                'full' => ['label' => 'Full width', 'icon' => 'full'],
                // No side padding or gap: for a theme section that draws its
                // own full-bleed band and inner container.
                'edge' => ['label' => 'Edge to edge', 'icon' => 'full'],
            ])->default('boxed')->section('Layout'),

            Control::slider('max_width', 'Maximum width')
                ->min(320)->max(1920)->units(['px'])->default(['size' => 1152, 'unit' => 'px'])
                ->when('content_width', 'boxed')
                ->section('Layout')
                ->selector('{{WRAPPER}} > .cb-section-inner', 'max-width'),

            Control::slider('gap', 'Column gap')
                ->min(0)->max(120)->units(['px'])->default(['size' => 24, 'unit' => 'px'])
                ->responsive()
                ->section('Layout')
                ->selector('{{WRAPPER}} > .cb-section-inner', 'gap'),

            Control::slider('min_height', 'Minimum height')
                ->min(0)->max(1000)->units(['px', 'vh'])
                ->responsive()
                ->section('Layout')
                ->selector('{{WRAPPER}}', 'min-height'),

            Control::choose('vertical_align', 'Vertical alignment', [
                'flex-start' => ['label' => 'Top', 'icon' => 'align-top'],
                'center' => ['label' => 'Middle', 'icon' => 'align-middle'],
                'flex-end' => ['label' => 'Bottom', 'icon' => 'align-bottom'],
                'stretch' => ['label' => 'Stretch', 'icon' => 'align-stretch'],
            ])->default('stretch')
                ->section('Layout')
                ->selector('{{WRAPPER}} > .cb-section-inner', 'align-items'),

            Control::select('stack_on', 'Stack columns on', [
                'tablet' => 'Tablet and below',
                'mobile' => 'Mobile only',
                'none' => 'Never stack',
            ])->default('tablet')->section('Layout')
                ->help('Below this size the columns sit one above the other.'),

            Control::text('html_tag', 'HTML tag')
                ->default('section')
                ->section('Layout')
                ->help('section, div, header, footer, main or article.'),
        ], self::styleControls(), self::commonControls());
    }

    /** @return Control[] */
    public static function columnControls(): array
    {
        return array_merge([
            Control::slider('width', 'Column width')
                ->min(5)->max(100)->units(['%'])
                ->responsive()
                ->section('Layout'),

            Control::choose('vertical_align', 'Vertical alignment', [
                'flex-start' => ['label' => 'Top', 'icon' => 'align-top'],
                'center' => ['label' => 'Middle', 'icon' => 'align-middle'],
                'flex-end' => ['label' => 'Bottom', 'icon' => 'align-bottom'],
            ])->section('Layout')
                ->selector('{{WRAPPER}} > .cb-column-inner', 'justify-content'),

            Control::choose('horizontal_align', 'Horizontal alignment', [
                'flex-start' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'flex-end' => ['label' => 'Right', 'icon' => 'align-right'],
                'stretch' => ['label' => 'Stretch', 'icon' => 'align-stretch'],
            ])->default('stretch')
                ->section('Layout')
                ->selector('{{WRAPPER}} > .cb-column-inner', 'align-items'),

            Control::slider('gap', 'Space between widgets')
                ->min(0)->max(100)->units(['px'])
                ->responsive()
                ->section('Layout')
                ->selector('{{WRAPPER}} > .cb-column-inner', 'gap'),
        ], self::styleControls(), self::commonControls());
    }

    /**
     * Background, border, padding: the settings a section and a column share.
     *
     * @return Control[]
     */
    private static function styleControls(): array
    {
        return [
            Control::background('background', 'Background')
                ->tab(Control::TAB_STYLE)->section('Background')
                ->selector('{{WRAPPER}}', 'background'),

            Control::color('overlay_color', 'Overlay')
                ->tab(Control::TAB_STYLE)->section('Background')
                ->help('Sits above a background image to keep text readable.')
                ->selector('{{WRAPPER}} > .cb-overlay', 'background-color'),

            Control::border('border', 'Border')
                ->tab(Control::TAB_STYLE)->section('Border')
                ->selector('{{WRAPPER}}', 'border'),

            Control::dimensions('border_radius', 'Rounded corners')
                ->tab(Control::TAB_STYLE)->section('Border')
                ->responsive()
                ->selector('{{WRAPPER}}', 'border-radius'),

            Control::shadow('box_shadow', 'Shadow')
                ->tab(Control::TAB_STYLE)->section('Border')
                ->selector('{{WRAPPER}}', 'box-shadow'),
        ];
    }

    /**
     * The Advanced tab, shared by sections, columns and every widget: spacing,
     * visibility, custom classes and animation.
     *
     * @return Control[]
     */
    public static function commonControls(): array
    {
        return [
            Control::dimensions('margin', 'Margin')
                ->tab(Control::TAB_ADVANCED)->section('Spacing')
                ->responsive()
                ->selector('{{WRAPPER}}', 'margin'),

            Control::dimensions('padding', 'Padding')
                ->tab(Control::TAB_ADVANCED)->section('Spacing')
                ->responsive()
                ->selector('{{WRAPPER}}', 'padding'),

            Control::slider('z_index', 'Z-index')
                ->min(-10)->max(999)->units([''])
                ->tab(Control::TAB_ADVANCED)->section('Positioning')
                ->selector('{{WRAPPER}}', 'z-index'),

            Control::toggle('hide_desktop', 'Hide on desktop')
                ->tab(Control::TAB_ADVANCED)->section('Visibility'),
            Control::toggle('hide_tablet', 'Hide on tablet')
                ->tab(Control::TAB_ADVANCED)->section('Visibility'),
            Control::toggle('hide_mobile', 'Hide on mobile')
                ->tab(Control::TAB_ADVANCED)->section('Visibility'),

            Control::select('animation', 'Entrance animation', [
                '' => 'None',
                'fade-in' => 'Fade in',
                'fade-up' => 'Fade up',
                'fade-down' => 'Fade down',
                'fade-left' => 'Fade from left',
                'fade-right' => 'Fade from right',
                'zoom-in' => 'Zoom in',
            ])->tab(Control::TAB_ADVANCED)->section('Motion'),

            Control::text('css_id', 'CSS ID')
                ->tab(Control::TAB_ADVANCED)->section('Attributes'),

            Control::text('css_classes', 'CSS classes')
                ->tab(Control::TAB_ADVANCED)->section('Attributes')
                ->help('Space separated. Useful for targeting with custom CSS.'),
        ];
    }

    /** Schema for the editor's section and column panels. */
    public static function schema(): array
    {
        return [
            'section' => self::group(self::controls()),
            'column' => self::group(self::columnControls()),
            'presets' => self::COLUMN_PRESETS,
        ];
    }

    /** @param Control[] $controls */
    private static function group(array $controls): array
    {
        $tabs = [];

        foreach ($controls as $control) {
            $definition = $control->toArray();
            $tabs[$definition['tab']][$definition['section'] ?? 'General'][] = $definition;
        }

        return $tabs;
    }

    /** Defaults a freshly inserted section or column starts with. */
    public static function defaults(string $type): array
    {
        $controls = $type === 'column' ? self::columnControls() : self::controls();
        $defaults = [];

        foreach ($controls as $control) {
            $definition = $control->toArray();

            if ($definition['default'] !== null) {
                $defaults[$definition['key']] = $definition['default'];
            }
        }

        return $defaults;
    }
}
