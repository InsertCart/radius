<?php

namespace Tests\Feature;

use App\Cms\Support\TwoFactorService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * An attacker who has only the victim's PASSWORD (phishing, reuse, a dump)
 * must be stopped by the TOTP challenge.
 *
 * The bypass this guards against: a session that has passed the password step
 * but not the challenge re-enrols a fresh secret, overwriting the one it could
 * not satisfy, and confirms it with its own authenticator.
 */
class TwoFactorBypassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');
    }

    public function test_password_alone_cannot_defeat_two_factor(): void
    {
        $svc = app(TwoFactorService::class);

        $victim = User::create([
            'name' => 'Victim Admin', 'email' => 'victim@example.test',
            'password' => Hash::make('leaked-password-1'), 'role' => User::ROLE_ADMIN,
            'status' => 'active', 'email_verified_at' => now(),
        ]);

        $original = $svc->beginEnrolment($victim);
        $this->assertTrue($svc->confirm($victim->fresh(), (new Google2FA)->getCurrentOtp($original)));
        $this->assertTrue($victim->fresh()->hasTwoFactorEnabled(), 'precondition: 2FA is on');

        // --- Attacker has the password only. Step one of login. ---
        $this->post('/admin/login', [
            'email' => 'victim@example.test',
            'password' => 'leaked-password-1',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->assertTrue($this->isAuthenticated(), 'precondition: password step signs the session in');
        $this->get('/admin')->assertRedirect(route('two-factor.challenge'));

        // --- The attack: try to re-enrol a fresh secret mid-challenge. ---
        $this->post('/two-factor/enable');

        $this->assertSame(
            $original,
            $victim->fresh()->two_factor_secret,
            "A half-authenticated session replaced the victim's TOTP secret."
        );

        // Even if a code for some other secret is offered, it must not confirm.
        $this->post('/two-factor/confirm', [
            'code' => (new Google2FA)->getCurrentOtp($svc->generateSecret()),
        ]);

        $this->get('/admin')->assertRedirect(route('two-factor.challenge'));
        $this->assertFalse(
            (bool) session('auth.two_factor_confirmed'),
            'The session was marked two-factor-confirmed without answering the challenge.'
        );
    }

    public function test_a_user_without_two_factor_can_still_enrol(): void
    {
        // The fix must not break ordinary first-time enrolment.
        $user = User::create([
            'name' => 'New Admin', 'email' => 'new@example.test',
            'password' => Hash::make('password-123'), 'role' => User::ROLE_ADMIN,
            'status' => 'active', 'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->post('/two-factor/enable')
            ->assertRedirect(route('two-factor.setup'));

        $secret = $user->fresh()->two_factor_secret;
        $this->assertNotEmpty($secret, 'A user with no second factor could not start enrolment.');

        $this->actingAs($user->fresh())->post('/two-factor/confirm', [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ]);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled(), 'Enrolment could not be completed.');
    }

    public function test_reenrolment_is_possible_with_the_password(): void
    {
        // Losing a phone must still be recoverable: re-enrol, but prove the
        // password first - the same bar as switching 2FA off.
        $svc = app(TwoFactorService::class);

        $user = User::create([
            'name' => 'Admin', 'email' => 'a@example.test',
            'password' => Hash::make('my-password-9'), 'role' => User::ROLE_ADMIN,
            'status' => 'active', 'email_verified_at' => now(),
        ]);

        $original = $svc->beginEnrolment($user);
        $svc->confirm($user->fresh(), (new Google2FA)->getCurrentOtp($original));

        // Fully signed in (challenge answered).
        $this->actingAs($user->fresh())->withSession(['auth.two_factor_confirmed' => true])
            ->post('/two-factor/enable', ['current_password' => 'my-password-9'])
            ->assertRedirect(route('two-factor.setup'));

        $this->assertNotSame($original, $user->fresh()->two_factor_secret,
            'A signed-in user who proved their password could not re-enrol a new device.');
    }
}
