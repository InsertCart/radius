<?php

namespace Tests\Feature;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Builder\RegionManager;
use App\Cms\Builder\RegionStarter;
use App\Models\Layout;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The product page template: one builder layout, rendered against whichever
 * product is being viewed, that replaces the theme's product page only once
 * it has been published.
 */
class ProductPageTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');
    }

    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'owner@x.test'], [
            'name' => 'Owner', 'password' => Hash::make('password-123'),
            'role' => User::ROLE_ADMIN, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Night Owl Decaf', 'slug' => 'night-owl-decaf', 'price' => 1550,
            'type' => 'simple', 'status' => 'published', 'manage_stock' => true, 'stock' => 40,
        ], $attributes));
    }

    private function publishTemplate(): Layout
    {
        $layout = Layout::forRegion('product');
        $layout->publish(app(RegionStarter::class)->build('product', 'classic'), $this->admin()->id);
        app(RegionManager::class)->flush();

        return $layout;
    }

    public function test_the_theme_page_is_used_until_a_template_is_published(): void
    {
        $product = $this->product();

        $this->get($product->url())
            ->assertOk()
            ->assertSee('Night Owl Decaf')
            ->assertDontSee('cb-buy', false);
    }

    public function test_a_published_template_renders_the_viewed_product(): void
    {
        $product = $this->product(['sale_price' => 1200]);
        $this->publishTemplate();

        $this->get($product->url())
            ->assertOk()
            ->assertSee('Night Owl Decaf')
            ->assertSee('class="cb-buy"', false)
            ->assertSee('name="buy_now"', false)
            ->assertSee(money(1200))
            ->assertSee('In stock, ready to ship')
            ->assertSee('Secure payments');
    }

    public function test_the_template_shows_out_of_stock_instead_of_buttons(): void
    {
        $product = $this->product(['stock' => 0]);
        $this->publishTemplate();

        $this->get($product->url())
            ->assertOk()
            ->assertDontSee('name="buy_now"', false)
            ->assertSee('Currently unavailable');
    }

    public function test_buy_now_adds_to_the_cart_and_goes_to_checkout(): void
    {
        $product = $this->product();

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2, 'buy_now' => 1])
            ->assertRedirect(route('checkout.index'));

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_product_widgets_are_only_offered_on_the_product_template(): void
    {
        $types = fn (?string $area) => collect(app(BlockRegistry::class)->panel($area))
            ->pluck('widgets')->flatten(1)->pluck('type');

        $this->assertContains('product-add-to-cart', $types('product'));
        $this->assertNotContains('product-add-to-cart', $types(null));
        $this->assertNotContains('product-add-to-cart', $types('cart'));
    }

    public function test_opening_the_builder_from_a_product_goes_to_the_template(): void
    {
        $product = $this->product();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.builder.edit', ['type' => 'product', 'id' => $product->id]))
            ->assertRedirect(route('admin.builder.region', ['region' => 'product', 'product' => $product->id]));

        // The description-only editor is still reachable on request.
        $this->actingAs($admin)
            ->get(route('admin.builder.edit', ['type' => 'product', 'id' => $product->id, 'description' => 1]))
            ->assertOk();
    }

    public function test_an_empty_template_opens_as_a_draft_copy_of_the_theme_layout(): void
    {
        $product = $this->product();
        $other = $this->product(['name' => 'Huila Reserve', 'slug' => 'huila-reserve', 'sku' => null]);

        $this->actingAs($this->admin())
            ->get(route('admin.builder.region', ['region' => 'product', 'product' => $product->id]))
            ->assertOk()
            ->assertSee('Previewing Night Owl Decaf');

        $layout = Layout::forRegion('product');
        $this->assertNotSame([], $layout->editableTree());
        $this->assertNull($layout->published_at, 'Opening the editor must not publish anything.');

        // Nothing changes for shoppers until it is published.
        $this->get($other->url())->assertOk()->assertDontSee('class="cb-buy"', false);

        // The preview renders the product the editor was opened from.
        $this->actingAs($this->admin())
            ->get(route('admin.builder.preview.region', ['region' => 'product', 'product' => $product->id]))
            ->assertOk()
            ->assertSee('Night Owl Decaf')
            ->assertSee('class="cb-buy"', false);
    }

    public function test_the_template_editor_and_its_preview_open(): void
    {
        $this->product();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.builder.region', 'product'))
            ->assertOk()
            ->assertSee('Product page');

        $this->publishTemplate();

        $this->actingAs($admin)->get(route('admin.builder.preview.region', 'product'))
            ->assertOk()
            ->assertSee('Night Owl Decaf');
    }
}
