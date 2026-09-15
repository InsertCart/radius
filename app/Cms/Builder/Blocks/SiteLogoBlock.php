<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

/**
 * The site logo from General settings, or a one-off image. Using the setting
 * means a header built in the editor still follows a rebrand.
 */
class SiteLogoBlock extends Block
{
    public static function type(): string
    {
        return 'site-logo';
    }

    public static function name(): string
    {
        return 'Site logo';
    }

    public static function icon(): string
    {
        return 'logo';
    }

    public static function category(): string
    {
        return 'site';
    }

    public static function order(): int
    {
        return 1;
    }

    public static function keywords(): array
    {
        return ['brand', 'header', 'identity'];
    }

    public static function controls(): array
    {
        return [
            Control::select('source', 'Source', [
                'setting' => 'Use the logo from settings',
                'custom' => 'Use a custom image',
                'text' => 'Show the site name as text',
            ])->default('setting'),

            Control::image('image', 'Logo')->when('source', 'custom'),

            // A logo block can be dropped onto any section, so the background
            // behind it is the editor's choice and not something the CMS can
            // work out. Phrased as the background rather than the ink because
            // that is the part the person placing it is looking at.
            Control::select('on', 'Background behind it', [
                'auto' => 'Follow the site colour scheme',
                'light' => 'Light - use the dark logo',
                'dark' => 'Dark - use the light logo',
            ])->default('auto')->when('source', 'setting'),

            Control::toggle('link_home', 'Link to the homepage')->default(true),

            Control::choose('align', 'Alignment', [
                'flex-start' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'flex-end' => ['label' => 'Right', 'icon' => 'align-right'],
            ])->default('flex-start')->responsive()
                ->selector('{{WRAPPER}}', 'justify-content'),

            Control::slider('width', 'Width')
                ->min(20)->max(500)->units(['px', '%'])
                ->default(['size' => 140, 'unit' => 'px'])
                ->responsive()
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-logo img', 'width'),

            Control::color('text_color', 'Text colour')
                ->when('source', 'text')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-logo', 'color'),

            Control::typography('typography', 'Typography')
                ->when('source', 'text')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-logo', 'typography'),
        ];
    }
}
