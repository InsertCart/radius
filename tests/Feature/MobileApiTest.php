<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Models\Module;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The mobile API, and mostly the locks on it.
 *
 * The promise this module makes is narrow and worth testing literally:
 * nothing answers without a registered app's credentials, only the endpoint
 * groups an owner switched on answer at all, a customer reaches their own
 * data and nobody else's, and there is no way through to the admin panel.
 */
class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private ApiClient $client;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        // Boots the CMS the way a first request does, then writes the module
        // rows: everything on except the API, which ships off.
        $this->get('/');
        modules()->sync();

        [$this->client, $this->secret] = ApiClient::issue('Test app');
    }

    // Helpers ---------------------------------------------------------------

    private function enableApi(array $features = []): void
    {
        Module::where('slug', 'api')->update(['enabled' => true]);
        modules()->flush();

        if ($features !== []) {
            api()->saveFeatures($features);
        } else {
            api()->seedDefaults();
        }
    }

    /** Headers a well-behaved app sends. */
    private function appHeaders(?string $token = null): array
    {
        return array_filter([
            'X-Api-Key' => $this->client->client_id,
            'X-Api-Secret' => $this->secret,
            'Authorization' => $token ? 'Bearer '.$token : null,
        ]);
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

        $response = $this->withHeaders($this->appHeaders())
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'secret-pass-1',
                'device_name' => 'Test phone',
            ]);

        $response->assertOk();

        return $response->json('data');
    }

    // The module switch ------------------------------------------------------

    public function test_the_api_ships_switched_off(): void
    {
        $this->assertFalse(modules()->shipsEnabled('api'));
        $this->assertFalse((bool) Module::where('slug', 'api')->value('enabled'));

        // Credentials and all: with the module off there is nothing to call.
        $this->withHeaders($this->appHeaders())
            ->getJson('/api/v1/site')
            ->assertNotFound()
            ->assertJsonPath('error', 'api_disabled');
    }

    public function test_switching_the_module_on_opens_the_api(): void
    {
        $this->enableApi();

        $this->withHeaders($this->appHeaders())
            ->getJson('/api/v1/site')
            ->assertOk()
            ->assertJsonPath('data.name', setting('site_name'));
    }

    // The front door ---------------------------------------------------------

    public function test_knowing_the_url_is_not_enough(): void
    {
        $this->enableApi();

        // No credentials at all.
        $this->getJson('/api/v1/site')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'missing_key');

        // A key nobody issued.
        $this->withHeaders(['X-Api-Key' => 'rad_not_a_real_key', 'X-Api-Secret' => 'x'])
            ->getJson('/api/v1/site')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'unknown_key');

        // The right key, the wrong secret.
        $this->withHeaders(['X-Api-Key' => $this->client->client_id, 'X-Api-Secret' => 'wrong'])
            ->getJson('/api/v1/site')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'bad_credentials');

        // The right key, no secret.
        $this->withHeaders(['X-Api-Key' => $this->client->client_id])
            ->getJson('/api/v1/site')
            ->assertUnauthorized();
    }

    public function test_an_app_that_has_been_switched_off_is_refused(): void
    {
        $this->enableApi();
        $this->client->forceFill(['enabled' => false])->save();

        $this->withHeaders($this->appHeaders())
            ->getJson('/api/v1/site')
            ->assertForbidden()
            ->assertJsonPath('error', 'app_disabled');
    }

    public function test_the_secret_is_never_handed_back(): void
    {
        $this->enableApi();

        $admin = $this->customer([
            'email' => 'admin@example.test',
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get('/'.config('cms.admin_prefix').'/api');

        $response->assertOk();
        $response->assertDontSee($this->secret);
    }

    // Endpoint groups --------------------------------------------------------

    public function test_a_group_that_is_not_switched_on_answers_404(): void
    {
        // The shipped defaults leave the shop off.
        $this->enableApi();

        Product::create([
            'name' => 'Filter Coffee', 'slug' => 'filter-coffee', 'price' => 45000,
            'type' => 'simple', 'status' => 'published', 'manage_stock' => true, 'stock' => 10,
        ]);

        $this->withHeaders($this->appHeaders())
            ->getJson('/api/v1/products')
            ->assertNotFound()
            ->assertJsonPath('error', 'feature_disabled');

        api()->saveFeatures(['shop']);

        $this->withHeaders($this->appHeaders())
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'filter-coffee');
    }

    public function test_switching_the_shop_on_brings_customer_accounts_with_it(): void
    {
        $this->enableApi(['posts']);

        $this->assertFalse(api()->feature('registration'));

        api()->saveFeatures(['posts', 'shop']);

        $this->assertTrue(api()->feature('shop'));
        $this->assertTrue(api()->feature('auth'), 'The shop needs customers to be able to sign in.');
        $this->assertTrue(api()->feature('registration'), 'Registration is included with the shop.');
    }

    public function test_a_group_cannot_be_switched_on_without_its_module(): void
    {
        Module::where('slug', 'blog')->update(['enabled' => false]);
        $this->enableApi();

        api()->saveFeatures(['posts', 'pages']);

        $this->assertFalse(api()->feature('posts'), 'Posts cannot be exposed by a site with no blog.');
        $this->assertTrue(api()->feature('pages'));
    }

    public function test_content_endpoints_only_publish_published_content(): void
    {
        $this->enableApi();

        Post::create(['title' => 'Live one', 'slug' => 'live-one', 'status' => 'published', 'published_at' => now()->subDay()]);
        Post::create(['title' => 'Draft one', 'slug' => 'draft-one', 'status' => 'draft']);

        $response = $this->withHeaders($this->appHeaders())->getJson('/api/v1/posts');

        $response->assertOk();
        $this->assertSame(['live-one'], array_column($response->json('data'), 'slug'));

        $this->withHeaders($this->appHeaders())
            ->getJson('/api/v1/posts/draft-one')
            ->assertNotFound();
    }

    // Signing in -------------------------------------------------------------

    public function test_a_customer_signs_in_and_reaches_their_own_account(): void
    {
        $this->enableApi();
        $user = $this->customer();

        // Without a token, an account endpoint is closed.
        $this->withHeaders($this->appHeaders())
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'not_signed_in');

        $session = $this->signIn($user);

        $this->assertNotEmpty($session['access_token']);
        $this->assertNotEmpty($session['refresh_token']);

        $this->withHeaders($this->appHeaders($session['access_token']))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_a_wrong_password_says_nothing_about_the_account(): void
    {
        $this->enableApi();
        $this->customer();

        $unknown = $this->withHeaders($this->appHeaders())
            ->postJson('/api/v1/auth/login', ['email' => 'nobody@example.test', 'password' => 'secret-pass-1']);

        $wrong = $this->withHeaders($this->appHeaders())
            ->postJson('/api/v1/auth/login', ['email' => 'asha@example.test', 'password' => 'not-the-password']);

        $unknown->assertUnauthorized();
        $wrong->assertUnauthorized();
        $this->assertSame($unknown->json('message'), $wrong->json('message'));
    }

    public function test_staff_accounts_cannot_sign_in_unless_the_owner_allows_it(): void
    {
        $this->enableApi();

        $admin = $this->customer(['email' => 'admin@example.test', 'role' => User::ROLE_ADMIN]);

        $this->withHeaders($this->appHeaders())
            ->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'secret-pass-1'])
            ->assertForbidden()
            ->assertJsonPath('error', 'staff_blocked');

        api()->saveSecurity(['allow_staff' => true]);

        $this->withHeaders($this->appHeaders())
            ->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'secret-pass-1'])
            ->assertOk();
    }

    public function test_a_suspended_account_cannot_sign_in(): void
    {
        $this->enableApi();
        $user = $this->customer(['status' => 'suspended']);

        $this->withHeaders($this->appHeaders())
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-pass-1'])
            ->assertForbidden()
            ->assertJsonPath('error', 'account_inactive');
    }

    public function test_a_token_only_works_through_the_app_it_was_issued_to(): void
    {
        $this->enableApi();
        $session = $this->signIn();

        [$other, $otherSecret] = ApiClient::issue('Somebody else\'s app');

        $this->withHeaders([
            'X-Api-Key' => $other->client_id,
            'X-Api-Secret' => $otherSecret,
            'Authorization' => 'Bearer '.$session['access_token'],
        ])
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'wrong_app');
    }

    public function test_signing_out_kills_the_token(): void
    {
        $this->enableApi();
        $session = $this->signIn();

        $this->withHeaders($this->appHeaders($session['access_token']))
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->withHeaders($this->appHeaders($session['access_token']))
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'invalid_token');
    }

    public function test_refreshing_rotates_both_halves(): void
    {
        $this->enableApi();
        $session = $this->signIn();

        $refreshed = $this->withHeaders($this->appHeaders())
            ->postJson('/api/v1/auth/refresh', ['refresh_token' => $session['refresh_token']]);

        $refreshed->assertOk();

        $this->assertNotSame($session['access_token'], $refreshed->json('data.access_token'));
        $this->assertNotSame($session['refresh_token'], $refreshed->json('data.refresh_token'));

        // The old refresh token is spent.
        $this->withHeaders($this->appHeaders())
            ->postJson('/api/v1/auth/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertUnauthorized();

        // And so is the access token it replaced.
        $this->withHeaders($this->appHeaders($session['access_token']))
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_changing_a_password_signs_the_other_devices_out(): void
    {
        $this->enableApi();
        $user = $this->customer();

        $phone = $this->signIn($user);
        $tablet = $this->signIn($user);

        $this->withHeaders($this->appHeaders($tablet['access_token']))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'secret-pass-1',
                'password' => 'a-brand-new-1',
                'password_confirmation' => 'a-brand-new-1',
            ])
            ->assertOk();

        // The device that made the change keeps working; the other does not.
        $this->withHeaders($this->appHeaders($tablet['access_token']))
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->withHeaders($this->appHeaders($phone['access_token']))
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    // One customer's data is not another's ------------------------------------

    public function test_a_customer_reaches_only_their_own_orders(): void
    {
        $this->enableApi();
        api()->saveFeatures(['auth', 'shop']);

        $mine = $this->customer();
        $theirs = $this->customer(['email' => 'someone@example.test']);

        $order = Order::create([
            'user_id' => $theirs->id, 'email' => $theirs->email, 'status' => 'pending',
            'payment_status' => 'unpaid', 'currency' => 'USD', 'subtotal' => 1000,
            'grand_total' => 1000,
        ]);

        $session = $this->signIn($mine);

        $this->withHeaders($this->appHeaders($session['access_token']))
            ->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withHeaders($this->appHeaders($session['access_token']))
            ->getJson('/api/v1/orders/'.$order->order_number)
            ->assertNotFound();
    }

    public function test_a_guest_cannot_read_a_guest_order_without_its_handle(): void
    {
        $this->enableApi();
        api()->saveFeatures(['auth', 'shop']);

        $order = Order::create([
            'user_id' => null, 'email' => 'guest@example.test', 'status' => 'pending',
            'payment_status' => 'unpaid', 'currency' => 'USD', 'subtotal' => 1000,
            'grand_total' => 1000,
        ]);

        $this->withHeaders($this->appHeaders())
            ->getJson('/api/v1/orders/'.$order->order_number)
            ->assertNotFound();
    }

    public function test_a_cart_belongs_to_the_token_that_made_it(): void
    {
        $this->enableApi();
        api()->saveFeatures(['auth', 'shop']);

        $product = Product::create([
            'name' => 'Filter Coffee', 'slug' => 'filter-coffee', 'price' => 45000,
            'type' => 'simple', 'status' => 'published', 'manage_stock' => true, 'stock' => 10,
        ]);

        $added = $this->withHeaders($this->appHeaders())
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2]);

        $added->assertCreated();
        $token = $added->json('data.token');

        $this->assertNotEmpty($token);
        $this->assertSame(2, $added->json('data.item_count'));

        // The price came from the product, not from the request.
        $this->assertSame(90000, $added->json('data.totals.subtotal.amount'));

        // Another guest, no token: a different, empty cart.
        $this->withHeaders($this->appHeaders())
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 0);

        // The same guest, with the token: their cart.
        $this->withHeaders($this->appHeaders() + ['X-Cart-Token' => $token])
            ->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 2);
    }

    public function test_a_guest_cart_can_be_switched_off(): void
    {
        $this->enableApi();
        api()->saveFeatures(['auth', 'shop']);
        api()->saveSecurity(['guest_cart' => false]);

        $this->withHeaders($this->appHeaders())
            ->getJson('/api/v1/cart')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'sign_in_required');
    }

    // Signed requests --------------------------------------------------------

    public function test_signed_requests_can_be_required_and_replays_are_refused(): void
    {
        $this->enableApi();
        api()->saveSecurity(['signature_required' => true]);

        // The plain secret is no longer enough.
        $this->withHeaders($this->appHeaders())
            ->getJson('/api/v1/site')
            ->assertUnauthorized();

        $timestamp = (string) time();
        $nonce = 'nonce-'.bin2hex(random_bytes(8));

        $canonical = implode("\n", [
            $this->client->client_id,
            'GET',
            '/api/v1/site',
            '',
            $timestamp,
            $nonce,
            hash('sha256', ''),
        ]);

        $headers = [
            'X-Api-Key' => $this->client->client_id,
            'X-Api-Timestamp' => $timestamp,
            'X-Api-Nonce' => $nonce,
            'X-Api-Signature' => base64_encode(hash_hmac('sha256', $canonical, $this->secret, true)),
        ];

        // get() rather than getJson(), because a real GET carries no body and
        // the signature covers the body it actually sends.
        $this->withHeaders($headers)->get('/api/v1/site')->assertOk();

        // The very same request again is a replay, and is refused.
        $this->withHeaders($headers)->get('/api/v1/site')->assertUnauthorized();
    }

    // No admin surface -------------------------------------------------------

    public function test_the_api_exposes_no_admin_endpoint(): void
    {
        $this->enableApi();
        api()->saveFeatures(array_keys(config('api.features')));

        $admin = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'api.v1.'))
            ->filter(fn ($route) => str_contains((string) $route->getActionName(), 'Controllers\Admin'))
            ->map(fn ($route) => $route->uri());

        $this->assertTrue($admin->isEmpty(), 'An API route points at an admin controller: '.$admin->implode(', '));
    }

    public function test_api_credentials_do_not_open_the_admin_panel(): void
    {
        $this->enableApi();

        $admin = $this->customer(['email' => 'admin@example.test', 'role' => User::ROLE_ADMIN]);
        api()->saveSecurity(['allow_staff' => true]);

        $session = $this->signIn($admin);

        // A valid API token, offered to the admin panel, is not a session.
        $this->withHeaders($this->appHeaders($session['access_token']))
            ->get('/'.config('cms.admin_prefix'))
            ->assertRedirect();
    }

    public function test_an_editor_cannot_reach_the_api_screen(): void
    {
        $this->enableApi();

        $editor = $this->customer(['email' => 'editor@example.test', 'role' => User::ROLE_EDITOR]);

        $this->actingAs($editor)
            ->get('/'.config('cms.admin_prefix').'/api')
            ->assertForbidden();
    }

    // The admin screen -------------------------------------------------------

    public function test_an_admin_registers_an_app_and_sees_its_secret_once(): void
    {
        $this->enableApi();
        $admin = $this->customer(['email' => 'admin@example.test', 'role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->post('/'.config('cms.admin_prefix').'/api/apps', [
            'name' => 'Storefront app',
            'platform' => 'mobile',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('credentials');

        $credentials = session('credentials');
        $client = ApiClient::where('client_id', $credentials['client_id'])->firstOrFail();

        // What is stored is usable by this server and by nothing else: the
        // row holds it encrypted, not in the clear.
        $this->assertNotSame($credentials['secret'], $client->getRawOriginal('secret'));
        $this->assertTrue($client->secretMatches($credentials['secret']));

        // The screen shows it once, on the way back from creating it...
        $this->actingAs($admin)
            ->get('/'.config('cms.admin_prefix').'/api')
            ->assertOk()
            ->assertSee($credentials['secret'], false);

        // ...and never again.
        $this->actingAs($admin)
            ->get('/'.config('cms.admin_prefix').'/api')
            ->assertOk()
            ->assertDontSee($credentials['secret']);
    }

    public function test_deleting_an_app_signs_its_devices_out(): void
    {
        $this->enableApi();
        $session = $this->signIn();

        $this->assertSame(1, ApiToken::active()->count());

        $admin = $this->customer(['email' => 'admin@example.test', 'role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->delete('/'.config('cms.admin_prefix').'/api/apps/'.$this->client->id)
            ->assertRedirect();

        $this->assertSame(0, ApiToken::count());
    }

    public function test_checkout_hands_the_app_a_signed_link_rather_than_a_session(): void
    {
        $this->enableApi();
        api()->saveFeatures(['auth', 'shop']);

        PaymentGateway::updateOrCreate(
            ['slug' => 'cod'],
            ['name' => 'Cash on delivery', 'is_enabled' => true, 'mode' => 'live']
        );

        $product = Product::create([
            'name' => 'Filter Coffee', 'slug' => 'filter-coffee', 'price' => 45000,
            'type' => 'simple', 'status' => 'published', 'manage_stock' => true, 'stock' => 10,
        ]);

        $added = $this->withHeaders($this->appHeaders())
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id]);

        $cartToken = $added->json('data.token');

        $placed = $this->withHeaders($this->appHeaders() + ['X-Cart-Token' => $cartToken])
            ->postJson('/api/v1/checkout', [
                'email' => 'guest@example.test',
                'payment_gateway' => 'cod',
                'billing' => [
                    'name' => 'Asha Menon', 'line1' => '12 Residency Road',
                    'city' => 'Bengaluru', 'country' => 'IN',
                ],
                'terms' => '1',
            ]);

        $placed->assertCreated();

        // Cash on delivery settles without a browser.
        $this->assertSame('instructions', $placed->json('data.payment.type'));

        $number = $placed->json('data.order.number');
        $handle = $placed->json('data.order_token');

        $this->assertNotEmpty($handle, 'A guest needs a handle on the order they just placed.');

        // The handle, and only the handle, opens it.
        $this->withHeaders($this->appHeaders() + ['X-Order-Token' => $handle])
            ->getJson('/api/v1/orders/'.$number)
            ->assertOk()
            ->assertJsonPath('data.number', $number);

        $this->withHeaders($this->appHeaders() + ['X-Order-Token' => str_repeat('0', 32)])
            ->getJson('/api/v1/orders/'.$number)
            ->assertNotFound();
    }

    // Rate limiting ------------------------------------------------------------

    public function test_the_general_rate_limit_is_enforced_and_configurable(): void
    {
        $this->enableApi();
        api()->saveSecurity(['rate_limit' => 2]);

        $this->withHeaders($this->appHeaders())->getJson('/api/v1/site')->assertOk();
        $this->withHeaders($this->appHeaders())->getJson('/api/v1/site')->assertOk();

        $blocked = $this->withHeaders($this->appHeaders())->getJson('/api/v1/site');

        $blocked->assertStatus(429)
            ->assertJsonPath('error', 'rate_limited')
            ->assertHeader('Retry-After');

        // The message is ours, not the framework's debug-mode rendering of
        // the exception - which would carry 'exception', 'file' and 'trace'
        // keys alongside the message.
        $this->assertArrayNotHasKey('exception', $blocked->json());
    }

    public function test_the_rate_limit_is_scoped_per_app_not_shared(): void
    {
        $this->enableApi();
        api()->saveSecurity(['rate_limit' => 1]);

        $this->withHeaders($this->appHeaders())->getJson('/api/v1/site')->assertOk();
        $this->withHeaders($this->appHeaders())->getJson('/api/v1/site')->assertStatus(429);

        // A second, different app, from the same test client (so the same
        // IP): its own budget, untouched by the first app's use of theirs.
        [$other, $otherSecret] = ApiClient::issue('A second app');

        $this->withHeaders(['X-Api-Key' => $other->client_id, 'X-Api-Secret' => $otherSecret])
            ->getJson('/api/v1/site')
            ->assertOk();
    }

    public function test_the_auth_rate_limit_is_tighter_and_independent_of_the_general_one(): void
    {
        $this->enableApi();
        $user = $this->customer();

        // A generous general limit, a mean auth-specific one - so a
        // difference in behaviour between the two routes can only be the
        // dedicated limiter, not the shared one.
        api()->saveSecurity(['rate_limit' => 100, 'auth_rate_limit' => 2]);

        // The general limit alone would allow far more than this on /site.
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders($this->appHeaders())->getJson('/api/v1/site')->assertOk();
        }

        // But sign-in attempts are throttled after two, whether they succeed
        // or fail: the budget protects against being tried against at all.
        $this->withHeaders($this->appHeaders())
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnauthorized();

        $this->withHeaders($this->appHeaders())
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnauthorized();

        $this->withHeaders($this->appHeaders())
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-pass-1'])
            ->assertStatus(429)
            ->assertJsonPath('error', 'rate_limited');
    }

    public function test_admin_can_raise_or_lower_the_rate_limit(): void
    {
        $this->enableApi();

        $this->assertSame(60, api()->rateLimit(), 'The shipped default.');

        api()->saveSecurity(['rate_limit' => 500]);
        $this->assertSame(500, api()->rateLimit());

        // The screen's own bounds: the field is capped, so a stray zero or a
        // typo cannot switch the limiter off by accident.
        api()->saveSecurity(['rate_limit' => 0]);
        $this->assertSame(1, api()->rateLimit(), 'A non-positive limit is floored at 1, not switched off.');
    }
}
