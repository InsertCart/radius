<?php

namespace Tests\Feature;

use App\Cms\Builder\RegionManager;
use App\Models\Layout;
use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The header and footer regions render after the head has been printed, so
 * their stylesheet has to be added to the page once it has finished rendering.
 */
class BuilderRegionStylesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');

        themes()->sync();
        Theme::query()->update(['is_active' => false]);
        Theme::where('slug', 'default')->update(['is_active' => true]);
        Cache::flush();
        app()->forgetInstance(\App\Cms\Themes\ThemeManager::class);
        app()->forgetInstance(RegionManager::class);
        themes()->registerViewNamespace();
    }

    public function test_a_built_footer_keeps_its_background_and_padding_on_the_live_site(): void
    {
        Layout::forRegion('footer', 'default')->publish([[
            'type' => 'section', 'id' => 'foot1',
            'settings' => [
                'background' => ['type' => 'classic', 'color' => '#f8fafc'],
                'padding' => ['top' => 56, 'right' => 0, 'bottom' => 56, 'left' => 0, 'unit' => 'px'],
            ],
            'elements' => [[
                'type' => 'column', 'id' => 'col1', 'settings' => [],
                'elements' => [[
                    'type' => 'widget', 'id' => 'w1', 'widgetType' => 'heading',
                    'settings' => ['text' => 'Footer heading'],
                ]],
            ]],
        ]], null);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Footer heading', $html);
        $this->assertMatchesRegularExpression('/<head>.*background-color:#f8fafc.*<\/head>/s', $html);
        $this->assertStringNotContainsString('<!--cb-builder-styles-->', $html);
    }
}
