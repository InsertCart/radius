<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

/**
 * The site's contact form, posting to the same endpoint and inbox as the
 * theme's own. Placing one in a layout does not create a second, parallel
 * form system.
 */
class ContactFormBlock extends Block
{
    public static function type(): string
    {
        return 'contact-form';
    }

    public static function name(): string
    {
        return 'Contact form';
    }

    public static function icon(): string
    {
        return 'form';
    }

    public static function category(): string
    {
        return 'content';
    }

    public static function order(): int
    {
        return 50;
    }

    public static function requiresModule(): ?string
    {
        return 'contact';
    }

    public static function keywords(): array
    {
        return ['enquiry', 'message', 'email'];
    }

    public static function controls(): array
    {
        return [
            Control::toggle('show_phone', 'Ask for a phone number')->default(true),
            Control::toggle('show_subject', 'Ask for a subject')->default(true),
            Control::text('button_text', 'Button label')->default('Send message'),
            Control::text('success_text', 'Message after sending')
                ->default('Thanks for getting in touch. We will reply shortly.'),

            Control::choose('button_align', 'Button alignment', [
                'flex-start' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'flex-end' => ['label' => 'Right', 'icon' => 'align-right'],
                'stretch' => ['label' => 'Full width', 'icon' => 'align-stretch'],
            ])->default('flex-start')
                ->selector('{{WRAPPER}} .cb-form__actions', 'justify-content'),

            Control::slider('gap', 'Space between fields')
                ->min(4)->max(40)->units(['px'])
                ->default(['size' => 16, 'unit' => 'px'])
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-form', 'gap'),

            Control::dimensions('field_radius', 'Field corners')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-form input, {{WRAPPER}} .cb-form textarea', 'border-radius'),

            Control::color('button_bg', 'Button background')
                ->tab(Control::TAB_STYLE)
                ->default('var(--cb-color-primary, #2563eb)')
                ->selector('{{WRAPPER}} .cb-form button', 'background-color'),
        ];
    }
}
