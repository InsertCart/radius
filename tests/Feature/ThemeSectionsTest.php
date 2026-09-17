<?php

namespace Tests\Feature;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Builder\RegionManager;
use App\Cms\Builder\RegionStarter;
use App\Models\Layout;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Theme sections: a theme's own markup, offered to the builder as widgets, and
 * the starters that make a region open looking like the theme.
 */
class ThemeSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');

        themes()->sync();
        Theme::query()->update(['is_active' => false]);
        Theme::where('slug', 'storefront')->update(['is_active' => true]);
        Cache::flush();
        app()->forgetInstance(\App\Cms\Themes\ThemeManager::class);
        app()->forgetInstance(\App\Cms\Themes\ThemeSections::class);
        app()->forgetInstance(RegionManager::class);
        themes()->registerViewNamespace();
    }

    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'owner@x.test'], [
            'name' => 'Owner', 'password' => Hash::make('password-123'),
            'role' => User::ROLE_ADMIN, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    public function test_the_theme_sections_appear_as_their_own_widgets(): void
    {
        $tiles = collect(app(BlockRegistry::class)->panel('home'))->firstWhere('key', 'theme')['widgets'] ?? [];
        $names = collect($tiles)->pluck('name');

        $this->assertContains('Product rail', $names);
        $this->assertContains('Hero banner', $names);
        $this->assertSame('rail', collect($tiles)->firstWhere('name', 'Product rail')['preset']['section']);
    }

    public function test_an_empty_region_opens_as_the_theme_starter_without_going_live(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.builder.region', 'header'))
            ->assertOk();

        $layout = Layout::forRegion('header', 'storefront');

        $this->assertSame('theme-section', $layout->editableTree()[0]['elements'][0]['elements'][0]['widgetType']);
        $this->assertNull($layout->published_at);
    }

    public function test_a_published_homepage_renders_the_theme_sections_with_their_settings(): void
    {
        $tree = app(RegionStarter::class)->build('home', 'theme-storefront');
        $this->assertNotSame([], $tree);

        // Change the hero heading, as an admin would in the settings panel.
        $tree[0]['elements'][0]['elements'][0]['settings']['hero__title'] = 'Built with the builder';

        Layout::forRegion('home', 'storefront')->publish($tree, $this->admin()->id);
        app(RegionManager::class)->flush();

        $html = app(RegionManager::class)->render('home');

        $this->assertStringContainsString('Built with the builder', $html);
        $this->assertStringContainsString('class="sf-hero"', $html);
        $this->assertStringContainsString('cb-section--edge', $html);
    }

    public function test_a_section_from_a_theme_that_is_gone_renders_nothing_for_visitors(): void
    {
        $html = app(\App\Cms\Builder\LayoutRenderer::class)->render([[
            'id' => 'aaaa1111', 'type' => 'section', 'settings' => [], 'elements' => [[
                'id' => 'bbbb2222', 'type' => 'column', 'settings' => [], 'elements' => [[
                    'id' => 'cccc3333', 'type' => 'widget', 'widgetType' => 'theme-section',
                    'settings' => ['section' => 'no-such-section'], 'elements' => [],
                ]],
            ]],
        ]]);

        $this->assertStringNotContainsString('no longer active', $html);
    }
}
