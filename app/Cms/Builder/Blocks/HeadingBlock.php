<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class HeadingBlock extends Block
{
    public static function type(): string
    {
        return 'heading';
    }

    public static function name(): string
    {
        return 'Heading';
    }

    public static function icon(): string
    {
        return 'heading';
    }

    public static function order(): int
    {
        return 1;
    }

    public static function keywords(): array
    {
        return ['title', 'h1', 'h2', 'text'];
    }

    public static function controls(): array
    {
        return [
            Control::text('text', 'Heading')
                ->default('Add your heading here')
                ->placeholder('Enter your heading'),

            Control::select('tag', 'HTML tag', [
                'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3',
                'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'p' => 'Paragraph',
            ])->default('h2')
                ->help('Use one H1 per page. Search engines read this as the page structure.'),

            Control::link('link', 'Link')->help('Optional. Wraps the heading in a link.'),

            Control::choose('align', 'Alignment', [
                'left' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'right' => ['label' => 'Right', 'icon' => 'align-right'],
            ])->responsive()
                ->selector('{{WRAPPER}}', 'text-align'),

            // Style ------------------------------------------------------
            Control::color('color', 'Text colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-heading', 'color'),

            Control::typography('typography', 'Typography')
                ->tab(Control::TAB_STYLE)
                ->responsive()
                ->selector('{{WRAPPER}} .cb-heading', 'typography'),

            Control::shadow('text_shadow', 'Text shadow')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-heading', 'text-shadow'),
        ];
    }
}
