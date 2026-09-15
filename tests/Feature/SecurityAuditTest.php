<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Probes for privilege escalation from the "editor" role.
 *
 * An editor is a lower-privileged staff account: the route file's own comment
 * says "Settings screens are admin-only; editors stop at content."
 */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;


    /**
     * The first request in a test process resolves against a stale base path
     * (RootRewrite::align runs at boot), so it 404s regardless of routing.
     * Burn one request before every assertion so results are meaningful.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');
    }

    private function editor(): User
    {
        return User::create([
            'name' => 'Ed Editor',
            'email' => 'editor@example.test',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_EDITOR,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Root Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('adminpassword123'),
            'role' => User::ROLE_ADMIN,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function p(string $path): string
    {
        return '/'.trim(config('cms.admin_prefix', 'admin'), '/').'/'.ltrim($path, '/');
    }

    public function test_editor_is_blocked_from_settings_as_documented(): void
    {
        // Control: proves the editor role really is meant to be limited.
        $this->actingAs($this->editor())
            ->get($this->p('settings'))
            ->assertForbidden();
    }

    public function test_editor_cannot_create_an_admin_account(): void
    {
        $this->actingAs($this->editor())
            ->post($this->p('users'), [
                'name' => 'Backdoor',
                'email' => 'backdoor@example.test',
                'role' => User::ROLE_ADMIN,
                'status' => 'active',
                'password' => 'SuperSecret123',
                'password_confirmation' => 'SuperSecret123',
            ]);

        $this->assertNull(
            User::where('email', 'backdoor@example.test')->where('role', User::ROLE_ADMIN)->first(),
            'An EDITOR successfully created a new ADMIN account.'
        );
    }

    public function test_editor_cannot_change_an_admins_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($this->editor())
            ->put($this->p('users/'.$admin->id), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => User::ROLE_ADMIN,
                'status' => 'active',
                'password' => 'AttackerPick123',
                'password_confirmation' => 'AttackerPick123',
            ]);

        $this->assertFalse(
            Hash::check('AttackerPick123', $admin->fresh()->password),
            "An EDITOR successfully reset the ADMIN's password."
        );
    }

    public function test_editor_cannot_promote_themselves_to_admin(): void
    {
        $editor = $this->editor();
        $other = User::create([
            'name' => 'Second Editor', 'email' => 'e2@example.test',
            'password' => Hash::make('password123'), 'role' => User::ROLE_EDITOR,
            'status' => 'active', 'email_verified_at' => now(),
        ]);

        // Self-promotion is blocked by an explicit guard, so escalate via a
        // second editor account and then use that one.
        $this->actingAs($editor)->put($this->p('users/'.$other->id), [
            'name' => $other->name, 'email' => $other->email,
            'role' => User::ROLE_ADMIN, 'status' => 'active',
        ]);

        $this->assertNotSame(
            User::ROLE_ADMIN, $other->fresh()->role,
            'An EDITOR promoted another account to ADMIN.'
        );
    }

    public function test_editor_cannot_strip_two_factor_from_an_admin(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($this->editor())
            ->delete($this->p('users/'.$admin->id.'/two-factor'));

        $this->assertTrue(
            in_array($response->status(), [403, 404], true),
            "An EDITOR was allowed to clear the ADMIN's two-factor authentication (HTTP {$response->status()})."
        );
    }

    public function test_editor_cannot_delete_an_admin(): void
    {
        $this->admin();
        $second = User::create([
            'name' => 'Admin Two', 'email' => 'admin2@example.test',
            'password' => Hash::make('adminpassword123'), 'role' => User::ROLE_ADMIN,
            'status' => 'active', 'email_verified_at' => now(),
        ]);

        $this->actingAs($this->editor())->delete($this->p('users/'.$second->id));

        $this->assertNotNull(
            User::find($second->id),
            'An EDITOR deleted an ADMIN account.'
        );
    }
}
