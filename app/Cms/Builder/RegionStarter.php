<?php

namespace App\Cms\Builder;

use App\Models\Menu;

/**
 * Starting points for theme regions.
 *
 * Opening a region editor for the first time shows an empty canvas, which is
 * accurate - nothing has been built - but unhelpful, because the live site
 * clearly has a header. These starters give an admin something close to what
 * their theme already renders, so they are editing a real header rather than
 * rebuilding one from nothing.
 *
 * The trees are assembled from the site's actual data: the real menu slug, the
 * configured logo, the brand colour. Nothing is hard-coded that the CMS
 * already knows.
 */
class RegionStarter
{
    /** Regions that have a starter, with what it produces. */
    public function available(string $region): array
    {
        // A theme's own starters look like the theme, so they come first.
        return array_merge(app(\App\Cms\Themes\ThemeSections::class)->starters($region), $this->builtIn($region));
    }

    private function builtIn(string $region): array
    {
        return match ($region) {
            'header' => [
                ['key' => 'simple', 'label' => 'Logo and menu', 'hint' => 'A logo on the left, navigation on the right'],
                ['key' => 'centred', 'label' => 'Centred logo', 'hint' => 'Logo above, navigation centred beneath'],
                ['key' => 'shop', 'label' => 'Logo, menu and cart', 'hint' => 'Adds search and a cart icon'],
            ],
            'footer' => [
                ['key' => 'simple', 'label' => 'Simple footer', 'hint' => 'Site name, links and a copyright line'],
                ['key' => 'columns', 'label' => 'Three columns', 'hint' => 'About, links and a newsletter signup'],
            ],
            'cart' => [
                ['key' => 'simple', 'label' => 'Cart with a heading', 'hint' => 'Matches the theme, ready to restyle'],
            ],
            'checkout' => [
                ['key' => 'simple', 'label' => 'Checkout with a heading', 'hint' => 'Matches the theme, ready to restyle'],
            ],
            'blog_index' => [
                ['key' => 'simple', 'label' => 'Heading and post grid', 'hint' => 'A title above a grid of posts'],
            ],
            'shop_index' => [
                ['key' => 'simple', 'label' => 'Heading and product grid', 'hint' => 'A title above a grid of products'],
            ],
            'product' => [
                ['key' => 'classic', 'label' => 'Images beside details', 'hint' => 'Like your theme: gallery left, buy box right, reviews below'],
                ['key' => 'stacked', 'label' => 'Centred, single column', 'hint' => 'Image on top, everything else beneath it'],
            ],
            default => [],
        };
    }

    public function has(string $region): bool
    {
        return $this->available($region) !== [];
    }

    /** Build the tree for one starter. */
    public function build(string $region, string $key): array
    {
        if (str_starts_with($key, 'theme-')) {
            return app(\App\Cms\Themes\ThemeSections::class)->buildStarter($region, $key);
        }

        return match ("{$region}.{$key}") {
            'header.simple' => $this->headerSimple(),
            'header.centred' => $this->headerCentred(),
            'header.shop' => $this->headerShop(),
            'footer.simple' => $this->footerSimple(),
            'footer.columns' => $this->footerColumns(),
            'cart.simple' => $this->systemPage('Your cart', 'cart'),
            'checkout.simple' => $this->systemPage('Checkout', 'checkout'),
            'blog_index.simple' => $this->systemPage('Blog', 'posts'),
            'shop_index.simple' => $this->systemPage('Shop', 'products'),
            'product.classic' => $this->productClassic(),
            'product.stacked' => $this->productStacked(),
            default => [],
        };
    }

    // Headers --------------------------------------------------------------

    private function headerSimple(): array
    {
        return [$this->section('header', [
            $this->column(30, [$this->logo()]),
            $this->column(70, [$this->menu('flex-end')]),
        ], [
            'vertical_align' => 'center',
            'padding' => $this->spacing(18, 0),
        ])];
    }

    private function headerCentred(): array
    {
        return [$this->section('header', [
            $this->column(100, [
                $this->logo('center'),
                $this->menu('center'),
            ]),
        ], [
            'vertical_align' => 'center',
            'padding' => $this->spacing(24, 0),
        ])];
    }

    private function headerShop(): array
    {
        $columns = [
            $this->column(25, [$this->logo()]),
            $this->column(50, [$this->menu('center')]),
        ];

        $tools = [$this->widget('search', [
            'target' => modules()->enabled('shop') ? 'shop' : 'blog',
            'show_button' => false,
            'placeholder' => 'Search',
        ])];

        if (modules()->enabled('shop')) {
            $tools[] = $this->widget('cart-icon', ['show_count' => true]);
        }

        $columns[] = $this->column(25, $tools, ['horizontal_align' => 'flex-end']);

        return [$this->section('header', $columns, [
            'vertical_align' => 'center',
            'padding' => $this->spacing(16, 0),
        ])];
    }

    // Footers --------------------------------------------------------------

    private function footerSimple(): array
    {
        return [$this->section('footer', [
            $this->column(100, [
                $this->logo('center'),
                $this->menu('center'),
                $this->widget('text', [
                    'content' => '<p>&copy; '.date('Y').' '.e((string) setting('site_name', config('app.name'))).'. All rights reserved.</p>',
                    'align' => ['desktop' => 'center'],
                ]),
            ]),
        ], [
            'padding' => $this->spacing(48, 0),
            'background' => ['type' => 'classic', 'color' => '#f8fafc'],
        ])];
    }

