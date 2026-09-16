<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class ProductRatingBlock extends ProductBlock
{
    public static function type(): string
    {
        return 'product-rating';
    }

    public static function name(): string
    {
        return 'Star rating';
    }

    public static function icon(): string
    {
        return 'star';
    }

    public static function order(): int
    {
        return 25;
    }

    public static function controls(): array
    {
        return [
            Control::toggle('show_count', 'Show the review count')->default(true),
            Control::toggle('hide_when_empty', 'Hide until the first review')->default(true),

            Control::color('star_color', 'Star colour')
                ->tab(Control::TAB_STYLE)
                ->default('#f59e0b')
                ->selector('{{WRAPPER}} .cb-rating__stars', 'color'),
        ];
    }
}
