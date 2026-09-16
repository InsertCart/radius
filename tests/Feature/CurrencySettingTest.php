<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Changing the shop currency has to change what prices actually look like.
 *
 * The code and the symbol are stored separately, so picking "Indian Rupee"
 * used to leave every price rendering in dollars. These pin the symbol to the
 * currency without trampling one an owner has deliberately customised.
 */
class CurrencySettingTest extends TestCase
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

    private function saveShop(array $overrides = []): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update', 'shop'), array_merge([
                'shop_currency' => 'USD',
                'shop_currency_symbol' => '$',
                'shop_currency_position' => 'before',
                'shop_tax_rate' => 0,
                'shop_shipping_flat' => 0,
                'shop_free_shipping_over' => 0,
                'shop_low_stock_threshold' => 5,
                'shop_order_prefix' => 'ORD-',
                'shop_terms_page' => 'terms',
                'shop_download_limit' => 5,
                'shop_download_days' => 0,
            ], $overrides))
            ->assertRedirect();
    }

    public function test_the_shop_settings_screen_offers_a_currency_picker(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.edit', 'shop'))
            ->assertOk()
            ->assertSee('shop_currency', false)
            ->assertSee('Indian Rupee (INR)');
    }

    public function test_switching_currency_brings_its_symbol_along(): void
    {
        $this->saveShop(['shop_currency' => 'INR']);

        $this->assertSame('INR', setting('shop_currency'));
        $this->assertSame('₹', setting('shop_currency_symbol'));
        $this->assertSame('₹1,250.00', money(125000));
    }

    public function test_a_customised_symbol_survives_a_currency_change(): void
    {
        $this->saveShop(['shop_currency' => 'INR', 'shop_currency_symbol' => 'Rs.']);

        $this->assertSame('INR', setting('shop_currency'));
        $this->assertSame('Rs.', setting('shop_currency_symbol'));
    }

    public function test_the_symbol_can_be_edited_without_the_currency_changing(): void
    {
        $this->saveShop(['shop_currency' => 'USD', 'shop_currency_symbol' => 'US$']);

        $this->assertSame('US$', setting('shop_currency_symbol'));
    }
}
