<?php

namespace Tests\Feature;

use App\Cms\Builder\LayoutRenderer;
use App\Models\Layout;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression cover for the security fixes: the things that must STILL work.
 *
 * Every fix narrows something, and a narrowing that goes too far is its own
 * kind of outage. These assert the ordinary day of an admin and of an editor.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Owner', 'email' => 'owner@x.test', 'password' => Hash::make('password-123'),
            'role' => User::ROLE_ADMIN, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function editor(): User
    {
        return User::create([
            'name' => 'Ed', 'email' => 'ed@x.test', 'password' => Hash::make('password-123'),
            'role' => User::ROLE_EDITOR, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function layout(string $slug): Layout
    {
        $page = Page::create(['title' => 'T', 'slug' => $slug, 'status' => 'published']);

        return Layout::create([
            'layoutable_type' => Page::class, 'layoutable_id' => $page->id,
            'theme_slug' => 'default', 'is_enabled' => true,
        ]);
    }

    private function tree(array $widgets): array
    {
        return [[
            'type' => 'section', 'id' => 's1', 'settings' => [], 'elements' => [[
                'type' => 'column', 'id' => 'c1', 'settings' => [], 'elements' => $widgets,
            ]],
        ]];
    }

    /** The admin must not have been locked out of anything by the new gates. */
    public function test_admin_can_still_reach_every_admin_screen(): void
    {
        $admin = $this->admin();

        foreach ([
            '/admin', '/admin/users', '/admin/users/create', '/admin/themes',
            '/admin/modules', '/admin/settings', '/admin/system', '/admin/media',
            '/admin/pages', '/admin/posts', '/admin/categories', '/admin/menus',
            '/admin/builder', '/admin/updates',
        ] as $path) {
            $status = $this->actingAs($admin)->get($path)->status();

            $this->assertContains($status, [200, 302],
                "Admin was blocked from {$path} (HTTP {$status}).");
        }
    }

    /** The editor must still be able to do the job the role exists for. */
    public function test_editor_can_still_do_content_work(): void
    {
        $editor = $this->editor();

        foreach ([
            '/admin', '/admin/pages', '/admin/posts', '/admin/categories',
            '/admin/menus', '/admin/media', '/admin/builder',
        ] as $path) {
            $status = $this->actingAs($editor)->get($path)->status();

            $this->assertContains($status, [200, 302],
                "Editor was blocked from {$path} (HTTP {$status}), which is content work.");
        }
    }

    public function test_editor_sidebar_offers_no_link_that_would_403(): void
    {
        $html = $this->actingAs($this->editor())->get('/admin')->getContent();

        foreach (['admin/users', 'admin/themes', 'admin/modules', 'admin/payments', 'admin/settings'] as $path) {
            $this->assertStringNotContainsString(
                'href="'.url($path).'"',
                $html,
                "The editor sidebar links to /{$path}, which now answers 403."
            );
        }
    }

    /** Ordinary rich text must survive sanitisation intact. */
    public function test_builder_keeps_legitimate_rich_content(): void
    {
        $layout = $this->layout('smoke-1');

        $content = '<p>Hello <strong>world</strong>, see <a href="https://example.com">this</a>.</p>'
            .'<ul><li>One</li><li>Two</li></ul><img src="/storage/a.png" alt="A">';

        $this->actingAs($this->editor())
            ->postJson('/admin/builder/'.$layout->id.'/publish', [
                'tree' => $this->tree([[
                    'type' => 'widget', 'id' => 'w1', 'widgetType' => 'text',
                    'settings' => ['content' => $content],
                ]]),
            ])->assertOk();

        $out = app(LayoutRenderer::class)->render($layout->fresh()->data ?? []);

        foreach (['<strong>world</strong>', 'href="https://example.com"', '<li>One</li>', '<img'] as $keep) {
            $this->assertStringContainsString($keep, $out,
                "Sanitising the builder destroyed legitimate content: {$keep}");
        }
    }

    /** The HTML widget is a real feature for the owner; it must still work. */
    public function test_admin_can_still_place_a_raw_html_block(): void
    {
        $layout = $this->layout('smoke-2');
        $embed = '<script async src="https://example.com/embed.js"></script>';

        $this->actingAs($this->admin())
            ->postJson('/admin/builder/'.$layout->id.'/publish', [
                'tree' => $this->tree([[
                    'type' => 'widget', 'id' => 'w1', 'widgetType' => 'html',
                    'settings' => ['html' => $embed],
                ]]),
            ])->assertOk();

        $this->assertStringContainsString(
            'embed.js',
            app(LayoutRenderer::class)->render($layout->fresh()->data ?? []),
            'An administrator could no longer place an embed code, which is the widget\'s entire purpose.'
        );
    }

    /** An editor editing a page must not destroy an admin's embed. */
    public function test_editor_edit_preserves_an_admins_existing_html_block(): void
    {
        $layout = $this->layout('smoke-3');
        $embed = '<script async src="https://example.com/embed.js"></script>';

        $tree = fn (string $heading) => $this->tree([
            ['type' => 'widget', 'id' => 'w1', 'widgetType' => 'html', 'settings' => ['html' => $embed]],
            ['type' => 'widget', 'id' => 'w2', 'widgetType' => 'heading', 'settings' => ['text' => $heading]],
        ]);

        $this->actingAs($this->admin())
            ->postJson('/admin/builder/'.$layout->id.'/publish', ['tree' => $tree('Before')])
            ->assertOk();

        // The editor changes the heading, submitting the page as their browser
        // has it - embed included.
        $this->actingAs($this->editor())
            ->postJson('/admin/builder/'.$layout->id.'/publish', ['tree' => $tree('After')])
            ->assertOk();

        $out = app(LayoutRenderer::class)->render($layout->fresh()->data ?? []);

        $this->assertStringContainsString('embed.js', $out,
            'An editor saving the page destroyed the administrator\'s embed code.');
        $this->assertStringContainsString('After', $out,
            'The editor\'s own change was lost.');
    }

    public function test_front_end_still_renders(): void
    {
        Page::create(['title' => 'About', 'slug' => 'about-us', 'status' => 'published', 'content' => '<p>Hi</p>']);
        Post::create(['title' => 'News', 'slug' => 'news-1', 'status' => 'published', 'content' => '<p>Hi</p>']);

        $this->get('/')->assertOk();
        $this->get('/about-us')->assertOk();
    }

    public function test_login_and_generated_urls_still_work(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', ['email' => 'owner@x.test', 'password' => 'password-123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);

        $this->assertStringStartsWith(
            rtrim(config('app.url'), '/'),
            route('admin.dashboard'),
            'Generated URLs no longer match APP_URL.'
        );
    }
}
