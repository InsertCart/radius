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
                'all' => 'Whole site',
                'blog' => 'Blog posts',
                'shop' => 'Products',
            ])->default('blog'),

            Control::select('instant', 'Results while typing', [
                'site' => 'Use the site setting',
                'off' => 'Off for this box',
            ])->default('site'),

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
        $type = match ($settings['target'] ?? 'blog') {
            'shop' => 'product',
            'all' => 'all',
            default => 'post',
        };

        // "Whole site" needs the /search page. With it switched off, fall
        // back to a listing that exists rather than a form that 404s.
        if ($type === 'all' && ! \Illuminate\Support\Facades\Route::has('search')) {
            $type = \Illuminate\Support\Facades\Route::has('shop.index') ? 'product' : 'post';
        }

        return [
            'type' => $type,
            'action' => search()->formAction($type),
            'instant' => ($settings['instant'] ?? 'site') !== 'off',
        ];
    }
}
