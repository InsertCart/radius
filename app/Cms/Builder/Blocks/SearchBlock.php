<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class SearchBlock extends Block
{
    public static function type(): string
    {
        return 'search';
    }

    public static function name(): string
    {
        return 'Search';
    }

    public static function icon(): string
    {
        return 'search';
    }

    public static function category(): string
    {
        return 'site';
    }

    public static function order(): int
    {
        return 5;
    }

    public static function controls(): array
    {
        return [
            Control::select('target', 'Search in', [
                'blog' => 'Blog posts',
                'shop' => 'Products',
            ])->default('blog'),

            Control::text('placeholder', 'Placeholder')->default('Search...'),
            Control::toggle('show_button', 'Show search button')->default(true),
            Control::text('button_text', 'Button label')->default('Search')->when('show_button', true),

            Control::dimensions('radius', 'Rounded corners')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-search input', 'border-radius'),

            Control::color('bg_color', 'Field background')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-search input', 'background-color'),

            Control::border('border', 'Border')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-search input', 'border'),
        ];
    }

    public function data(array $settings, array $context = []): array
    {
        $target = $settings['target'] ?? 'blog';

        return [
            'action' => $target === 'shop'
                ? safe_route('shop.index', [], '#')
                : safe_route('blog.index', [], '#'),
        ];
    }
}
