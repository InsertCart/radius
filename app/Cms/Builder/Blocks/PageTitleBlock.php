<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

/**
 * The current page's own title, for building reusable headers that adapt to
 * whatever content they sit above.
 */
class PageTitleBlock extends Block
{
    public static function type(): string
    {
        return 'page-title';
    }

    public static function name(): string
    {
        return 'Page title';
    }

    public static function icon(): string
    {
        return 'heading';
    }

    public static function category(): string
    {
        return 'site';
    }

    public static function order(): int
    {
        return 10;
    }

    public static function keywords(): array
    {
        return ['dynamic', 'title', 'breadcrumb'];
    }

    public static function controls(): array
    {
        return [
            Control::select('tag', 'HTML tag', [
                'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'div' => 'Div',
            ])->default('h1'),

            Control::toggle('show_breadcrumbs', 'Show breadcrumbs'),

            Control::choose('align', 'Alignment', [
                'left' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'right' => ['label' => 'Right', 'icon' => 'align-right'],
            ])->default('left')->responsive()
                ->selector('{{WRAPPER}}', 'text-align'),

            Control::color('color', 'Colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-page-title', 'color'),

            Control::typography('typography', 'Typography')
                ->tab(Control::TAB_STYLE)
                ->responsive()
                ->selector('{{WRAPPER}} .cb-page-title', 'typography'),
        ];
    }

    public function data(array $settings, array $context = []): array
    {
        $model = $context['model'] ?? null;

        $title = $model->title
            ?? $model->name
            ?? ($context['editing'] ?? false ? 'Page title' : setting('site_name'));

        return ['title' => $title];
    }
}
