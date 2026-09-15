<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * bootstrap/app.php calls trustProxies(at: '*'), so Request::ip() is whatever
 * the client puts in X-Forwarded-For. The admin login lockout key is
 * "admin-login|<email>|<ip>".
 */
class ThrottleBypassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');

        User::create([
            'name' => 'Root', 'email' => 'admin@example.test',
            'password' => Hash::make('the-real-password'), 'role' => User::ROLE_ADMIN,
            'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function guess(string $ip): int
    {
        return $this->withServerVariables(['HTTP_X_FORWARDED_FOR' => $ip])
            ->post('/admin/login', [
                'email' => 'admin@example.test',
                'password' => 'wrong-guess-'.uniqid(),
            ])->status();
    }

    public function test_ip_is_client_controlled(): void
    {
        $seen = $this->withServerVariables(['HTTP_X_FORWARDED_FOR' => '203.0.113.99'])
            ->get('/admin')->baseResponse;

        $ip = $this->app['request']->ip();

        $this->assertNotSame('203.0.113.99', $ip,
            "Request::ip() returned the client-supplied X-Forwarded-For value ({$ip}), so every IP-keyed control is attacker-controlled.");
    }

    public function test_lockout_holds_from_a_single_ip(): void
    {
        // Baseline: the lockout must engage when the IP is constant.
        $errors = 0;
        for ($i = 0; $i < 12; $i++) {
            $this->guess('198.51.100.7');
            $body = session('errors');
            if ($body && str_contains((string) $body->first('email'), 'Too many attempts')) {
                $errors++;
            }
        }
        $this->assertGreaterThan(0, $errors, 'Lockout never engaged even from one fixed IP.');
    }

    public function test_lockout_cannot_be_bypassed_by_rotating_xff(): void
    {
        $lockedOut = false;

        for ($i = 0; $i < 40; $i++) {
            $this->guess('10.9.'.intdiv($i, 250).'.'.($i % 250 + 1));
            $e = session('errors');
            if ($e && str_contains((string) $e->first('email'), 'Too many attempts')) {
                $lockedOut = true;
                break;
            }
        }

        $this->assertTrue($lockedOut,
            '40 consecutive failed admin password guesses with a rotating X-Forwarded-For header never triggered the lockout.');
    }
}
