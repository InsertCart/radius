<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class TestimonialBlock extends Block
{
    public static function type(): string
    {
        return 'testimonial';
    }

    public static function name(): string
    {
        return 'Testimonial';
    }

    public static function icon(): string
    {
        return 'quote';
    }

    public static function category(): string
    {
        return 'content';
    }

    public static function order(): int
    {
        return 30;
    }

    public static function keywords(): array
    {
        return ['review', 'quote', 'customer'];
    }

    public static function controls(): array
    {
        return [
            Control::textarea('quote', 'Quote')
                ->default('This product changed how our team works. I would recommend it to anyone.'),
            Control::text('author', 'Name')->default('Jane Smith'),
            Control::text('role', 'Role or company')->default('Operations Lead, Acme'),
            Control::image('avatar', 'Photo'),

            Control::number('rating', 'Star rating')->min(0)->max(5)->default(5)
                ->help('Set to 0 to hide the stars.'),

            Control::choose('align', 'Alignment', [
                'left' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'right' => ['label' => 'Right', 'icon' => 'align-right'],
            ])->default('center')
                ->selector('{{WRAPPER}} .cb-testimonial', 'text-align'),

            Control::color('quote_color', 'Quote colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-testimonial__quote', 'color'),

            Control::typography('quote_typography', 'Quote typography')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-testimonial__quote', 'typography'),

            Control::slider('avatar_size', 'Photo size')
                ->min(32)->max(160)->units(['px'])
                ->default(['size' => 64, 'unit' => 'px'])
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-testimonial__avatar', 'width')
                ->selector('{{WRAPPER}} .cb-testimonial__avatar', 'height'),

            Control::color('star_color', 'Star colour')
                ->tab(Control::TAB_STYLE)
                ->default('#f59e0b')
                ->selector('{{WRAPPER}} .cb-testimonial__stars', 'color'),
        ];
    }
}
