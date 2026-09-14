<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class ImageBlock extends Block
{
    public static function type(): string
    {
        return 'image';
    }

    public static function name(): string
    {
        return 'Image';
    }

    public static function icon(): string
    {
        return 'image';
    }

    public static function category(): string
    {
        return 'media';
    }

    public static function order(): int
    {
        return 1;
    }

    public static function keywords(): array
    {
        return ['photo', 'picture', 'media'];
    }

    public static function controls(): array
    {
        return [
            Control::image('image', 'Image'),

            Control::text('alt', 'Alt text')
                ->help('Describes the image for screen readers and search engines. Leave blank to use the alt text saved in the media library.'),

            Control::text('caption', 'Caption'),
            Control::link('link', 'Link'),

            Control::choose('align', 'Alignment', [
                'flex-start' => ['label' => 'Left', 'icon' => 'align-left'],
                'center' => ['label' => 'Centre', 'icon' => 'align-center'],
                'flex-end' => ['label' => 'Right', 'icon' => 'align-right'],
            ])->default('center')->responsive()
                ->selector('{{WRAPPER}}', 'justify-content'),

            // Style ------------------------------------------------------
            Control::slider('width', 'Width')
                ->min(10)->max(100)->units(['%', 'px'])
                ->responsive()
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-image img', 'width'),

            Control::slider('height', 'Height')
                ->min(0)->max(1000)->units(['px', 'vh'])
                ->responsive()
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-image img', 'height'),

            Control::select('fit', 'Fit', [
                '' => 'Default',
                'cover' => 'Cover (crop to fill)',
                'contain' => 'Contain (fit inside)',
                'fill' => 'Stretch',
            ])->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-image img', 'object-fit'),

            Control::dimensions('radius', 'Rounded corners')
                ->tab(Control::TAB_STYLE)
                ->responsive()
                ->selector('{{WRAPPER}} .cb-image img', 'border-radius'),

            Control::border('border', 'Border')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-image img', 'border'),

            Control::shadow('shadow', 'Shadow')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-image img', 'box-shadow'),

            Control::slider('opacity', 'Opacity')
                ->min(0)->max(1)->step(0.05)->units([''])
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-image img', 'opacity'),
        ];
    }

    /** Alt text falls back to what is saved against the file in the library. */
    public function data(array $settings, array $context = []): array
    {
        if (filled($settings['alt'] ?? null) || blank($settings['image'] ?? null)) {
            return ['libraryAlt' => null];
        }

        $path = is_array($settings['image']) ? ($settings['image']['path'] ?? null) : $settings['image'];

        return ['libraryAlt' => $path ? \App\Models\Media::where('path', $path)->value('alt') : null];
    }
}
