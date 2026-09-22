<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Module;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Every route in routes/api.php, hit at least once for a working response and
 * at least once for the error it is supposed to produce.
 *
 * MobileApiTest.php covers the security properties - the front door, tokens,
 * feature gating, one customer's data staying away from another's. This file
 * is the complement: it walks the endpoint list itself and checks that each
 * one answers the shape a client actually needs, and fails the way its own
 * validation and business rules say it should.
 */
class MobileApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private ApiClient $client;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->get('/');
        modules()->sync();

        Module::where('slug', 'api')->update(['enabled' => true]);
        modules()->flush();

        // Every group on, so every route in the file is actually reachable.
        api()->saveFeatures(array_keys(config('api.features')));

        // Nothing here is testing mail delivery; without this, resending a
        // verification email tries a real SMTP connection and fails the test
        // on an infrastructure error rather than an assertion.
        Notification::fake();

        [$this->client, $this->secret] = ApiClient::issue('Endpoint test app');
    }

    private function h(?string $token = null, array $extra = []): array
    {
        return array_filter([
            'X-Api-Key' => $this->client->client_id,
            'X-Api-Secret' => $this->secret,
            'Authorization' => $token ? 'Bearer '.$token : null,
        ]) + $extra;
    }

    private function customer(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Asha Menon',
            'email' => 'asha@example.test',
            'password' => Hash::make('secret-pass-1'),
            'role' => User::ROLE_CUSTOMER,
            'status' => 'active',
            'email_verified_at' => now(),
        ], $attributes));
    }

    private function signIn(?User $user = null): array
    {
        $user ??= $this->customer();

        return $this->withHeaders($this->h())
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-pass-1'])
            ->assertOk()
            ->json('data');
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Filter Coffee', 'slug' => 'filter-coffee-'.uniqid(), 'price' => 45000,
            'type' => 'simple', 'status' => 'published', 'manage_stock' => true, 'stock' => 10,
        ], $attributes));
    }

    // Site -------------------------------------------------------------------

    public function test_get_site(): void
    {
        $this->withHeaders($this->h())->getJson('/api/v1/site')
            ->assertOk()
            ->assertJsonStructure(['data' => ['name', 'currency', 'features', 'account']]);
    }

    public function test_get_menus(): void
    {
        $this->withHeaders($this->h())->getJson('/api/v1/menus')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    // Auth ---------------------------------------------------------------------

    public function test_login_success_and_validation_error(): void
    {
        $user = $this->customer();

        $this->withHeaders($this->h())
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-pass-1'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'expires_at', 'user']]);

        // Missing password: framework validation, 422 with field errors.
        $this->withHeaders($this->h())
            ->postJson('/api/v1/auth/login', ['email' => $user->email])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_me_get_and_update(): void
    {
        $session = $this->signIn();

        $this->withHeaders($this->h($session['access_token']))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'asha@example.test');

        $this->withHeaders($this->h($session['access_token']))
            ->patchJson('/api/v1/auth/me', ['name' => 'Asha M.'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Asha M.');

        // A field not on the allow-list (email) is silently ignored, not
        // rejected and not applied.
        $before = $this->customer2($session)->email;
        $this->withHeaders($this->h($session['access_token']))
            ->patchJson('/api/v1/auth/me', ['email' => 'hijack@example.test'])
            ->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'asha@example.test']);
    }

    private function customer2(array $session): User
    {
        return User::where('email', $session['user']['email'])->firstOrFail();
    }

    public function test_password_update_wrong_current_password_is_rejected(): void
    {
        $session = $this->signIn();

        $this->withHeaders($this->h($session['access_token']))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'not-it',
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'wrong_password');
    }

    public function test_devices_list_and_revoke_unknown_id(): void
    {
        $session = $this->signIn();

        $this->withHeaders($this->h($session['access_token']))
            ->getJson('/api/v1/auth/devices')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->h($session['access_token']))
            ->deleteJson('/api/v1/auth/devices/999999')
            ->assertNotFound()
            ->assertJsonPath('error', 'not_found');
    }

    public function test_register_success_and_duplicate_email(): void
    {
        $payload = [
            'name' => 'New Customer', 'email' => 'new@example.test',
            'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
            'terms' => true,
        ];

        $this->withHeaders($this->h())
            ->postJson('/api/v1/auth/register', $payload)
            ->assertCreated()
            ->assertJsonStructure(['data' => ['access_token', 'user']]);

        // Same email again: validation failure, not a 500.
        $this->withHeaders($this->h())
            ->postJson('/api/v1/auth/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_registration_closed_refuses_with_403(): void
    {
        settings()->set('registration_enabled', false);

        $this->withHeaders($this->h())
            ->postJson('/api/v1/auth/register', [
                'name' => 'X', 'email' => 'x@example.test',
                'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1', 'terms' => true,
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'registration_closed');
    }

    public function test_forgot_password_answers_the_same_for_a_real_and_fake_address(): void
    {
        $this->customer();

        $real = $this->withHeaders($this->h())
            ->postJson('/api/v1/auth/forgot-password', ['email' => 'asha@example.test']);
        $fake = $this->withHeaders($this->h())
            ->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test']);

        $real->assertOk();
        $fake->assertOk();
        $this->assertSame($real->json('message'), $fake->json('message'));
    }

    public function test_resend_verification(): void
    {
        settings()->set('email_verification', true);
        $user = $this->customer(['email_verified_at' => null]);
        $session = $this->signIn($user);

        $this->withHeaders($this->h($session['access_token']))
            ->postJson('/api/v1/auth/resend-verification')
            ->assertOk();
    }

    // Posts, categories, tags, comments ------------------------------------

    public function test_posts_index_show_categories_tags(): void
    {
        $category = Category::create(['type' => 'blog', 'name' => 'News', 'slug' => 'news', 'is_active' => true]);
        Post::create([
            'title' => 'Hello world', 'slug' => 'hello-world', 'status' => 'published',
            'published_at' => now()->subDay(), 'category_id' => $category->id, 'allow_comments' => true,
        ]);

        $this->withHeaders($this->h())->getJson('/api/v1/posts')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withHeaders($this->h())->getJson('/api/v1/posts/hello-world')
            ->assertOk()->assertJsonPath('data.slug', 'hello-world');

        $this->withHeaders($this->h())->getJson('/api/v1/posts/does-not-exist')
            ->assertNotFound()->assertJsonPath('error', 'not_found');

        $this->withHeaders($this->h())->getJson('/api/v1/post-categories')
            ->assertOk()->assertJsonPath('data.0.slug', 'news');

        $this->withHeaders($this->h())->getJson('/api/v1/post-tags')
            ->assertOk()->assertJsonStructure(['data']);
    }

    public function test_post_comments_read_and_write_and_closed_comments(): void
    {
        $open = Post::create(['title' => 'Open', 'slug' => 'open-post', 'status' => 'published', 'published_at' => now(), 'allow_comments' => true]);
        $closed = Post::create(['title' => 'Closed', 'slug' => 'closed-post', 'status' => 'published', 'published_at' => now(), 'allow_comments' => false]);

        $this->withHeaders($this->h())->getJson('/api/v1/posts/open-post/comments')
            ->assertOk()->assertJsonStructure(['data']);

        $this->withHeaders($this->h())
            ->postJson('/api/v1/posts/open-post/comments', [
                'body' => 'A genuinely useful comment.', 'author_name' => 'Guest', 'author_email' => 'guest@example.test',
            ])
            ->assertCreated();

        // Held for moderation: not visible in the approved list yet.
        $this->assertDatabaseHas('comments', ['post_id' => $open->id, 'status' => 'pending']);

        $this->withHeaders($this->h())
            ->postJson('/api/v1/posts/closed-post/comments', ['body' => 'Too late', 'author_name' => 'G', 'author_email' => 'g@example.test'])
            ->assertForbidden()
            ->assertJsonPath('error', 'comments_closed');
    }

    // Pages ------------------------------------------------------------------

    public function test_pages_index_and_show(): void
    {
        Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published']);

        $this->withHeaders($this->h())->getJson('/api/v1/pages')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withHeaders($this->h())->getJson('/api/v1/pages/about')
            ->assertOk()->assertJsonPath('data.slug', 'about');

        $this->withHeaders($this->h())->getJson('/api/v1/pages/nope')
            ->assertNotFound();
    }

    // Search -------------------------------------------------------------------

    public function test_search_grouped_and_typed(): void
    {
        Post::create(['title' => 'Searchable Post', 'slug' => 'searchable-post', 'status' => 'published', 'published_at' => now()]);

        $this->withHeaders($this->h())->getJson('/api/v1/search?q=Searchable')
            ->assertOk()->assertJsonStructure(['data' => ['query', 'types', 'groups']]);

        $this->withHeaders($this->h())->getJson('/api/v1/search?q=Searchable&type=post')
            ->assertOk()->assertJsonStructure(['data', 'meta']);

        // Below the minimum character count: empty result, not an error.
        $this->withHeaders($this->h())->getJson('/api/v1/search?q=a')
            ->assertOk()->assertJsonPath('data.groups', []);
    }

    // Products, categories, reviews -------------------------------------------

    public function test_products_index_show_categories(): void
    {
        $category = Category::create(['type' => 'shop', 'name' => 'Coffee', 'slug' => 'coffee', 'is_active' => true]);
        $product = $this->product();
        $product->categories()->attach($category->id);

        $this->withHeaders($this->h())->getJson('/api/v1/products')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withHeaders($this->h())->getJson('/api/v1/products?min=100&max=10')
            ->assertOk()->assertJsonCount(0, 'data');

        $this->withHeaders($this->h())->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()->assertJsonStructure(['data' => ['price' => ['amount', 'formatted'], 'variants', 'categories']]);

        $this->withHeaders($this->h())->getJson('/api/v1/products/no-such-product')
            ->assertNotFound();

        $this->withHeaders($this->h())->getJson('/api/v1/product-categories')
            ->assertOk()->assertJsonPath('data.0.slug', 'coffee');
    }

    public function test_reviews_index_store_and_duplicate_is_rejected(): void
    {
        $product = $this->product();
        $session = $this->signIn();

        $this->withHeaders($this->h())->getJson("/api/v1/products/{$product->slug}/reviews")
            ->assertOk()->assertJsonStructure(['data', 'summary']);

        $this->withHeaders($this->h($session['access_token']))
            ->postJson("/api/v1/products/{$product->slug}/reviews", ['rating' => 5, 'title' => 'Great', 'body' => 'Loved it.'])
            ->assertCreated();

        $this->withHeaders($this->h($session['access_token']))
            ->postJson("/api/v1/products/{$product->slug}/reviews", ['rating' => 4])
            ->assertStatus(409)
            ->assertJsonPath('error', 'already_reviewed');

        // A guest cannot review at all. Headers are flushed first: a bearer
        // token set with withHeaders() on an earlier call in this test
        // persists on every later call otherwise, guest or not.
        $this->flushHeaders();
        $this->withHeaders($this->h())
            ->postJson("/api/v1/products/{$product->slug}/reviews", ['rating' => 5])
            ->assertUnauthorized();

        // Out-of-range rating: validation error.
        $this->withHeaders($this->h($session['access_token']))
            ->postJson("/api/v1/products/{$this->product()->slug}/reviews", ['rating' => 9])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');
    }

    // Cart ---------------------------------------------------------------------

    public function test_cart_full_lifecycle(): void
    {
        $product = $this->product();

        $add = $this->withHeaders($this->h())
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 3]);
        $add->assertCreated()->assertJsonPath('data.item_count', 3);
        $token = $add->json('data.token');
        $itemId = $add->json('data.items.0.id');

        $this->withHeaders($this->h() + ['X-Cart-Token' => $token])
            ->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 1])
            ->assertOk()->assertJsonPath('data.item_count', 1);

        $this->withHeaders($this->h() + ['X-Cart-Token' => $token])
            ->deleteJson("/api/v1/cart/items/{$itemId}")
            ->assertOk()->assertJsonPath('data.item_count', 0);

        // Acting on an item id that is not this guest's cart: not found, not
        // somebody else's basket. Headers flushed first, or the previous
        // X-Cart-Token - a persistent default in this test client, not a
        // per-call header - would put this "new" guest in the same cart.
        $this->flushHeaders();
        $add2 = $this->withHeaders($this->h())
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id]);
        $otherToken = $add2->json('data.token');
        $otherItemId = $add2->json('data.items.0.id');
        $this->assertNotSame($token, $otherToken, "The second guest must not have landed in the first guest's cart.");

        $this->withHeaders($this->h() + ['X-Cart-Token' => $token])
            ->patchJson("/api/v1/cart/items/{$otherItemId}", ['quantity' => 2])
            ->assertNotFound();

        // Adding more than is in stock. 50, not 9999: the field itself
        // rejects anything over 999 before the stock check ever runs, which
        // would exercise the wrong error path.
        $this->withHeaders($this->h())
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 50])
            ->assertStatus(422)
            ->assertJsonPath('error', 'out_of_stock');

        // A product that does not exist.
        $this->withHeaders($this->h())
            ->postJson('/api/v1/cart/items', ['product_id' => 999999])
            ->assertNotFound();
    }

    public function test_cart_coupon_apply_and_remove_and_invalid_code(): void
    {
        $product = $this->product();
        Coupon::create(['code' => 'SAVE10', 'type' => 'percent', 'value' => 1000, 'is_active' => true]);

        $add = $this->withHeaders($this->h())->postJson('/api/v1/cart/items', ['product_id' => $product->id]);
        $token = $add->json('data.token');

        $this->withHeaders($this->h() + ['X-Cart-Token' => $token])
            ->postJson('/api/v1/cart/coupon', ['code' => 'SAVE10'])
            ->assertOk()
            ->assertJsonPath('data.coupon.code', 'SAVE10');

        $this->withHeaders($this->h() + ['X-Cart-Token' => $token])
            ->deleteJson('/api/v1/cart/coupon')
            ->assertOk()
            ->assertJsonPath('data.coupon', null);

        $this->withHeaders($this->h() + ['X-Cart-Token' => $token])
            ->postJson('/api/v1/cart/coupon', ['code' => 'NOT-REAL'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'coupon_rejected');
    }

    // Checkout & orders ----------------------------------------------------

    public function test_checkout_show_and_store_validation_error(): void
    {
        $product = $this->product();
        $add = $this->withHeaders($this->h())->postJson('/api/v1/cart/items', ['product_id' => $product->id]);
        $token = $add->json('data.token');

        $this->withHeaders($this->h() + ['X-Cart-Token' => $token])
            ->getJson('/api/v1/checkout')
            ->assertOk()
            ->assertJsonStructure(['data' => ['cart', 'payment_methods', 'countries']]);

        // Missing required billing fields.
        $this->withHeaders($this->h() + ['X-Cart-Token' => $token])
            ->postJson('/api/v1/checkout', ['email' => 'g@example.test', 'payment_gateway' => 'cod', 'terms' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors('billing.name');
    }

    public function test_checkout_store_with_empty_cart_is_refused(): void
    {
        $this->withHeaders($this->h())
            ->postJson('/api/v1/checkout', [
                'email' => 'g@example.test', 'payment_gateway' => 'cod', 'terms' => true,
                'billing' => ['name' => 'A', 'line1' => '1 Rd', 'city' => 'B', 'country' => 'IN'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'cart_empty');
    }

    public function test_orders_index_requires_sign_in(): void
    {
        $this->withHeaders($this->h())->getJson('/api/v1/orders')
            ->assertUnauthorized();

        $session = $this->signIn();
        $this->withHeaders($this->h($session['access_token']))->getJson('/api/v1/orders')
            ->assertOk()->assertJsonStructure(['data', 'meta']);
    }

    // Addresses ------------------------------------------------------------

    public function test_addresses_full_crud_and_ownership(): void
    {
        $session = $this->signIn();
        $stranger = $this->signIn($this->customer(['email' => 'stranger@example.test']));

        $store = $this->withHeaders($this->h($session['access_token']))
            ->postJson('/api/v1/addresses', [
                'name' => 'Asha Menon', 'line1' => '12 Residency Road', 'city' => 'Bengaluru', 'country' => 'IN',
            ]);
        $store->assertCreated();
        $id = $store->json('data.id');

        $this->withHeaders($this->h($session['access_token']))->getJson('/api/v1/addresses')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withHeaders($this->h($session['access_token']))
            ->patchJson("/api/v1/addresses/{$id}", [
                'name' => 'Asha M.', 'line1' => '12 Residency Road', 'city' => 'Bengaluru', 'country' => 'IN',
            ])
            ->assertOk()->assertJsonPath('data.name', 'Asha M.');

        // A stranger cannot touch this address.
        $this->withHeaders($this->h($stranger['access_token']))
            ->patchJson("/api/v1/addresses/{$id}", [
                'name' => 'Hijacked', 'line1' => 'x', 'city' => 'x', 'country' => 'IN',
            ])
            ->assertNotFound();

        $this->withHeaders($this->h($stranger['access_token']))
            ->deleteJson("/api/v1/addresses/{$id}")
            ->assertNotFound();

        $this->withHeaders($this->h($session['access_token']))
            ->deleteJson("/api/v1/addresses/{$id}")
            ->assertOk();

        // Bad country code: validation error.
        $this->withHeaders($this->h($session['access_token']))
            ->postJson('/api/v1/addresses', ['name' => 'A', 'line1' => 'x', 'city' => 'x', 'country' => 'ZZ'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('country');
    }

    public function test_addresses_off_when_the_setting_is_off(): void
    {
        settings()->set('shop_save_addresses', false);
        $session = $this->signIn();

        $this->withHeaders($this->h($session['access_token']))->getJson('/api/v1/addresses')
            ->assertNotFound()
            ->assertJsonPath('error', 'not_available');
    }

    // Contact, newsletter, devices -------------------------------------------

    public function test_contact_success_and_validation(): void
    {
        $this->withHeaders($this->h())
            ->postJson('/api/v1/contact', [
                'name' => 'Asha', 'email' => 'asha@example.test', 'message' => 'A message long enough to pass validation.',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('contact_submissions', ['email' => 'asha@example.test']);

        $this->withHeaders($this->h())
            ->postJson('/api/v1/contact', ['name' => 'Asha'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'message']);
    }

    public function test_newsletter_subscribe_is_idempotent(): void
    {
        $first = $this->withHeaders($this->h())->postJson('/api/v1/newsletter', ['email' => 'sub@example.test']);
        $second = $this->withHeaders($this->h())->postJson('/api/v1/newsletter', ['email' => 'sub@example.test']);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('message'), $second->json('message'));
        $this->assertSame(1, Subscriber::where('email', 'sub@example.test')->count());
    }

    public function test_devices_register_and_forget(): void
    {
        settings()->set('firebase_enabled', true);
        settings()->set('firebase_api_key', 'test-key');
        settings()->set('firebase_project_id', 'test-project');

        $token = 'fcm-token-'.uniqid();

        $this->withHeaders($this->h())
            ->postJson('/api/v1/devices', ['token' => $token, 'platform' => 'android'])
            ->assertOk();

        $this->assertDatabaseHas('push_devices', ['token_hash' => hash('sha256', $token)]);

        $this->withHeaders($this->h())
            ->deleteJson('/api/v1/devices', ['token' => $token])
            ->assertOk();

        $this->assertDatabaseMissing('push_devices', ['token_hash' => hash('sha256', $token)]);
    }

    public function test_devices_register_refused_when_firebase_is_off(): void
    {
        settings()->set('firebase_enabled', false);

        $this->withHeaders($this->h())
            ->postJson('/api/v1/devices', ['token' => 'x'])
            ->assertNotFound()
            ->assertJsonPath('error', 'not_available');
    }

    // Validation is JSON everywhere, not an HTML error page -------------------

    public function test_a_404_route_returns_json_not_html(): void
    {
        $response = $this->withHeaders($this->h())->get('/api/v1/does-not-exist');

        $response->assertStatus(404);
        $this->assertJson($response->getContent());
    }

    public function test_an_unhandled_server_side_validation_failure_is_json(): void
    {
        // No Accept header sent at all - ForceJsonResponse must still make
        // this JSON rather than Laravel's default HTML validation page.
        $response = $this->post('/api/v1/contact', [], $this->h());

        $response->assertStatus(422);
        $this->assertJson($response->getContent());
    }
}
