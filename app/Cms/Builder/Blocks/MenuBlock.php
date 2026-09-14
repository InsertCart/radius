<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;
use App\Models\Menu;

/**
 * Renders one of the CMS's navigation menus. This is what lets an admin build
 * a header in the editor without losing the menu manager.
 */
class MenuBlock extends Block
{
    public static function type(): string
    {
        return 'menu';
    }

    public static function name(): string
    {
        return 'Navigation menu';
    }

    public static function icon(): string
    {
        return 'menu';
    }

    public static function category(): string
    {
        return 'site';
    }

    public static function order(): int
    {
        return 2;
    }

    public static function keywords(): array
    {
        return ['nav', 'links', 'header', 'navigation'];
    }

    public static function controls(): array
    {
        return [
            Control::source('menu', 'Menu', 'menus')->default('header'),

            Control::choose('layout', 'Layout', [
                'horizontal' => ['label' => 'Horizontal', 'icon' => 'align-right'],
                'vertical' => ['label' => 'Vertical', 'icon' => 'align-bottom'],
            ])->default('horizontal'),

            Control::choose('align', 'Alignment', [
                'flex-start' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'flex-end' => ['label' => 'Right', 'icon' => 'align-right'],
            ])->default('flex-start')->responsive()
                ->selector('{{WRAPPER}} .cb-menu', 'justify-content'),

            Control::toggle('mobile_toggle', 'Collapse to a menu button on mobile')->default(true),

            Control::slider('gap', 'Space between links')
                ->min(0)->max(60)->units(['px'])
                ->default(['size' => 24, 'unit' => 'px'])
                ->responsive()
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-menu', 'gap'),

            Control::color('link_color', 'Link colour')
                ->tab(Control::TAB_STYLE)->section('Normal')
                ->selector('{{WRAPPER}} .cb-menu a', 'color'),

            Control::typography('typography', 'Typography')
                ->tab(Control::TAB_STYLE)->section('Normal')
                ->selector('{{WRAPPER}} .cb-menu a', 'typography'),

            Control::color('hover_color', 'Link colour on hover')
                ->tab(Control::TAB_STYLE)->section('Hover')
                ->selector('{{WRAPPER}} .cb-menu a:hover', 'color'),
        ];
    }

    public function data(array $settings, array $context = []): array
    {
        $slug = $settings['menu'] ?? 'header';

        $menu = Menu::where('slug', $slug)->orWhere('id', $slug)->first();

        return [
            'items' => $menu
                ? $menu->tree()->get()->filter(fn ($item) => $item->isVisible())
                : collect(),
            'menuName' => $menu?->name,
        ];
    }

    public static function sourceOptions(): array
    {
        return Menu::orderBy('name')->pluck('name', 'slug')->all();
    }
}
