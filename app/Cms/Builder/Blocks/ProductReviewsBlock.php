<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class ProductReviewsBlock extends ProductBlock
{
    public static function type(): string
    {
        return 'product-reviews';
    }

    public static function name(): string
    {
        return 'Reviews';
    }

    public static function icon(): string
    {
        return 'chat';
    }

    public static function order(): int
    {
        return 70;
    }

    public static function controls(): array
    {
        return [
            Control::text('heading', 'Heading')->default('Reviews'),
            Control::toggle('show_form', 'Let shoppers write a review')->default(true),
            Control::text('empty_text', 'When there are no reviews')->default('No reviews yet. Be the first to write one.'),

            Control::color('heading_color', 'Heading colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-reviews__heading', 'color'),
            Control::color('star_color', 'Star colour')
                ->tab(Control::TAB_STYLE)
                ->default('#f59e0b')
                ->selector('{{WRAPPER}} .cb-reviews__stars', 'color'),
            Control::color('button_bg', 'Submit button')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-reviews .cb-button', 'background-color'),
        ];
    }
}
