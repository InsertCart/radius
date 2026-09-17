<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

/**
 * Links to the site's social profiles, taken from Settings so they stay in one
 * place, with the option to override per instance.
 */
class SocialIconsBlock extends Block
{
    public static function type(): string
    {
        return 'social-icons';
    }

    public static function name(): string
    {
        return 'Social icons';
    }

    public static function icon(): string
    {
        return 'share';
    }

    public static function category(): string
    {
        return 'site';
    }

    public static function order(): int
    {
        return 6;
    }

    public static function controls(): array
    {
        return [
            Control::toggle('use_settings', 'Use the links from Settings')->default(true),

            Control::repeater('links', 'Links', [
                Control::select('network', 'Network', [
                    'facebook' => 'Facebook', 'instagram' => 'Instagram', 'twitter' => 'X / Twitter',
                    'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp',
                    'tiktok' => 'TikTok', 'pinterest' => 'Pinterest', 'github' => 'GitHub',
                ])->default('facebook'),
                Control::text('url', 'URL'),
            ])->when('use_settings', false),

            Control::select('shape', 'Shape', [
                'none' => 'Icon only', 'circle' => 'Circle', 'square' => 'Rounded square',
            ])->default('circle'),

            Control::choose('align', 'Alignment', [
                'flex-start' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'flex-end' => ['label' => 'Right', 'icon' => 'align-right'],
            ])->default('flex-start')->responsive()
                ->selector('{{WRAPPER}} .cb-social', 'justify-content'),

            Control::slider('size', 'Icon size')
                ->min(12)->max(60)->units(['px'])
                ->default(['size' => 18, 'unit' => 'px'])
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-social svg', 'width')
                ->selector('{{WRAPPER}} .cb-social svg', 'height'),

            Control::slider('gap', 'Spacing')
                ->min(0)->max(40)->units(['px'])
                ->default(['size' => 10, 'unit' => 'px'])
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-social', 'gap'),

            Control::color('color', 'Icon colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-social a', 'color'),

            Control::color('bg_color', 'Background')
                ->when('shape', ['circle', 'square'])
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-social a', 'background-color'),
        ];
    }

    public function data(array $settings, array $context = []): array
    {
        if (empty($settings['use_settings'])) {
            return ['links' => array_filter(
                $settings['links'] ?? [],
                fn ($link) => filled($link['url'] ?? null)
            )];
        }

        $networks = [
            'facebook' => setting('social_facebook'),
            'instagram' => setting('social_instagram'),
            'twitter' => setting('social_twitter'),
            'linkedin' => setting('social_linkedin'),
            'youtube' => setting('social_youtube'),
            'whatsapp' => whatsapp_url(),
        ];

        $links = [];

        foreach (array_filter($networks) as $network => $url) {
            $links[] = ['network' => $network, 'url' => $url];
        }

        return ['links' => $links];
    }
}
