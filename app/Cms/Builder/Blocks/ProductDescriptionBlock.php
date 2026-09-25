<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;
use App\Models\Product;

class ProductDescriptionBlock extends ProductBlock
{
    public static function type(): string
    {
        return 'product-description';
    }

    public static function name(): string
    {
        return 'Description';
    }

    public static function icon(): string
    {
        return 'document';
    }

    public static function order(): int
    {
        return 60;
    }

    public static function controls(): array
    {
        return [
            Control::text('heading', 'Heading')->default('Description'),
            Control::select('display', 'Display', [
                'open' => 'Collapsible, open',
                'closed' => 'Collapsible, closed',
                'plain' => 'Always shown',
            ])->default('open'),

            Control::color('heading_color', 'Heading colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-pinfo__title', 'color'),
            Control::color('text_color', 'Text colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-pinfo__body', 'color'),
        ];
    }

    protected function productData(Product $product, array $settings, array $context): array
    {
        // The per-product builder may have designed this description. When it
        // has not, fall back to the stored HTML, then the short description.
        return ['body' => rich_content($product->description) ?: e((string) $product->short_description)];
    }
}
