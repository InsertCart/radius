<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RadiusPlugins\ThemePreview\PreviewSettings;
use Tests\TestCase;

/**
 * The Theme Preview plugin in plugins/theme-preview, loaded the way a site
 * loads it: synced from the folder, switched on, registered.
 */
class ThemePreviewPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->get('/');
        themes()->sync();

        // Plugins register while the app does, which in a test is before the
        // database exists - so this one is switched on and registered by hand.
        plugins()->sync();
        plugins()->enable('theme-preview');
        plugins()->registerEnabled($this->app);

        // On a real site plugin routes are registered before the pages
        // catch-all; here they arrive after it, so it is moved back to last.
        $router = app('router');
        $routes = collect($router->getRoutes()->getRoutes());
        [$catchAll, $rest] = $routes->partition(fn ($route) => $route->uri() === '{slug}');
        $collection = new \Illuminate\Routing\RouteCollection;
        $rest->concat($catchAll)->each(fn ($route) => $collection->add($route));
        $router->setRoutes($collection);

        Page::create(['title' => 'About the shop', 'slug' => 'about-demo', 'status' => 'published']);

        $this->assertSame('default', themes()->activatedSlug());
    }

    public function test_a_theme_that_is_not_public_sends_visitors_to_the_real_page(): void
    {
        $this->get('/theme-demo/zenith/blog?page=2')
            ->assertRedirect('http://localhost/blog?page=2');
    }

    public function test_a_public_demo_renders_the_whole_site_in_that_theme_without_activating_it(): void
    {
        $this->publish(['zenith'], ['zenith' => 'https://example.com/buy']);

        $response = $this->get('/theme-demo/zenith')->assertOk();

        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertSee('themes/zenith/css/theme.css', false);
        $response->assertSee('radius-theme-preview-template', false);
        $response->assertSee('https://example.com/buy', false);

        // Links stay inside the demo; assets stay where they are.
        $response->assertSee('href="http://localhost/theme-demo/zenith"', false);
        $response->assertDontSee('http://localhost/theme-demo/zenith/themes/', false);

        // A copy of the site, and it says so.
        $response->assertSee('<link rel="canonical" href="http://localhost/"', false);
        $response->assertDontSee('content="index, follow', false);

        // The real site is untouched.
        $this->assertSame('default', Theme::where('is_active', true)->value('slug'));
        $this->get('/')->assertOk()
            ->assertDontSee('themes/zenith/css/theme.css', false)
            ->assertDontSee('radius-theme-preview-template', false);
    }

    public function test_two_demos_can_be_browsed_side_by_side(): void
    {
        $this->publish(['zenith', 'storefront']);

        $this->get('/theme-demo/zenith/about-demo')->assertOk()->assertSee('About the shop')->assertSee('themes/zenith/', false);
        $this->get('/theme-demo/storefront/about-demo')->assertOk()->assertSee('themes/storefront/', false);
        $this->get('/theme-demo/zenith/about-demo')->assertOk()->assertDontSee('themes/storefront/css', false);

        // And the real page, straight after, is back on the active theme.
        $this->get('/about-demo')->assertOk()->assertDontSee('themes/zenith/', false)->assertDontSee('radius-theme-preview', false);
    }

    public function test_hard_coded_links_in_page_content_are_kept_inside_the_demo(): void
    {
        $this->publish(['zenith']);
        $context = app(\RadiusPlugins\ThemePreview\PreviewContext::class);
        $context->slug = 'zenith';
        $context->root = 'https://example.com/site';

        $links = app(\RadiusPlugins\ThemePreview\LinkRewriter::class);

        $this->assertSame('/site/theme-demo/zenith/about', $links->rewriteUrl('/site/about'));
        $this->assertSame('https://example.com/site/theme-demo/zenith', $links->rewriteUrl('https://example.com/site'));
        $this->assertSame('/site/storage/file.pdf', $links->rewriteUrl('/site/storage/file.pdf'));
        $this->assertSame('/site/themes/zenith/css/theme.css', $links->rewriteUrl('/site/themes/zenith/css/theme.css'));
        $this->assertSame('/site/admin/pages', $links->rewriteUrl('/site/admin/pages'));
        $this->assertSame('https://other.test/about', $links->rewriteUrl('https://other.test/about'));
        $this->assertSame('#top', $links->rewriteUrl('#top'));
        $this->assertSame('/site/theme-demo/storefront', $links->rewriteUrl('/site/theme-demo/storefront'));
    }

    public function test_an_administrator_can_preview_any_theme_and_activate_it_from_the_bar(): void
    {
        $admin = $this->admin();

        $page = $this->actingAs($admin)->withSession(['auth.two_factor_confirmed' => true])
            ->get('/theme-demo/shipnex/about-demo')
            ->assertOk()
            ->assertSee('themes/shipnex/', false)
            ->assertSee('Exit preview')
            ->assertSee('Activate');

        $page->assertSee(route('admin.theme-preview.activate', 'shipnex'), false);

        $this->actingAs($admin)->withSession(['auth.two_factor_confirmed' => true])
            ->post(route('admin.theme-preview.activate', 'shipnex'), ['return_to' => '/about-demo'])
            ->assertRedirect('http://localhost/about-demo');

        $this->assertSame('shipnex', Theme::where('is_active', true)->value('slug'));
    }

    public function test_the_activate_redirect_never_leaves_the_site(): void
    {
        $this->actingAs($this->admin())->withSession(['auth.two_factor_confirmed' => true])
            ->post(route('admin.theme-preview.activate', 'zenith'), ['return_to' => '//evil.test/x'])
            ->assertRedirect('http://localhost/');
    }

    public function test_the_admin_panel_is_never_served_under_the_demo_prefix(): void
    {
        $this->get('/theme-demo/zenith/admin/themes')->assertRedirect('http://localhost/admin/themes');
    }

    public function test_an_unknown_theme_is_left_to_ordinary_routing(): void
    {
        $this->publish(['zenith']);

        $this->get('/theme-demo/no-such-theme')->assertNotFound();
    }

    public function test_a_cached_demo_is_never_served_as_the_real_page(): void
    {
        settings()->set('cache_enabled', true);
        $this->publish(['default']);

        // A demo of the active theme and the real page share a path once the
        // prefix is off, so only the cache key can keep them apart.
        $this->get('/theme-demo/default')->assertOk()->assertHeader('X-Page-Cache', 'MISS');
        $this->get('/')->assertOk()->assertHeader('X-Page-Cache', 'MISS')
            ->assertDontSee('radius-theme-preview-template', false);
    }

    public function test_the_gallery_lists_public_demos_only(): void
    {
        $this->get('/theme-demo')->assertNotFound();

        $this->publish(['storefront']);

        $this->get('/theme-demo')->assertOk()
            ->assertSee('http://localhost/theme-demo/storefront', false)
            ->assertDontSee('http://localhost/theme-demo/zenith', false);
    }

    public function test_the_settings_screen_refuses_a_prefix_that_would_hide_real_pages(): void
    {
        $this->actingAs($this->admin())->withSession(['auth.two_factor_confirmed' => true])
            ->put(route('admin.theme-preview.settings.update'), [
                'prefix' => 'shop', 'gallery_title' => 'Demos', 'cta_label' => 'Buy',
            ])
            ->assertSessionHasErrors('prefix');
    }

    public function test_the_admin_screens_carry_the_plugin(): void
    {
        $this->publish(['zenith']);
        $admin = $this->admin();

        $this->actingAs($admin)->withSession(['auth.two_factor_confirmed' => true])
            ->get(route('admin.themes.index'))
            ->assertOk()
            ->assertSee('http://localhost/theme-demo/zenith', false)
            ->assertSee('Public demo')
            ->assertSee('Live demos')
            ->assertSee(route('admin.theme-preview.settings'), false);

        $this->actingAs($admin)->withSession(['auth.two_factor_confirmed' => true])
            ->get(route('admin.theme-preview.settings'))
            ->assertOk()
            ->assertSee('Copy demo link')
            ->assertSee('http://localhost/theme-demo', false);

        $this->actingAs($admin)->withSession(['auth.two_factor_confirmed' => true])
            ->get(route('admin.plugins.index'))
            ->assertOk()
            ->assertSee('Theme Preview')
            ->assertSee(route('admin.theme-preview.settings'), false);
    }

    private function publish(array $themes, array $buyLinks = []): void
    {
        app(PreviewSettings::class)->save([
            'public_enabled' => true,
            'public_themes' => $themes,
            'buy_links' => $buyLinks,
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin'.Str::random(4).'@example.test', 'password' => Hash::make('x'),
            'role' => User::ROLE_ADMIN, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }
}
