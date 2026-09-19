<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Settings -> Shop -> the countries the shop sells to: a multi-select, which
 * is the first settings field whose value is a list rather than a string.
 */
class SellingCountriesSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/');
        $this->actingAs($this->admin());
    }

    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'owner@x.test'], [
            'name' => 'Owner', 'password' => Hash::make('password-123'),
            'role' => User::ROLE_ADMIN, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    /** Every shop field, since the form saves the whole group at once. */
    private function shopForm(array $overrides = []): array
    {
        return array_merge([
            'shop_currency' => 'INR',
            'shop_currency_symbol' => '₹',
            'shop_currency_position' => 'before',
            'shop_selling_scope' => 'world',
            'shop_tax_rate' => 0,
            'shop_shipping_flat' => 0,
            'shop_free_shipping_over' => 0,
            'shop_low_stock_threshold' => 5,
            'shop_order_prefix' => 'ORD-',
            'shop_terms_page' => 'terms',
            'shop_download_limit' => 5,
            'shop_download_days' => 0,
        ], $overrides);
    }

    public function test_the_countries_field_is_on_the_shop_settings_screen(): void
    {
        $this->get(route('admin.settings.edit', 'shop'))
            ->assertOk()
            ->assertSee('Countries you sell to')
            ->assertSee('multiSelect(', false)
            ->assertSee('United Arab Emirates');
    }

    public function test_a_chosen_list_is_saved_and_read_back_as_a_list(): void
    {
        $this->put(route('admin.settings.update', 'shop'), $this->shopForm([
            'shop_selling_scope' => 'selected',
            'shop_selling_countries' => ['IN', 'AE', 'GB'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['IN', 'AE', 'GB'], setting('shop_selling_countries'));
        $this->assertSame('selected', setting('shop_selling_scope'));
    }

    public function test_unticking_everything_clears_the_list(): void
    {
        settings()->set('shop_selling_countries', ['IN']);

        // An untouched multi-select posts no input at all, which has to read
        // as "nothing chosen" rather than "leave it alone".
        $this->put(route('admin.settings.update', 'shop'), $this->shopForm())
            ->assertSessionHasNoErrors();

        $this->assertSame([], setting('shop_selling_countries'));
    }

    public function test_a_country_code_that_is_not_real_is_rejected(): void
    {
        $this->from(route('admin.settings.edit', 'shop'))
            ->put(route('admin.settings.update', 'shop'), $this->shopForm([
                'shop_selling_countries' => ['IN', 'XX'],
            ]))
            ->assertSessionHasErrors('shop_selling_countries.1');
    }
}
