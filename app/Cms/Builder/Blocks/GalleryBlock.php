<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

class GalleryBlock extends Block
{
    public static function type(): string
    {
        return 'gallery';
    }

    public static function name(): string
    {
        return 'Gallery';
    }

    public static function icon(): string
    {
        return 'gallery';
    }

    public static function category(): string
    {
        return 'media';
    }

    public static function order(): int
    {
        return 3;
    }

    public static function keywords(): array
    {
        return ['images', 'photos', 'grid', 'lightbox'];
    }

    public static function controls(): array
    {
        return [
            Control::gallery('images', 'Images'),

            Control::slider('columns', 'Columns')
                ->min(1)->max(6)->units([''])
                ->default(['size' => 3, 'unit' => ''])
                ->responsive()
                ->selector('{{WRAPPER}} .cb-gallery', 'grid-template-columns', 'repeat({{VALUE}}, minmax(0, 1fr))'),

            Control::select('ratio', 'Image shape', [
                'auto' => 'Original proportions',
                '1-1' => 'Square',
                '4-3' => 'Landscape 4:3',
                '3-4' => 'Portrait 3:4',
                '16-9' => 'Wide 16:9',
            ])->default('1-1'),

            Control::toggle('lightbox', 'Open full size on click')->default(true),
            Control::toggle('show_captions', 'Show captions'),

            Control::slider('gap', 'Gap')
                ->min(0)->max(60)->units(['px'])
                ->default(['size' => 12, 'unit' => 'px'])
                ->responsive()
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-gallery', 'gap'),

            Control::dimensions('radius', 'Rounded corners')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-gallery img', 'border-radius'),
        ];
    }

    /** Library alt text for every image, fetched in one query. */
    public function data(array $settings, array $context = []): array
    {
        $paths = collect($settings['images'] ?? [])
            ->map(fn ($image) => is_array($image) ? ($image['path'] ?? null) : $image)
            ->filter()
            ->all();

        return [
            'libraryAlts' => $paths ? \App\Models\Media::whereIn('path', $paths)->pluck('alt', 'path')->all() : [],
        ];
    }
}
