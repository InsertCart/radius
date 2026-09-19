<?php

namespace Tests\Feature;

use App\Cms\Shop\AddressBook;
use App\Models\Address;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Checkout remembering the address, and the shop deciding which countries it
 * is willing to sell to.
 *
 * Each test signs in and places at most one order. Both limits are about the
 * test harness rather than the shop: a guest cart is keyed by session id,
 * which a test client does not carry between calls, and Laravel keeps one
 * controller instance per route for the life of the process, so a second POST
 * to checkout in the same test would reuse the cart the first one emptied.
 */
class CheckoutAddressTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Boots the CMS the way a first request does: modules and settings.
        $this->get('/');

        PaymentGateway::updateOrCreate(
            ['slug' => 'cod'],
            ['name' => 'Cash on delivery', 'is_enabled' => true, 'mode' => 'live']
        );

        $this->user = User::create([
            'name' => 'Asha Menon',
            'email' => 'asha@example.test',
            'password' => Hash::make('secret-pass-1'),
            'role' => User::ROLE_CUSTOMER,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($this->user);
    }

    private function fillCart(): void
    {
        $product = Product::create([
            'name' => 'Filter Coffee 500g', 'slug' => 'filter-coffee-500g', 'price' => 45000,
            'type' => 'simple', 'status' => 'published', 'manage_stock' => true, 'stock' => 10,
        ]);

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'email' => 'asha@example.test',
            'phone' => '+91 98765 43210',
            'payment_gateway' => 'cod',
            'billing' => [
                'name' => 'Asha Menon',
                'line1' => '14 Residency Road',
                'line2' => null,
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'postcode' => '560025',
                'country' => 'IN',
            ],
            'terms' => '1',
        ], $overrides);
    }

    /** An address already in the book, as an earlier order would have left it. */
    private function savedAddress(array $overrides = []): Address
    {
        return $this->user->addresses()->create(array_merge([
            'name' => 'Asha Menon', 'phone' => '+91 98765 43210',
            'line1' => '14 Residency Road', 'city' => 'Bengaluru',
            'state' => 'Karnataka', 'postcode' => '560025', 'country' => 'IN',
            'is_default_billing' => true, 'is_default_shipping' => true,
        ], $overrides));
    }

    // Remembering the address ---------------------------------------------

    public function test_the_first_checkout_saves_the_address_to_the_account(): void
    {
        $this->fillCart();

        $this->post(route('checkout.store'), $this->payload())->assertRedirect();

        $saved = $this->user->addresses()->first();

        $this->assertNotNull($saved, 'The address used at checkout should be kept.');
        $this->assertSame('14 Residency Road', $saved->line1);
        $this->assertSame('IN', $saved->country);
        $this->assertSame('+91 98765 43210', $saved->phone);
        $this->assertTrue($saved->is_default_billing);
    }

    public function test_checkout_opens_with_the_saved_address_filled_in(): void
    {
        $this->savedAddress();
        $this->fillCart();

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('value="14 Residency Road"', false)
            ->assertSee('value="560025"', false)
            ->assertSee('<option value="IN" selected>India</option>', false);
    }

    public function test_another_saved_address_can_be_chosen_from_the_form(): void
    {
        $this->savedAddress();
        $other = $this->savedAddress([
            'label' => 'Office', 'line1' => '9 Brigade Road', 'postcode' => '560001',
            'is_default_billing' => false, 'is_default_shipping' => false,
        ]);
        $this->fillCart();

        $this->get(route('checkout.index', ['address' => $other->id]))
            ->assertOk()
            ->assertSee('value="9 Brigade Road"', false);
    }

    public function test_one_customer_cannot_prefill_from_another_customers_address(): void
    {
        $stranger = User::create([
            'name' => 'Someone Else', 'email' => 'else@example.test',
            'password' => Hash::make('secret-pass-1'), 'role' => User::ROLE_CUSTOMER,
            'status' => 'active', 'email_verified_at' => now(),
        ]);
        $theirs = $stranger->addresses()->create([
            'name' => 'Someone Else', 'line1' => '1 Private Lane',
            'city' => 'Kochi', 'country' => 'IN',
        ]);

        $this->fillCart();

        $this->get(route('checkout.index', ['address' => $theirs->id]))
            ->assertOk()
            ->assertDontSee('1 Private Lane');
    }

    public function test_a_one_off_address_does_not_join_the_book_on_its_own(): void
    {
        $this->savedAddress();
        $this->fillCart();

        // Delivering to a friend once should not clutter their account.
        $this->post(route('checkout.store'), $this->payload([
            'billing' => ['line1' => '9 Brigade Road', 'postcode' => '560001'],
        ]))->assertRedirect();

        $this->assertSame(1, $this->user->addresses()->count());
    }

    public function test_ticking_the_box_adds_the_address_and_makes_it_the_default(): void
    {
        $this->savedAddress();
        $this->fillCart();

        $this->post(route('checkout.store'), $this->payload([
            'billing' => ['line1' => '9 Brigade Road', 'postcode' => '560001'],
            'save_address' => '1',
        ]))->assertRedirect();

        $this->assertSame(2, $this->user->addresses()->count());
        $this->assertSame('9 Brigade Road', $this->user->defaultAddress('billing')->line1);
        $this->assertSame(1, Address::where('is_default_billing', true)->count());
    }

    public function test_the_same_address_is_never_saved_twice(): void
    {
        $this->savedAddress();
        $this->fillCart();

        $this->post(route('checkout.store'), $this->payload(['save_address' => '1']))
            ->assertRedirect();

        $this->assertSame(1, $this->user->addresses()->count());
    }

    public function test_an_address_is_not_remembered_when_the_setting_is_off(): void
    {
        settings()->set('shop_save_addresses', false);
        $this->fillCart();

        $this->post(route('checkout.store'), $this->payload())->assertRedirect();

        $this->assertSame(0, $this->user->addresses()->count());
    }

    public function test_the_address_book_is_not_there_when_the_setting_is_off(): void
    {
        settings()->set('shop_save_addresses', false);

        $this->get(route('account.addresses'))->assertNotFound();
        $this->post(route('account.addresses.store'), [
            'name' => 'Asha Menon', 'line1' => '14 Residency Road',
            'city' => 'Bengaluru', 'country' => 'IN',
        ])->assertNotFound();

        $this->get(route('account.dashboard'))->assertOk()->assertDontSee('Addresses');
    }

    public function test_the_order_keeps_its_own_copy_of_the_address(): void
    {
        $this->fillCart();
        $this->post(route('checkout.store'), $this->payload());

        // Editing the book entry afterwards must not rewrite a past order.
        $this->user->addresses()->first()->update(['line1' => 'Somewhere else entirely']);

        $this->assertSame('14 Residency Road', data_get(Order::first()->billing_address, 'line1'));
    }

    public function test_a_guest_address_is_remembered_in_their_session(): void
    {
        $book = app(AddressBook::class);

        $book->remember(null, [
            'name' => 'Guest Person', 'line1' => '22 Church Street',
            'city' => 'Bengaluru', 'country' => 'India',
        ]);

        $prefill = $book->prefill(null);

        $this->assertSame('22 Church Street', $prefill['line1']);
        // Typed as a name, stored as the code it matches.
        $this->assertSame('IN', $prefill['country']);
    }

    // The address book ----------------------------------------------------

    public function test_a_customer_manages_their_own_book_and_nobody_elses(): void
    {
        $stranger = User::create([
            'name' => 'Someone Else', 'email' => 'else@example.test',
            'password' => Hash::make('secret-pass-1'), 'role' => User::ROLE_CUSTOMER,
            'status' => 'active', 'email_verified_at' => now(),
        ]);
        $theirs = $stranger->addresses()->create([
            'name' => 'Someone Else', 'line1' => '1 Private Lane',
            'city' => 'Kochi', 'country' => 'IN',
        ]);

        $this->post(route('account.addresses.store'), [
            'label' => 'Home', 'name' => 'Asha Menon', 'line1' => '14 Residency Road',
            'city' => 'Bengaluru', 'country' => 'IN',
        ])->assertRedirect(route('account.addresses'));

        $this->assertSame(1, $this->user->addresses()->count());
        $this->assertTrue($this->user->addresses()->first()->is_default_billing);

        $this->delete(route('account.addresses.destroy', $theirs))->assertForbidden();
        $this->assertDatabaseHas('addresses', ['id' => $theirs->id]);
    }

    public function test_only_one_address_is_ever_the_default(): void
    {
        $first = $this->savedAddress();
        $second = $this->savedAddress([
            'line1' => 'Two', 'is_default_billing' => false, 'is_default_shipping' => false,
        ]);

        $this->patch(route('account.addresses.default', $second))->assertRedirect();

        $this->assertFalse($first->fresh()->is_default_billing);
        $this->assertTrue($second->fresh()->is_default_billing);
        $this->assertSame(1, Address::where('is_default_billing', true)->count());
    }

    public function test_removing_the_default_promotes_whatever_is_left(): void
    {
        $first = $this->savedAddress();
        $second = $this->savedAddress([
            'line1' => 'Two', 'is_default_billing' => false, 'is_default_shipping' => false,
        ]);

        $this->delete(route('account.addresses.destroy', $first))->assertRedirect();

        $this->assertTrue($second->fresh()->is_default_billing);
    }

    // Countries we sell to ------------------------------------------------

    public function test_worldwide_is_the_default_and_accepts_any_country(): void
    {
        $this->fillCart();

        $this->post(route('checkout.store'), $this->payload([
            'billing' => ['country' => 'BR'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Order::count());
    }

    public function test_an_order_outside_the_chosen_countries_is_refused(): void
    {
        settings()->set('shop_selling_scope', 'selected');
        settings()->set('shop_selling_countries', ['IN', 'AE']);
        $this->fillCart();

        $this->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->payload([
                'billing' => ['country' => 'BR'],
            ]))
            ->assertSessionHasErrors('billing.country');

        $this->assertSame(0, Order::count());
    }

    public function test_a_delivery_address_outside_the_chosen_countries_is_refused(): void
    {
        settings()->set('shop_selling_scope', 'selected');
        settings()->set('shop_selling_countries', ['IN']);
        $this->fillCart();

        $this->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->payload([
                'ship_to_different' => '1',
                'shipping' => [
                    'name' => 'A Friend', 'line1' => 'Rua Augusta 100',
                    'city' => 'Sao Paulo', 'country' => 'BR',
                ],
            ]))
            ->assertSessionHasErrors('shipping.country');

        $this->assertSame(0, Order::count());
    }

    public function test_a_chosen_country_still_goes_through(): void
    {
        settings()->set('shop_selling_scope', 'selected');
        settings()->set('shop_selling_countries', ['IN', 'AE']);
        $this->fillCart();

        $this->post(route('checkout.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(1, Order::count());
    }

    public function test_checkout_only_offers_the_countries_the_shop_sells_to(): void
    {
        settings()->set('shop_selling_scope', 'selected');
        settings()->set('shop_selling_countries', ['IN', 'AE']);
        $this->fillCart();

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('United Arab Emirates')
            ->assertDontSee('>Brazil<', false);
    }

    public function test_choosing_no_countries_keeps_the_shop_worldwide(): void
    {
        // Otherwise switching the setting on before ticking anything would
        // quietly stop the shop taking orders at all.
        settings()->set('shop_selling_scope', 'selected');
        settings()->set('shop_selling_countries', []);
        $this->fillCart();

        $this->post(route('checkout.store'), $this->payload([
            'billing' => ['country' => 'BR'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Order::count());
    }

    public function test_a_country_is_stored_as_its_code_and_shown_by_name(): void
    {
        $this->fillCart();
        $this->post(route('checkout.store'), $this->payload());

        $order = Order::first();

        $this->assertSame('IN', data_get($order->billing_address, 'country'));

        // The order carries the phone number in its own column, so the address
        // itself reads as an address.
        $this->assertSame(
            'Asha Menon, 14 Residency Road, Bengaluru, Karnataka, 560025, India',
            format_address($order->billing_address)
        );
    }

    public function test_an_address_written_before_the_country_list_still_reads(): void
    {
        $this->assertSame('United States', country_name('us'));
        $this->assertSame('Atlantis', country_name('Atlantis'));
        $this->assertSame('1 Old Road, Somewhereshire', format_address([
            'line1' => '1 Old Road', 'country' => 'Somewhereshire',
        ]));
    }
}
