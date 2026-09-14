<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * The minimum content a fresh install needs to look like a working site
 * rather than an empty shell: a couple of pages, a starter category and the
 * two menus themes expect to find.
 *
 * Run by the installer. Safe to run again - everything is keyed on its slug.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->pages();
        $this->categories();
        $this->menus();
    }

    private function pages(): void
    {
        $pages = [
            [
                'title' => 'About us',
                'slug' => 'about',
                'content' => '<p>Tell your visitors who you are and what you do. You can edit this page from the admin panel under Content &rarr; Pages.</p>',
                'show_in_menu' => true,
                'sort_order' => 1,
                'schema_type' => 'AboutPage',
            ],
            [
                'title' => 'Privacy policy',
                'slug' => 'privacy',
                'content' => '<p>Describe what data you collect, why you collect it, and how visitors can ask for it to be removed.</p>'
                    .'<p>This placeholder is not legal advice. Replace it before going live.</p>',
                'show_in_menu' => false,
                'sort_order' => 2,
            ],
            [
                'title' => 'Terms and conditions',
                'slug' => 'terms',
                'content' => '<p>Set out the terms visitors and customers agree to when they use your site or place an order.</p>'
                    .'<p>This placeholder is not legal advice. Replace it before going live.</p>',
                'show_in_menu' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($pages as $attributes) {
            Page::firstOrCreate(
                ['slug' => $attributes['slug']],
                array_merge($attributes, ['status' => 'published'])
            );
        }
    }

    private function categories(): void
    {
        Category::firstOrCreate(
            ['type' => Category::TYPE_BLOG, 'slug' => 'news'],
            [
                'name' => 'News',
                'description' => 'Announcements and updates.',
                'is_active' => true,
                'show_in_menu' => true,
            ]
        );

        if (modules()->enabled('shop')) {
            Category::firstOrCreate(
                ['type' => Category::TYPE_SHOP, 'slug' => 'all-products'],
                [
                    'name' => 'All products',
                    'description' => 'Everything in the shop.',
                    'is_active' => true,
                    'show_in_menu' => true,
                ]
            );
        }
    }

    /**
     * Themes look up menus by slug, so 'header' and 'footer' are the two names
     * a template can safely assume exist.
     */
    private function menus(): void
    {
        $header = Menu::firstOrCreate(
            ['slug' => 'header'],
            ['name' => 'Main navigation', 'description' => 'The links across the top of every page.']
        );

        $footer = Menu::firstOrCreate(
            ['slug' => 'footer'],
            ['name' => 'Footer links', 'description' => 'Secondary links in the footer.']
        );

        if ($header->items()->doesntExist()) {
            $items = [
                ['label' => 'Home', 'type' => 'custom', 'url' => '/', 'sort_order' => 0],
                ['label' => 'Blog', 'type' => 'route', 'url' => 'blog.index', 'sort_order' => 1, 'requires_module' => 'blog'],
                ['label' => 'Shop', 'type' => 'route', 'url' => 'shop.index', 'sort_order' => 2, 'requires_module' => 'shop'],
                ['label' => 'About', 'type' => 'page', 'reference_id' => Page::where('slug', 'about')->value('id'), 'sort_order' => 3],
                ['label' => 'Contact', 'type' => 'route', 'url' => 'contact', 'sort_order' => 4, 'requires_module' => 'contact'],
            ];

            foreach ($items as $item) {
                $header->items()->create($item + ['target' => '_self', 'is_active' => true]);
            }
        }

        if ($footer->items()->doesntExist()) {
            $items = [
                ['label' => 'Privacy policy', 'type' => 'page', 'reference_id' => Page::where('slug', 'privacy')->value('id'), 'sort_order' => 0],
                ['label' => 'Terms', 'type' => 'page', 'reference_id' => Page::where('slug', 'terms')->value('id'), 'sort_order' => 1],
                ['label' => 'Contact', 'type' => 'route', 'url' => 'contact', 'sort_order' => 2, 'requires_module' => 'contact'],
            ];

            foreach ($items as $item) {
                $footer->items()->create($item + ['target' => '_self', 'is_active' => true]);
            }
        }
    }
}
