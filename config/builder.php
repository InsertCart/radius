<?php

use App\Cms\Builder\Blocks;

/*
|--------------------------------------------------------------------------
| Visual builder
|--------------------------------------------------------------------------
| The widgets the editor can place, plus the editor's own limits and
| defaults. Adding a widget means writing one Block class, one Blade view,
| and adding the class to the list below.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Registered blocks
    |--------------------------------------------------------------------------
    | Order here does not matter; the widget panel sorts by category and each
    | block's own order() value.
    */

    'blocks' => [
        // Basic
        Blocks\HeadingBlock::class,
        Blocks\TextBlock::class,
        Blocks\ButtonBlock::class,
        Blocks\IconBlock::class,

        // Media
        Blocks\ImageBlock::class,
        Blocks\VideoBlock::class,
        Blocks\GalleryBlock::class,
        Blocks\MapBlock::class,

        // Layout
        Blocks\SpacerBlock::class,
        Blocks\DividerBlock::class,

        // Content
        Blocks\IconBoxBlock::class,
        Blocks\PostsBlock::class,
        Blocks\AccordionBlock::class,
        Blocks\TabsBlock::class,
        Blocks\TestimonialBlock::class,
        Blocks\CounterBlock::class,
        Blocks\PricingBlock::class,
        Blocks\ContactFormBlock::class,
        Blocks\NewsletterBlock::class,
        Blocks\HtmlBlock::class,

        // Shop
        Blocks\ProductsBlock::class,
        Blocks\CartBlock::class,
        Blocks\CheckoutBlock::class,

        // Product page template
        Blocks\ProductBreadcrumbsBlock::class,
        Blocks\ProductImagesBlock::class,
        Blocks\ProductTitleBlock::class,
        Blocks\ProductRatingBlock::class,
        Blocks\ProductPriceBlock::class,
        Blocks\ProductShortDescriptionBlock::class,
        Blocks\ProductAddToCartBlock::class,
        Blocks\ProductStockBlock::class,
        Blocks\ProductBadgesBlock::class,
        Blocks\ProductDescriptionBlock::class,
        Blocks\ProductDetailsBlock::class,
        Blocks\ProductReviewsBlock::class,
        Blocks\ProductRelatedBlock::class,

        // Site parts
        Blocks\SiteLogoBlock::class,
        Blocks\MenuBlock::class,
        Blocks\CartIconBlock::class,
        Blocks\SearchBlock::class,
        Blocks\SocialIconsBlock::class,
        Blocks\PageTitleBlock::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Where the builder may be used
    |--------------------------------------------------------------------------
    | Maps the short key used in builder URLs to its model. Removing an entry
    | takes the "Edit with builder" button off that content type.
    */

    'editable' => [
        'page' => App\Models\Page::class,
        'post' => App\Models\Post::class,
        'product' => App\Models\Product::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | System page areas
    |--------------------------------------------------------------------------
    | Pages the CMS renders from a controller rather than from a content
    | record: the cart, the checkout, the blog and shop index pages.
    |
    | These are buildable the same way a theme region is, and fall back to the
    | theme's own view while nothing has been built.
    |
    | The catch is that these pages *do* something. Replacing a checkout with
    | a picture and a heading would leave customers unable to pay. So each one
    | names the widget that carries its actual function, the editor warns when
    | a layout is missing it, and every starter includes it.
    */

    'system_areas' => [
        'cart' => [
            'label' => 'Cart page',
            'module' => 'shop',
            'route' => 'cart.index',
            'requires_widget' => 'cart',
            'description' => 'What shoppers see when they open their basket.',
        ],
        'checkout' => [
            'label' => 'Checkout page',
            'module' => 'shop',
            'route' => 'checkout.index',
            'requires_widget' => 'checkout',
            'description' => 'The address, payment and place-order step.',
        ],
        'product' => [
            'label' => 'Product page',
            'module' => 'shop',
            'route' => 'shop.index',
            'requires_widget' => 'product-add-to-cart',
            'description' => 'One design shared by every product: images, price, buy buttons and more.',
        ],
        'blog_index' => [
            'label' => 'Blog index',
            'module' => 'blog',
            'route' => 'blog.index',
            'requires_widget' => null,
            'description' => 'The list of posts at /blog.',
        ],
        'shop_index' => [
            'label' => 'Shop index',
            'module' => 'shop',
            'route' => 'shop.index',
            'requires_widget' => null,
            'description' => 'The product listing at /shop.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Breakpoints
    |--------------------------------------------------------------------------
    | The device widths the editor previews at, and that responsive controls
    | compile media queries against. Keep these in step with StyleCompiler.
    */

    'breakpoints' => [
        'desktop' => ['label' => 'Desktop', 'width' => null, 'icon' => 'desktop'],
        'tablet' => ['label' => 'Tablet', 'width' => 1024, 'icon' => 'tablet'],
        'mobile' => ['label' => 'Mobile', 'width' => 767, 'icon' => 'mobile'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Editor behaviour
    |--------------------------------------------------------------------------
    */

    'editor' => [
        // Autosave the draft this many milliseconds after the last change.
        'autosave_delay' => 4000,

        // Steps kept in the in-editor undo stack.
        'history_limit' => 50,

        // Refuse absurdly large trees, which are usually a bug rather than a
        // real page. Also caps what a single request has to parse.
        'max_nodes' => 2000,
        'max_depth' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default design tokens
    |--------------------------------------------------------------------------
    | Seeded on install. An admin edits these under Appearance, and every
    | element referencing one updates with it.
    */

    'tokens' => [
        'color' => [
            'primary' => ['label' => 'Primary', 'value' => '#2563eb'],
            'secondary' => ['label' => 'Secondary', 'value' => '#0f172a'],
            'accent' => ['label' => 'Accent', 'value' => '#f59e0b'],
            'text' => ['label' => 'Body text', 'value' => '#334155'],
            'heading' => ['label' => 'Headings', 'value' => '#0f172a'],
            'muted' => ['label' => 'Muted text', 'value' => '#64748b'],
            'surface' => ['label' => 'Surface', 'value' => '#ffffff'],
            'border' => ['label' => 'Borders', 'value' => '#e2e8f0'],
        ],
        'font' => [
            'body' => ['label' => 'Body font', 'value' => 'inherit'],
            'heading' => ['label' => 'Heading font', 'value' => 'inherit'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fonts offered in typography controls
    |--------------------------------------------------------------------------
    | System fonts need no download. Anything else is fetched from Google
    | Fonts, as one request for every family a page actually uses.
    */

    'fonts' => [
        'system' => [
            'inherit' => 'Theme default',
            'system-ui, sans-serif' => 'System sans',
            'Georgia, serif' => 'Georgia',
            'ui-monospace, monospace' => 'Monospace',
        ],
        'google' => [
            'Inter', 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Poppins',
            'Raleway', 'Nunito', 'Playfair Display', 'Merriweather',
            'Source Sans 3', 'Work Sans', 'DM Sans', 'Manrope', 'Outfit',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Icons
    |--------------------------------------------------------------------------
    | The built-in icon set, drawn inline as SVG so no icon font is loaded.
    | Grouped for the icon picker.
    */

    'icons' => [
        'general' => ['star', 'heart', 'check', 'close', 'plus', 'minus', 'info', 'alert',
            'question', 'lightbulb', 'sparkles', 'fire', 'bolt', 'gift', 'tag', 'award'],
        'arrows' => ['arrow-right', 'arrow-left', 'arrow-up', 'arrow-down',
            'chevron-right', 'chevron-left', 'chevron-up', 'chevron-down', 'external'],
        'business' => ['briefcase', 'chart', 'target', 'rocket', 'shield', 'lock',
            'clock', 'calendar', 'document', 'folder', 'clipboard', 'wallet'],
        'contact' => ['mail', 'phone', 'map-pin', 'globe', 'chat', 'send', 'user', 'users'],
        'shop' => ['cart', 'bag', 'credit-card', 'truck', 'package', 'refresh', 'percent'],
        'media' => ['image', 'video', 'camera', 'music', 'play', 'headphones', 'mic'],
        'social' => ['facebook', 'instagram', 'twitter', 'linkedin', 'youtube',
            'whatsapp', 'tiktok', 'pinterest', 'github'],
    ],
];
