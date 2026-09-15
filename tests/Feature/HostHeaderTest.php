<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * trustProxies(at: '*') makes X-Forwarded-Host authoritative, and Laravel
 * builds absolute URLs (including the emailed reset link) from the request
 * root. This is unauthenticated.
 */
class HostHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/');

        User::create([
            'name' => 'Victim', 'email' => 'victim@example.test',
            'password' => Hash::make('correct-horse-1'), 'role' => User::ROLE_ADMIN,
            'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    public function test_reset_link_host_cannot_be_set_by_the_client(): void
    {
        $this->assertResetLinkIgnores(['HTTP_X_FORWARDED_HOST' => 'attacker.test']);
    }

    /**
     * The forwarded header is only half of it. On a server with a catch-all
     * virtual host - the normal shared-hosting arrangement - anyone can send a
     * plain Host header of their choosing straight to the application.
     */
    public function test_reset_link_host_ignores_a_spoofed_host_header(): void
    {
        $this->assertResetLinkIgnores(['HTTP_HOST' => 'attacker.test']);
    }

    private function assertResetLinkIgnores(array $server): void
    {
        Notification::fake();

        $this->withServerVariables($server)
            ->post('/forgot-password', ['email' => 'victim@example.test']);

        $link = null;
        Notification::assertSentTo(
            User::where('email', 'victim@example.test')->first(),
            function (ResetPassword $n, array $channels, $notifiable) use (&$link) {
                $mail = $n->toMail($notifiable);
                foreach ($mail->actionUrl ? [$mail->actionUrl] : [] as $u) {
                    $link = $u;
                }
                return true;
            }
        );

        $this->assertNotNull($link, 'No reset link was captured.');
        $this->assertStringNotContainsString(
            'attacker.test',
            (string) $link,
            "The emailed password-reset link points at a client-supplied host:\n  {$link}\nThe victim clicking it hands their reset token to the attacker."
        );
    }
}
