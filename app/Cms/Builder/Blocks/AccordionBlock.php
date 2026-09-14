<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

/**
 * Collapsible panels, commonly used for FAQs.
 *
 * When "mark up as FAQ" is on, the block also emits FAQPage structured data,
 * which is what earns the expandable answers in search results.
 */
class AccordionBlock extends Block
{
    public static function type(): string
    {
        return 'accordion';
    }

    public static function name(): string
    {
        return 'Accordion';
    }

    public static function icon(): string
    {
        return 'accordion';
    }

    public static function category(): string
    {
        return 'content';
    }

    public static function order(): int
    {
        return 20;
    }

    public static function keywords(): array
    {
        return ['faq', 'toggle', 'collapse', 'questions'];
    }

    public static function controls(): array
    {
        return [
            Control::repeater('items', 'Panels', [
                Control::text('title', 'Title')->default('Question goes here'),
                Control::richtext('content', 'Content')->default('<p>And the answer goes here.</p>'),
                Control::toggle('open', 'Open by default'),
            ])->default([
                ['title' => 'What is your return policy?', 'content' => '<p>Explain it here.</p>', 'open' => true],
                ['title' => 'How long does delivery take?', 'content' => '<p>Explain it here.</p>', 'open' => false],
            ]),

            Control::toggle('single', 'Only one panel open at a time')->default(true),

            Control::toggle('faq_schema', 'Mark up as an FAQ for search engines')
                ->default(true)
                ->help('Adds FAQPage structured data, which can show your answers directly in search results.'),

            Control::icon('icon', 'Toggle icon')->default('chevron-down'),

            // Style ------------------------------------------------------
            Control::color('title_color', 'Title colour')
                ->tab(Control::TAB_STYLE)->section('Title')
                ->selector('{{WRAPPER}} .cb-accordion__title', 'color'),

            Control::typography('title_typography', 'Title typography')
                ->tab(Control::TAB_STYLE)->section('Title')
                ->selector('{{WRAPPER}} .cb-accordion__title', 'typography'),

            Control::color('title_bg', 'Title background')
                ->tab(Control::TAB_STYLE)->section('Title')
                ->selector('{{WRAPPER}} .cb-accordion__title', 'background-color'),

            Control::dimensions('title_padding', 'Title padding')
                ->tab(Control::TAB_STYLE)->section('Title')
                ->selector('{{WRAPPER}} .cb-accordion__title', 'padding'),

            Control::color('content_color', 'Content colour')
                ->tab(Control::TAB_STYLE)->section('Content')
                ->selector('{{WRAPPER}} .cb-accordion__panel', 'color'),

            Control::dimensions('content_padding', 'Content padding')
                ->tab(Control::TAB_STYLE)->section('Content')
                ->selector('{{WRAPPER}} .cb-accordion__panel', 'padding'),

            Control::border('border', 'Border')
                ->tab(Control::TAB_STYLE)->section('Panel')
                ->selector('{{WRAPPER}} .cb-accordion__item', 'border'),

            Control::slider('gap', 'Space between panels')
                ->min(0)->max(40)->units(['px'])
                ->default(['size' => 8, 'unit' => 'px'])
                ->tab(Control::TAB_STYLE)->section('Panel')
                ->selector('{{WRAPPER}} .cb-accordion', 'gap'),
        ];
    }
}
