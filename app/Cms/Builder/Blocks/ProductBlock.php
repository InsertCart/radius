<?php

namespace App\Cms\Builder\Blocks;

use App\Models\Product;

/**
 * Base for the widgets that make up the product page template.
 *
 * One template serves every product, so these widgets hold no product of
 * their own: they read whichever product the page is showing from the render
 * context. In the editor that is a sample product, so the design is previewed
 * with real prices, images and stock rather than grey boxes.
 */
abstract class ProductBlock extends Block
{
    public static function category(): string
    {
        return 'product';
    }

    public static function requiresModule(): ?string
    {
        return 'shop';
    }

    /** Only offered while designing the product page template. */
    public static function areas(): ?array
    {
        return ['product'];
    }

    /** Extra view data for the product being shown. */
    protected function productData(Product $product, array $settings, array $context): array
    {
        return [];
    }

    public function data(array $settings, array $context = []): array
    {
        $product = $context['model'] ?? null;

        if (! $product instanceof Product) {
            return ['product' => null];
        }

        return array_merge(['product' => $product], $this->productData($product, $settings, $context));
    }

    public function render(array $settings, array $context = []): string
    {
        if (! ($context['model'] ?? null) instanceof Product) {
            return $this->placeholder(static::name().' shows the current product on product pages.', $context);
        }

        return parent::render($settings, $context);
    }
}
