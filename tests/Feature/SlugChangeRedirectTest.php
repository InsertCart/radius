<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin URLs for slugged records are keyed by the slug itself, so saving a new
 * slug retires the address the editor is sitting on. Every save screen must
 * hand the browser the record's NEW address; sending it "back" to the old one
 * is a 404 immediately after a successful save.
 */
class SlugChangeRedirectTest extends TestCase
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

    public function test_renaming_a_page_slug_redirects_to_the_new_edit_url(): void
    {
        $page = Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published']);

        $this->actingAs($this->admin())
            ->from(route('admin.pages.edit', $page))
            ->put(route('admin.pages.update', $page), [
                'title' => 'About', 'slug' => 'about-us', 'status' => 'published',
            ])
            ->assertRedirect(route('admin.pages.edit', 'about-us'));

        $this->assertSame('about-us', $page->fresh()->slug);
    }

    public function test_renaming_a_post_slug_redirects_to_the_new_edit_url(): void
    {
        $post = Post::create(['title' => 'Hello', 'slug' => 'hello', 'status' => 'published']);

        $this->actingAs($this->admin())
            ->from(route('admin.posts.edit', $post))
            ->put(route('admin.posts.update', $post), [
                'title' => 'Hello', 'slug' => 'hello-world', 'status' => 'published',
            ])
            ->assertRedirect(route('admin.posts.edit', 'hello-world'));
    }

    public function test_renaming_a_product_slug_redirects_to_the_new_edit_url(): void
    {
        $product = Product::create([
            'name' => 'Mug', 'slug' => 'mug', 'price' => 10, 'type' => 'simple', 'status' => 'published',
        ]);

        $this->actingAs($this->admin())
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), [
                'name' => 'Mug', 'slug' => 'enamel-mug', 'price' => 10,
                'type' => 'simple', 'status' => 'published',
            ])
            ->assertRedirect(route('admin.products.edit', 'enamel-mug'));
    }

    public function test_renaming_a_category_slug_redirects_to_the_new_edit_url(): void
    {
        $category = Category::create(['name' => 'News', 'slug' => 'news', 'type' => Category::TYPE_BLOG]);

        $this->actingAs($this->admin())
            ->from(route('admin.categories.edit', $category))
            ->put(route('admin.categories.update', $category), [
                'name' => 'News', 'slug' => 'updates', 'type' => Category::TYPE_BLOG,
            ])
            ->assertRedirect(route('admin.categories.edit', 'updates'));
    }

    public function test_renaming_a_menu_slug_redirects_to_the_new_edit_url(): void
    {
        $menu = Menu::create(['name' => 'Header', 'slug' => 'header']);

        $this->actingAs($this->admin())
            ->from(route('admin.menus.edit', $menu))
            ->put(route('admin.menus.update', $menu), ['name' => 'Header', 'slug' => 'primary'])
            ->assertRedirect(route('admin.menus.edit', 'primary'));
    }

    /**
     * A requested slug that is already taken is saved with a numeric suffix,
     * so the redirect has to follow the slug that was actually stored.
     */
    public function test_redirect_follows_the_deduplicated_slug(): void
    {
        Page::create(['title' => 'Contact', 'slug' => 'contact', 'status' => 'published']);
        $page = Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published']);

        $this->actingAs($this->admin())
            ->from(route('admin.pages.edit', $page))
            ->put(route('admin.pages.update', $page), [
                'title' => 'About', 'slug' => 'contact', 'status' => 'published',
            ])
            ->assertRedirect(route('admin.pages.edit', $page->fresh()->slug));

        $this->assertSame('contact-2', $page->fresh()->slug);
    }
}
