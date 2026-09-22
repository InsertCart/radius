<?php

namespace Tests\Feature;

use App\Cms\Shop\CheckoutFields;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Settings -> Checkout: the shop choosing which fields checkout asks for and
 * which of them must be filled in.
 *
 * What matters is that the server holds to the choice, not only the form: a
 * hidden field is dropped even when something posts it, and a required one is
 * refused when blank whichever form it came from. One order per test, for the
 * reasons given in CheckoutAddressTest.
 */
class CheckoutFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'email' => 'asha@example.test',
            'phone' => '+91 98765 43210',
            'payment_gateway' => 'cod',
            'customer_note' => 'Ring the bell twice.',
            'billing' => [
                'name' => 'Asha Menon',
                'line1' => '14 Residency Road',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'postcode' => '560025',
                'country' => 'IN',
            ],
            'terms' => '1',
        ], $overrides);
    }

    private function fieldModes(array $modes): void
    {
        foreach ($modes as $field => $mode) {
            settings()->set('checkout_field_'.$field, $mode);
        }
    }

    public function test_a_shop_that_never_opens_the_settings_keeps_the_old_form(): void
    {
        $this->assertSame([
            'email' => 'required', 'name' => 'required',
            'phone' => 'optional', 'line1' => 'required', 'line2' => 'optional', 'city' => 'required',
            'state' => 'optional', 'postcode' => 'optional', 'country' => 'required', 'customer_note' => 'optional',
        ], app(CheckoutFields::class)->toArray());
    }

    public function test_a_hidden_field_is_left_off_the_form(): void
    {
        $this->fieldModes(['phone' => 'hidden', 'state' => 'hidden']);
        $this->fillCart();

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertDontSee('name="phone"', false)
            ->assertDontSee('name="billing[state]"', false)
            ->assertSee('name="billing[postcode]"', false);
    }

    public function test_a_hidden_field_is_dropped_even_when_it_is_posted(): void
    {
        $this->fieldModes(['phone' => 'hidden', 'customer_note' => 'hidden']);
        $this->fillCart();

        $this->post(route('checkout.store'), $this->payload())->assertRedirect();

        $order = Order::sole();
        $this->assertNull($order->phone);
        $this->assertNull($order->customer_note);
    }

    public function test_a_required_field_is_marked_required_on_the_form(): void
    {
        $this->fieldModes(['postcode' => 'required']);
        $this->fillCart();

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSeeInOrder(['name="billing[postcode]"', 'required'], false);
    }

    public function test_a_required_field_left_blank_is_refused(): void
    {
        $this->fieldModes(['postcode' => 'required', 'phone' => 'required']);
        $this->fillCart();

        $this->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->payload(['phone' => '', 'billing' => ['postcode' => '']]))
            ->assertSessionHasErrors(['phone', 'billing.postcode']);

        $this->assertSame(0, Order::count());
    }

    public function test_a_field_made_optional_can_be_left_blank(): void
    {
        $this->fieldModes(['city' => 'optional']);
        $this->fillCart();

        $this->post(route('checkout.store'), $this->payload(['billing' => ['city' => '']]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Order::sole()->billing_address['city']);
        // The book needs a whole address, so a cityless one stays out of it
        // rather than failing the order on a database constraint.
        $this->assertSame(0, $this->user->addresses()->count());
    }

    public function test_a_shop_can_hide_the_whole_address(): void
    {
        $this->fieldModes(array_fill_keys(CheckoutFields::ADDRESS, 'hidden'));
        $this->fillCart();

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertDontSee('name="billing[line1]"', false)
            ->assertDontSee('name="ship_to_different"', false);

        $this->post(route('checkout.store'), [
            'email' => 'asha@example.test',
            'payment_gateway' => 'cod',
            'billing' => ['name' => 'Asha Menon'],
            'terms' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Asha Menon', Order::sole()->billing_address['name']);
        $this->assertNull(Order::sole()->billing_address['line1']);
    }

    public function test_country_stays_required_while_selling_to_chosen_countries(): void
    {
        settings()->set('shop_selling_scope', 'selected');
        settings()->set('shop_selling_countries', ['IN']);
        $this->fieldModes(['country' => 'hidden']);
        $this->fillCart();

        $this->assertTrue(app(CheckoutFields::class)->requires('country'));

        $this->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->payload(['billing' => ['country' => '']]))
            ->assertSessionHasErrors('billing.country');
    }

    public function test_a_different_delivery_address_follows_the_same_rules_and_keeps_every_part(): void
    {
        $this->fieldModes(['postcode' => 'required']);
        $this->fillCart();

        $delivery = [
            'ship_to_different' => '1',
            'shipping' => [
                'name' => 'Ravi Menon', 'line1' => '2 MG Road', 'city' => 'Kochi',
                'state' => 'Kerala', 'postcode' => '', 'country' => 'IN',
            ],
        ];

        $this->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->payload($delivery))
            ->assertSessionHasErrors('shipping.postcode');

        $delivery['shipping']['postcode'] = '682001';

        $this->post(route('checkout.store'), $this->payload($delivery))->assertSessionHasNoErrors();

        $shipping = Order::sole()->shipping_address;
        $this->assertSame('682001', $shipping['postcode']);
        $this->assertSame('Kerala', $shipping['state']);
    }

    public function test_the_settings_screen_only_accepts_the_three_modes(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => Hash::make('secret-pass-1'),
            'role' => User::ROLE_ADMIN, 'status' => 'active', 'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.settings.edit', 'checkout'))
            ->put(route('admin.settings.update', 'checkout'), ['checkout_field_phone' => 'mandatory'])
            ->assertSessionHasErrors('checkout_field_phone');

        $this->actingAs($admin)
            ->put(route('admin.settings.update', 'checkout'), ['checkout_field_phone' => 'required'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(app(CheckoutFields::class)->requires('phone'));
    }
}
