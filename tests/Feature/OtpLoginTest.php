<?php

namespace Tests\Feature;

use App\Cms\Sms\SmsManager;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtpLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/');
    }

    private function enable(): void
    {
        modules()->sync();
        modules()->enable('sms');
        settings()->set('sms_enabled', true);
        settings()->set('sms_driver', 'log');
        settings()->set('sms_otp_login', true);
        app()->forgetInstance(SmsManager::class);
    }

    private function user(string $role = User::ROLE_CUSTOMER, string $phone = '+91 98765 43210'): User
    {
        return User::create([
            'name' => 'Phone Person', 'email' => $role.'@example.test', 'phone' => $phone,
            'password' => Hash::make('x'), 'role' => $role, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function lastCode(): string
    {
        preg_match('/^(\d{6})/', SmsLog::latest('id')->value('message'), $m);

        return $m[1];
    }

    public function test_hidden_while_switched_off(): void
    {
        $this->get('/login/code')->assertNotFound();
        $this->get('/login')->assertDontSee('Sign in with a code');
    }

    public function test_a_customer_signs_in_with_the_texted_code(): void
    {
        $this->enable();
        $user = $this->user();

        $this->get('/login')->assertSee('Sign in with a code');

        // Typed differently from how it was saved.
        $this->post('/login/code/send', ['phone' => '0091-9876543210'])->assertRedirect('/login/code');
        $this->assertSame(1, SmsLog::count());

        $this->post('/login/code/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/login/code/verify', ['code' => $this->lastCode()])->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_unknown_numbers_and_staff_get_the_same_reply_and_no_text(): void
    {
        $this->enable();
        $this->user(User::ROLE_ADMIN, '+44 7700 900123');

        foreach (['+44 7700 900123', '+1 555 010 9999'] as $phone) {
            $this->flushSession();
            $this->post('/login/code/send', ['phone' => $phone])
                ->assertRedirect('/login/code')
                ->assertSessionHas('status', 'If that number belongs to an account, we have texted it a sign-in code.');
            $this->get('/login/code')->assertSee('Enter the code sent to');
            $this->post('/login/code/verify', ['code' => '123456'])->assertSessionHasErrors('code');
        }

        $this->assertSame(0, SmsLog::count());
        $this->assertGuest();
    }

    public function test_a_number_shared_by_two_accounts_signs_nobody_in(): void
    {
        $this->enable();
        $this->user(User::ROLE_CUSTOMER, '9876543210');
        User::create([
            'name' => 'Twin', 'email' => 'twin@example.test', 'phone' => '+91 9876543210',
            'password' => Hash::make('x'), 'role' => User::ROLE_CUSTOMER, 'status' => 'active',
        ]);

        $this->post('/login/code/send', ['phone' => '9876543210']);

        $this->assertSame(0, SmsLog::count());
    }
}
