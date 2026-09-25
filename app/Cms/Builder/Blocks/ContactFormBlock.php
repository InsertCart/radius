<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;
use App\Cms\Forms\ContactFormSchema;

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

            // Required fields ---------------------------------------------
            Control::toggle('require_name', 'Name is required')
                ->section('Required fields')->default(true),

            Control::toggle('require_phone', 'Phone is required')
                ->section('Required fields')->when('show_phone', true),

            Control::toggle('require_subject', 'Subject is required')
                ->section('Required fields')->when('show_subject', true),

            Control::toggle('require_message', 'Message is required')
                ->section('Required fields')->default(true),

            Control::notice('required_note', 'The email address always stays required')
                ->section('Required fields')
                ->help('It is the address every reply goes to, and the one the inbox is searched on.'),

            // Extra fields -------------------------------------------------
            Control::repeater('extra_fields', 'Extra fields', [
                Control::text('label', 'Label')->default('New field'),

                Control::select('type', 'Type', ContactFormSchema::TYPES)
                    ->default('text'),

                Control::textarea('options', 'Choices, one per line')
                    ->when('type', 'select')
                    ->placeholder("Sales\nSupport\nSomething else"),

                Control::text('placeholder', 'Placeholder'),

                Control::toggle('required', 'Required'),

                Control::choose('width', 'Width', [
                    'full' => ['label' => 'Full width', 'icon' => 'align-stretch'],
                    'half' => ['label' => 'Half width', 'icon' => 'grid'],
                ])->default('full'),
            ])
                ->section('Extra fields')
                ->help('Answers arrive with the message and are listed under it in the inbox.'),

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
                ->selector('{{WRAPPER}} .cb-form input, {{WRAPPER}} .cb-form textarea, {{WRAPPER}} .cb-form select', 'border-radius'),

            Control::color('button_bg', 'Button background')
                ->tab(Control::TAB_STYLE)
                ->default('var(--cb-color-primary, #2563eb)')
                ->selector('{{WRAPPER}} .cb-form button', 'background-color'),
        ];
    }

    /**
     * The form carries its own schema, encrypted, so the shared endpoint
     * validates what this widget actually asked for rather than whatever
     * field names turn up in the request.
     */
    public function data(array $settings, array $context = []): array
    {
        $schema = ContactFormSchema::fromSettings($settings);

        return [
            'schema' => $schema,
            'schemaToken' => ContactFormSchema::token($schema),
        ];
    }
}
