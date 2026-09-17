<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Public sign-up, with and without "Require email verification". Registration
 * used to die with a stack trace because the verification link's route did not
 * exist and email_verified_at was silently dropped on create.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/');
    }

    private function register(): \Illuminate\Testing\TestResponse
    {
        return $this->post('/register', [
            'name' => 'New Customer',
            'email' => 'new@example.test',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'terms' => '1',
        ]);
    }

    public function test_without_verification_the_account_is_verified_and_no_email_is_sent(): void
    {
        Notification::fake();
        settings()->set('email_verification', false);

        $this->register()->assertRedirect(route('account.dashboard'));

        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        Notification::assertNothingSent();
    }

    public function test_with_verification_the_customer_is_held_until_they_click_the_link(): void
    {
        Notification::fake();
        settings()->set('email_verification', true);

        $this->register()->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get('/account')->assertRedirect(route('verification.notice'));
        $this->get('/email/verify')->assertOk();

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);

        $this->get($link)->assertRedirect(route('account.dashboard'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get('/account')->assertOk();
    }

    public function test_a_tampered_verification_link_is_rejected(): void
    {
        settings()->set('email_verification', true);
        Notification::fake();
        $this->register();

        $user = User::where('email', 'new@example.test')->firstOrFail();

        $this->get("/email/verify/{$user->id}/".sha1($user->email))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_errors_show_the_generic_page_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        // Rendered through the real handler: a test route would be shadowed by
        // the pages catch-all.
        $response = app(ExceptionHandler::class)->render(
            Request::create('/register', 'POST'),
            new \RuntimeException('secret internals'),
        );

        $this->createTestResponse($response, null)
            ->assertStatus(500)
            ->assertSee('Something went wrong')
            ->assertDontSee('secret internals')
            ->assertDontSee('RuntimeException');
    }
}
