<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class NewsletterBlock extends Block
{
    public static function type(): string
    {
        return 'newsletter';
    }

    public static function name(): string
    {
        return 'Newsletter signup';
    }

    public static function icon(): string
    {
        return 'mail';
    }

    public static function category(): string
    {
        return 'content';
    }

    public static function order(): int
    {
        return 51;
    }

    public static function requiresModule(): ?string
    {
        return 'newsletter';
    }

    public static function controls(): array
    {
        return [
            Control::text('heading', 'Heading')->default('Subscribe to our newsletter'),
            Control::textarea('description', 'Description')
                ->default('Occasional updates, no spam.'),
            Control::text('placeholder', 'Field placeholder')->default('you@example.com'),
            Control::text('button_text', 'Button label')->default('Subscribe'),
            Control::toggle('show_name', 'Ask for a name'),
            Control::toggle('inline', 'Put the field and button on one line')->default(true),

            Control::color('heading_color', 'Heading colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-newsletter__heading', 'color'),

            Control::color('button_bg', 'Button background')
                ->tab(Control::TAB_STYLE)
                ->default('var(--cb-color-primary, #2563eb)')
                ->selector('{{WRAPPER}} .cb-newsletter button', 'background-color'),

            Control::dimensions('radius', 'Rounded corners')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-newsletter input, {{WRAPPER}} .cb-newsletter button', 'border-radius'),
        ];
    }
}
