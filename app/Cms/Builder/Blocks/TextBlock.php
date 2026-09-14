<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class TextBlock extends Block
{
    public static function type(): string
    {
        return 'text';
    }

    public static function name(): string
    {
        return 'Text';
    }

    public static function icon(): string
    {
        return 'text';
    }

    public static function order(): int
    {
        return 2;
    }

    public static function keywords(): array
    {
        return ['paragraph', 'editor', 'content', 'copy'];
    }

    public static function controls(): array
    {
        return [
            Control::richtext('content', 'Content')
                ->default('<p>Click to edit this text. Write something your visitors will find useful.</p>'),

            Control::choose('align', 'Alignment', [
                'left' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'right' => ['label' => 'Right', 'icon' => 'align-right'],
                'justify' => ['label' => 'Justify', 'icon' => 'align-justify'],
            ])->responsive()
                ->selector('{{WRAPPER}}', 'text-align'),

            Control::color('color', 'Text colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-text', 'color'),

            Control::typography('typography', 'Typography')
                ->tab(Control::TAB_STYLE)
                ->responsive()
                ->selector('{{WRAPPER}} .cb-text', 'typography'),

            Control::color('link_color', 'Link colour')
                ->tab(Control::TAB_STYLE)->section('Links')
                ->selector('{{WRAPPER}} .cb-text a', 'color'),
        ];
    }
}
