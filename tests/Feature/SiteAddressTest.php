<?php

namespace Tests\Feature;

use App\Cms\Support\SiteUrlRewriter;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Change site address: a database copied from staging to live, and every link
 * in it moved across with one button.
 */
class SiteAddressTest extends TestCase
{
    use RefreshDatabase;

    private const STAGING = 'https://staging.baztro.com';

    private const LIVE = 'https://www.baztro.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');

        modules()->sync();
        modules()->flush();
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => 'Owner', 'email' => $role.'@x.test', 'password' => Hash::make('password-123'),
            'role' => $role, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function seedStagingContent(): Post
    {
        $post = Post::create([
            'title' => 'Launch', 'slug' => 'launch', 'status' => 'published',
            'content' => '<p><a href="'.self::STAGING.'/pricing">Pricing</a> '
                .'<img src="http://staging.baztro.com/storage/2026/09/a.jpg"> '
                .'<a href="https://staging.baztro.com.au/">not us</a></p>',
            'canonical_url' => self::STAGING.'/blog/launch',
        ]);

        $post->layout()->create([
            'data' => ['blocks' => [['type' => 'button', 'href' => self::STAGING.'/contact']]],
            'is_enabled' => true,
        ]);

        Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'content' => '<p>Nothing to change.</p>']);

        $menu = Menu::create(['name' => 'Header', 'slug' => 'header']);
        $menu->items()->create(['label' => 'Shop', 'type' => 'custom', 'url' => self::STAGING.'/shop', 'sort_order' => 0]);

        settings()->set('site_logo_link', self::STAGING.'/');

        return $post;
    }

    public function test_the_preview_counts_without_changing_anything(): void
    {
        $post = $this->seedStagingContent();
        $before = $post->fresh()->rawContent();

        $response = $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post('/admin/system/site-address/preview', ['from' => self::STAGING, 'to' => self::LIVE]);

        $response->assertOk();
        $response->assertSee('Posts');
        $response->assertSee('Builder layouts');
        $response->assertSee('Menus');
        $response->assertViewHas('preview', fn ($preview) => ! isset($preview['Pages']) && $preview['Posts']['links'] === 3);

        $this->assertSame($before, $post->fresh()->rawContent());
    }

    public function test_applying_moves_every_link_to_the_new_address(): void
    {
        $post = $this->seedStagingContent();

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post('/admin/system/site-address', ['from' => self::STAGING, 'to' => self::LIVE, 'backup' => '0'])
            ->assertRedirect('/admin/system/site-address')
            ->assertSessionHas('status');

        $post = $post->fresh();

        $this->assertStringContainsString('href="'.self::LIVE.'/pricing"', $post->rawContent());
        // An http:// link to the old site comes over too, on the new scheme.
        $this->assertStringContainsString('src="'.self::LIVE.'/storage/2026/09/a.jpg"', $post->rawContent());
        $this->assertStringContainsString('https://staging.baztro.com.au/', $post->rawContent());
        $this->assertSame(self::LIVE.'/blog/launch', $post->canonical_url);
        $this->assertSame(self::LIVE.'/contact', $post->layout->fresh()->data['blocks'][0]['href']);
        $this->assertSame(self::LIVE.'/shop', Menu::first()->items()->first()->url);
        $this->assertSame(self::LIVE.'/', settings()->get('site_logo_link'));

        // The layout JSON was rewritten, not left with the old escaped form.
        $this->assertStringNotContainsString('staging.baztro.com', (string) DB::table('layouts')->value('data'));
    }

    public function test_addresses_must_be_full_urls_and_different(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->post('/admin/system/site-address/preview', ['from' => 'staging.baztro.com', 'to' => self::LIVE])
            ->assertSessionHasErrors('from');

        $this->actingAs($admin)
            ->post('/admin/system/site-address/preview', ['from' => self::LIVE.'/', 'to' => self::LIVE])
            ->assertSessionHasErrors('to');
    }

    public function test_an_editor_cannot_change_the_site_address(): void
    {
        $editor = $this->user(User::ROLE_EDITOR);

        $this->actingAs($editor)->get('/admin/system/site-address')->assertForbidden();
        $this->actingAs($editor)
            ->post('/admin/system/site-address', ['from' => self::STAGING, 'to' => self::LIVE])
            ->assertForbidden();
    }

    public function test_the_rewriter_respects_address_boundaries(): void
    {
        $rewriter = new SiteUrlRewriter('https://baztro.com', 'https://www.baztro.com');

        $this->assertSame('https://www.baztro.com/a', $rewriter->replace('http://baztro.com/a'));
        $this->assertSame('src="https://www.baztro.com/x.jpg"', $rewriter->replace('src="//baztro.com/x.jpg"'));
        $this->assertSame('Visit https://www.baztro.com.', $rewriter->replace('Visit https://baztro.com.'));
        $this->assertSame('https://baztro.com.au/', $rewriter->replace('https://baztro.com.au/'));
        $this->assertSame('https://baztro.community', $rewriter->replace('https://baztro.community'));
        $this->assertSame('https://baztro.com:8080/', $rewriter->replace('https://baztro.com:8080/'));
        $this->assertSame('https://other.com/?u=https://www.baztro.com', $rewriter->replace('https://other.com/?u=https://baztro.com'));

        $sub = new SiteUrlRewriter('http://localhost/shop', 'https://shop.example.com');

        $this->assertSame('https://shop.example.com/cart', $sub->replace('http://localhost/shop/cart'));
        $this->assertSame('http://localhost/shopping', $sub->replace('http://localhost/shopping'));

        $count = 0;
        $json = $rewriter->replaceStored('{"href":"https:\/\/baztro.com\/a"}', $count);

        $this->assertSame('{"href":"https://www.baztro.com/a"}', $json);
        $this->assertSame(1, $count);
    }

    public function test_an_https_move_does_not_count_addresses_that_are_already_https(): void
    {
        $rewriter = new SiteUrlRewriter('http://baztro.com', 'https://baztro.com');

        $count = 0;
        $result = $rewriter->replace('<a href="https://baztro.com/a"></a><a href="http://baztro.com/b"></a>', $count);

        $this->assertSame('<a href="https://baztro.com/a"></a><a href="https://baztro.com/b"></a>', $result);
        $this->assertSame(1, $count);
    }
}
