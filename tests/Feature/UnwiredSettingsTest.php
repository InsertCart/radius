<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Settings that were on the admin screen but did nothing: reCAPTCHA, the
 * WhatsApp number and the terms page.
 */
class UnwiredSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/');
        Notification::fake();
    }

    private function enableRecaptcha(): void
    {
        settings()->set('recaptcha_enabled', true);
        settings()->set('recaptcha_site_key', 'site-key-123');
        settings()->set('recaptcha_secret_key', 'secret-key-456');
    }

    private function registration(array $extra = []): array
    {
        return array_merge([
            'name' => 'Real Person', 'email' => 'person@example.test',
            'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1', 'terms' => '1',
        ], $extra);
    }

    public function test_recaptcha_off_changes_nothing(): void
    {
        Http::fake();

        $this->post('/register', $this->registration())->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'person@example.test']);
        Http::assertNothingSent();
        $this->get('/register')->assertDontSee('recaptcha/api.js', false);
    }

    public function test_recaptcha_script_is_added_only_to_pages_with_a_protected_form(): void
    {
        $this->enableRecaptcha();

        $this->get('/register')->assertOk()->assertSee('recaptcha/api.js?render=site-key-123', false);
        $this->get('/login')->assertOk()->assertDontSee('recaptcha/api.js', false);
    }

    public function test_recaptcha_refuses_a_missing_or_low_scoring_token(): void
    {
        $this->enableRecaptcha();
        Http::fake(['www.google.com/*' => Http::response(['success' => true, 'score' => 0.1])]);

        $this->from('/register')->post('/register', $this->registration())
            ->assertRedirect('/register')->assertSessionHas('error');

        $this->from('/register')->post('/register', $this->registration(['g-recaptcha-response' => 'bot']))
            ->assertRedirect('/register')->assertSessionHas('error');

        $this->assertDatabaseMissing('users', ['email' => 'person@example.test']);
    }

    public function test_recaptcha_accepts_a_human_score(): void
    {
        $this->enableRecaptcha();
        Http::fake(['www.google.com/*' => Http::response(['success' => true, 'score' => 0.9])]);

        $this->post('/register', $this->registration(['g-recaptcha-response' => 'human']));

        $this->assertDatabaseHas('users', ['email' => 'person@example.test']);
        Http::assertSent(fn ($request) => $request['secret'] === 'secret-key-456' && $request['response'] === 'human');
    }

    public function test_whatsapp_number_becomes_a_click_to_chat_link(): void
    {
        $this->assertSame('https://wa.me/919876543210', whatsapp_url('+91 98765-43210'));
        $this->assertSame('https://wa.me/919876543210', whatsapp_url('0091 98765 43210'));
        $this->assertSame('https://wa.me/message/ABC', whatsapp_url('https://wa.me/message/ABC'));
        $this->assertNull(whatsapp_url(''));

        settings()->set('social_whatsapp', '+91 98765 43210');
        $this->get('/')->assertSee('https://wa.me/919876543210', false);
    }

    public function test_terms_checkbox_links_to_the_terms_page_when_it_exists(): void
    {
        $this->get('/register')->assertDontSee('terms and conditions</a>', false);

        Page::create(['title' => 'Terms', 'slug' => 'terms', 'status' => 'published']);

        $this->get('/register')->assertSee('terms and conditions</a>', false);
    }
}
