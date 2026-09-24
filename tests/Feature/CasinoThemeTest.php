<?php

namespace Tests\Feature;

use App\Cms\Themes\ThemeManager;
use App\Cms\Themes\ThemeSections;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class CasinoThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        themes()->sync();
        app(ThemeManager::class)->activate('casino');
        Cache::flush();

        View::replaceNamespace('theme', [
            base_path('themes/casino/views'),
            base_path('themes/default/views'),
            resource_path('views/theme'),
        ]);
    }

    public function test_casino_theme_packages_cleanly_without_scan_errors(): void
    {
        $scratch = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'casino-test-' . uniqid();
        File::ensureDirectoryExists($scratch);

        try {
            $this->artisan('cms:theme-package', ['slug' => 'casino', '--output' => $scratch])
                ->assertExitCode(0);

            $this->assertFileExists($scratch . DIRECTORY_SEPARATOR . 'casino-1.0.0.zip');
        } finally {
            File::deleteDirectory($scratch);
        }
    }

    public function test_hero_section_renders_and_respects_blank_means_hidden(): void
    {
        // 1. Default render
        $html = view('theme::sections.hero', [
            'settings' => [
                'badge' => 'Exclusive Promo',
                'title' => 'Win Big Now',
                'cta_text' => 'Play Today',
                'cta_url' => '/play',
            ]
        ])->render();

        $this->assertStringContainsString('Exclusive Promo', $html);
        $this->assertStringContainsString('Win Big Now', $html);
        $this->assertStringContainsString('Play Today', $html);

        // 2. Blank means hidden (cta_text blank)
        $noCtaHtml = view('theme::sections.hero', [
            'settings' => [
                'badge' => '',
                'title' => 'No CTA Hero',
                'cta_text' => '',
            ]
        ])->render();

        $this->assertStringNotContainsString('wn-hero-promo__btn', $noCtaHtml);
        $this->assertStringNotContainsString('wn-hero-promo__tag', $noCtaHtml);
    }

    public function test_hero_and_showcase_dynamic_category_selection(): void
    {
        $category = Category::create([
            'type' => Category::TYPE_BLOG,
            'name' => 'Crash Games',
            'slug' => 'crash-games',
            'is_active' => true,
        ]);

        Post::create([
            'category_id' => $category->id,
            'title' => 'Crash Multiplier Strategy',
            'slug' => 'crash-strategy',
            'status' => 'published',
            'published_at' => now(),
            'content' => 'Sample content for testing',
        ]);

        $html = view('theme::sections.hero', [
            'settings' => [
                'category_id' => $category->id,
            ]
        ])->render();

        $this->assertStringContainsString('Crash Multiplier Strategy', $html);

        $showcaseHtml = view('theme::sections.showcase_rail', [
            'settings' => [
                'category_id' => $category->id,
                'title' => 'Category Posts',
            ]
        ])->render();

        $this->assertStringContainsString('Crash Multiplier Strategy', $showcaseHtml);
    }

    public function test_showcase_and_trending_respect_blank_means_hidden(): void
    {
        $showcase = view('theme::sections.showcase_rail', [
            'settings' => [
                'badge' => '',
                'title' => 'Originals Only',
                'view_all_text' => '',
            ]
        ])->render();

        $this->assertStringNotContainsString('wn-section-badge', $showcase);
        $this->assertStringNotContainsString('wn-link-view-all', $showcase);

        $trending = view('theme::sections.trending_grid', [
            'settings' => [
                'badge' => '',
                'view_all_text' => '',
            ]
        ])->render();

        $this->assertStringNotContainsString('wn-link-view-all', $trending);
    }

    public function test_vip_and_faq_sections_render(): void
    {
        $vipHtml = view('theme::sections.vip_club', [
            'settings' => [
                'tag' => 'VIP PASS',
                'title' => 'VIP High Rollers',
                'btn_text' => 'Join VIP',
            ]
        ])->render();

        $this->assertStringContainsString('VIP PASS', $vipHtml);
        $this->assertStringContainsString('VIP High Rollers', $vipHtml);
        $this->assertStringContainsString('wn-btn-vip', $vipHtml);

        $vipNoBtn = view('theme::sections.vip_club', [
            'settings' => [
                'tag' => '',
                'btn_text' => '',
            ]
        ])->render();

        $this->assertStringNotContainsString('wn-btn-vip', $vipNoBtn);
        $this->assertStringNotContainsString('wn-vip-card__tag', $vipNoBtn);

        $faqHtml = view('theme::sections.faq', [
            'settings' => [
                'title' => 'Player Questions',
            ]
        ])->render();

        $this->assertStringContainsString('Player Questions', $faqHtml);
        $this->assertStringContainsString('wn-accordion', $faqHtml);
    }

    public function test_homepage_view_renders_casino_layout(): void
    {
        // Unmark seeded homepage to test default home view
        Page::where('is_homepage', true)->update(['is_homepage' => false]);

        $response = $this->get('/');
        $response->assertStatus(200);

        $response->assertSee('wn-header', false);
        $response->assertSee('wn-sidebar-left', false);
        $response->assertSee('wn-sidebar-right', false);
        $response->assertSee('wn-main-center', false);
        $response->assertSee('CASINO.BET', false);
        $response->assertSee('1,680 Online', false);
    }

    public function test_contact_and_blog_views_render(): void
    {
        $contactHtml = view('theme::contact')->render();
        $this->assertStringContainsString('Contact & VIP Concierge', $contactHtml);
        $this->assertStringContainsString('Telegram VIP Desk', $contactHtml);

        if (modules()->enabled('blog')) {
            $blogHtml = view('theme::blog.index', [
                'posts' => Post::paginate(6),
                'categories' => Category::blog()->get(),
            ])->render();
            $this->assertStringContainsString('Platform Updates & Game Guides', $blogHtml);

            $post = Post::first();
            if ($post) {
                $postHtml = view('theme::blog.show', [
                    'post' => $post,
                    'related' => collect(),
                ])->render();
                $this->assertStringContainsString($post->title, $postHtml);
            }
        }
    }

    public function test_page_show_view_renders(): void
    {
        $page = Page::create([
            'title' => 'VIP Privileges & Terms',
            'slug' => 'vip-privileges',
            'content' => '<p>Exclusive cashback and tier rewards for players.</p>',
            'status' => 'published',
        ]);

        $html = view('theme::pages.show', ['page' => $page])->render();
        $this->assertStringContainsString('VIP Privileges', $html);
        $this->assertStringContainsString('Exclusive cashback and tier rewards', $html);
    }

    public function test_shop_views_render(): void
    {
        if (modules()->enabled('shop')) {
            $category = Category::create([
                'type' => Category::TYPE_SHOP,
                'name' => 'Hardware Wallets',
                'slug' => 'hardware-wallets',
                'is_active' => true,
            ]);

            $user = User::create([
                'name' => 'Gamer One',
                'email' => 'gamer@winza.test',
                'password' => Hash::make('secret123'),
            ]);
            $this->actingAs($user);

            $product = Product::create([
                'name' => 'Ledger Casino Edition',
                'slug' => 'ledger-casino-edition',
                'price' => 14900,
                'type' => 'simple',
                'status' => 'published',
                'stock' => 100,
                'manage_stock' => true,
            ]);
            $product->categories()->attach($category->id);

            $shopHtml = view('theme::shop.index', [
                'products' => Product::paginate(12),
                'categories' => Category::shop()->get(),
            ])->render();
            $this->assertStringContainsString('Ledger Casino Edition', $shopHtml);
            $this->assertStringContainsString('Hardware Wallets', $shopHtml);

            // Test GET /cart with empty cart
            $emptyCartRes = $this->get('/cart');
            $emptyCartRes->assertStatus(200);
            $emptyCartRes->assertSee('Your Bag is Currently Empty', false);

            // Add product to cart and verify GET /cart renders item, subtotal, and checkout CTA
            $addRes = $this->post(route('cart.add'), [
                'product_id' => $product->id,
                'quantity' => 2,
            ]);
            $addRes->assertSessionHasNoErrors();

            $cartWithItemRes = $this->get('/cart');
            $cartWithItemRes->assertStatus(200);
            $cartWithItemRes->assertSee('Ledger Casino Edition', false);
            $cartWithItemRes->assertSee('Subtotal', false);
            $cartWithItemRes->assertSee('Proceed to Checkout', false);
        }
    }
}
