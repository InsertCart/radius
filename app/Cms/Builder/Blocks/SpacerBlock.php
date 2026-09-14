<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class SpacerBlock extends Block
{
    public static function type(): string
    {
        return 'spacer';
    }

    public static function name(): string
    {
        return 'Spacer';
    }

    public static function icon(): string
    {
        return 'spacer';
    }

    public static function category(): string
    {
        return 'layout';
    }

    public static function order(): int
    {
        return 1;
    }

    public static function keywords(): array
    {
        return ['gap', 'space', 'margin'];
    }

    public static function controls(): array
    {
        return [
            Control::slider('height', 'Height')
                ->min(0)->max(400)->units(['px', 'vh'])
                ->default(['size' => 50, 'unit' => 'px'])
                ->responsive()
                ->selector('{{WRAPPER}} .cb-spacer', 'height'),
        ];
    }
}