    private function footerColumns(): array
    {
        $about = [
            $this->widget('site-logo', ['source' => 'text', 'link_home' => true]),
            $this->widget('text', [
                'content' => '<p>'.e((string) setting('site_tagline', 'A short line about your business.')).'</p>',
            ]),
            $this->widget('social-icons', ['use_settings' => true, 'shape' => 'circle']),
        ];

        $links = [
            $this->widget('heading', ['text' => 'Explore', 'tag' => 'h3']),
            $this->menu('flex-start', 'footer', 'vertical'),
        ];

        $third = modules()->enabled('newsletter')
            ? [$this->widget('newsletter', [
                'heading' => 'Newsletter',
                'description' => 'Occasional updates, no spam.',
                'inline' => false,
            ])]
            : [
                $this->widget('heading', ['text' => 'Get in touch', 'tag' => 'h3']),
                $this->widget('text', [
                    'content' => '<p>'.e((string) setting('site_email', 'hello@example.com')).'</p>',
                ]),
            ];

        return [$this->section('footer', [
            $this->column(40, $about),
            $this->column(30, $links),
            $this->column(30, $third),
        ], [
            'padding' => $this->spacing(56, 0),
            'background' => ['type' => 'classic', 'color' => '#f8fafc'],
        ])];
    }

    /**
     * A heading above the widget that carries the page's function.
     *
     * The functional widget is always included, so starting from one of
     * these can never produce a cart with no cart in it.
     */
    private function systemPage(string $heading, string $widget): array
    {
        return [$this->section('div', [
            $this->column(100, [
                $this->widget('heading', ['text' => $heading, 'tag' => 'h1']),
                $this->widget($widget),
            ]),
        ], [
            'padding' => $this->spacing(48, 0),
        ])];
    }

    // Product page -----------------------------------------------------------

    /** The buy box widgets, in the order the theme's own page shows them. */
    private function productBuyBox(): array
    {
        return [
            $this->widget('product-title'),
            $this->widget('product-rating'),
            $this->widget('product-price'),
            $this->widget('product-add-to-cart'),
            $this->widget('product-stock'),
            $this->widget('product-badges'),
            $this->widget('product-description'),
            $this->widget('product-details'),
        ];
    }

    /** Reviews and related products, each in its own full-width section. */
    private function productFooter(): array
    {
        return [
            $this->section('div', [$this->column(100, [$this->widget('product-reviews')])], [
                'padding' => $this->spacing(32, 0),
            ]),
            $this->section('div', [$this->column(100, [$this->widget('product-related')])], [
                'padding' => $this->spacing(32, 0),
            ]),
        ];
    }

    private function productClassic(): array
    {
        return array_merge([
            $this->section('div', [$this->column(100, [$this->widget('product-breadcrumbs')])], [
                'padding' => $this->spacing(20, 0),
            ]),
            $this->section('div', [
                $this->column(45, [$this->widget('product-images')]),
                $this->column(55, $this->productBuyBox()),
            ], [
                'gap' => ['size' => 40, 'unit' => 'px'],
                'padding' => $this->spacing(8, 0),
            ]),
        ], $this->productFooter());
    }

    private function productStacked(): array
    {
        return array_merge([
            $this->section('div', [
                $this->column(100, array_merge(
                    [$this->widget('product-breadcrumbs'), $this->widget('product-images', ['thumbs' => 'below'])],
                    $this->productBuyBox()
                )),
            ], [
                'padding' => $this->spacing(24, 0),
            ]),
        ], $this->productFooter());
    }

    // Node builders --------------------------------------------------------

    private function section(string $tag, array $columns, array $settings = []): array
    {
        return [
            'id' => $this->id(),
            'type' => 'section',
            'settings' => array_merge([
                'html_tag' => $tag,
                'content_width' => 'boxed',
                'gap' => ['size' => 24, 'unit' => 'px'],
                'stack_on' => 'tablet',
            ], $settings),
            'elements' => $columns,
        ];
    }

    private function column(float $width, array $widgets, array $settings = []): array
    {
        return [
            'id' => $this->id(),
            'type' => 'column',
            'settings' => array_merge(['width' => ['desktop' => $width]], $settings),
            'elements' => $widgets,
        ];
    }

    private function widget(string $type, array $settings = []): array
    {
        $class = app(BlockRegistry::class)->find($type);

        return [
            'id' => $this->id(),
            'type' => 'widget',
            'widgetType' => $type,
            'settings' => array_merge($class ? $class::defaults() : [], $settings),
            'elements' => [],
        ];
    }

    private function logo(string $align = 'flex-start'): array
    {
        return $this->widget('site-logo', [
            // 'setting' is always safe now: site_logo_url() falls back to the
            // logo the CMS ships with, so the starter never renders as an empty
            // box even before anybody uploads one.
            'source' => 'setting',
            'link_home' => true,
            'align' => ['desktop' => $align],
        ]);
    }

    private function menu(string $align = 'flex-start', string $slug = 'header', string $layout = 'horizontal'): array
    {
        // Use whatever menu actually exists rather than assuming a slug.
        $exists = Menu::where('slug', $slug)->exists();

        return $this->widget('menu', [
            'menu' => $exists ? $slug : (Menu::value('slug') ?? $slug),
            'layout' => $layout,
            'align' => ['desktop' => $align],
            'mobile_toggle' => $layout === 'horizontal',
        ]);
    }

    private function spacing(int $vertical, int $horizontal): array
    {
        return [
            'top' => $vertical,
            'right' => $horizontal,
            'bottom' => $vertical,
            'left' => $horizontal,
            'unit' => 'px',
        ];
    }

    /** Ids match the format the renderer validates. */
    private function id(): string
    {
        return substr(str_replace('.', '', uniqid('', true)), -10);
    }
}
