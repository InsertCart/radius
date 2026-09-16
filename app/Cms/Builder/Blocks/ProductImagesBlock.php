<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;
use App\Models\Product;

/** The featured image plus the gallery, with clickable thumbnails. */
class ProductImagesBlock extends ProductBlock
{
    public static function type(): string
    {
        return 'product-images';
    }

    public static function name(): string
    {
        return 'Product images';
    }

    public static function icon(): string
    {
        return 'image';
    }

    public static function order(): int
    {
        return 10;
    }

    public static function keywords(): array
    {
        return ['gallery', 'photo', 'thumbnails'];
    }

    public static function controls(): array
    {
        return [
            Control::select('thumbs', 'Thumbnails', [
                'left' => 'Left of the image',
                'below' => 'Below the image',
                'none' => 'Hide',
            ])->default('left'),

            Control::select('ratio', 'Image shape', [
                '1-1' => 'Square',
                '4-3' => 'Landscape (4:3)',
                '3-4' => 'Portrait (3:4)',
                'auto' => 'Original',
            ])->default('1-1'),

            Control::color('stage_bg', 'Image background')
                ->tab(Control::TAB_STYLE)
                ->default('#ffffff')
                ->selector('{{WRAPPER}} .cb-pimages__stage', 'background-color'),

            Control::dimensions('radius', 'Corners')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-pimages__stage', 'border-radius'),

            Control::color('active_color', 'Selected thumbnail')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-pimages__thumb.is-active', 'border-color'),
        ];
    }

    protected function productData(Product $product, array $settings, array $context): array
    {
        // Keyed by URL so a featured image that also sits in the gallery is
        // not shown twice.
        $images = collect();

        if ($product->imageUrl()) {
            $images->put($product->imageUrl(), $product->name);
        }

        foreach ($product->gallery as $media) {
            $images->put($media->url, $media->alt ?: $product->name);
        }

        return ['images' => $images];
    }
}
