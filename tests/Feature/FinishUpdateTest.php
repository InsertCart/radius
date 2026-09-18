<?php

namespace Tests\Feature;

use App\Cms\Updates\UpgradeState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A release copied in over FTP runs no migrations, so the admin panel has to
 * notice and offer to finish the job. The finalising itself is not exercised
 * here - it migrates, rewrites .env and clears caches for real.
 */
class FinishUpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $lock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lock = storage_path('framework/testing/installed-'.getmypid().'.json');
        File::ensureDirectoryExists(dirname($this->lock));
        config(['cms.install_lock' => $this->lock]);

        $this->get('/');
    }

    protected function tearDown(): void
    {
        File::delete($this->lock);

        parent::tearDown();
    }

    private function stamp(string $version): void
    {
        File::put($this->lock, json_encode(['version' => $version]));
    }

    private function admin(string $role = User::ROLE_ADMIN): User
    {
        return User::create([
            'name' => 'Staff', 'email' => $role.'@example.test', 'password' => Hash::make('x'),
            'role' => $role, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    public function test_a_site_on_its_own_version_is_left_alone(): void
    {
        $this->stamp(cms_version());

        $this->assertFalse(app(UpgradeState::class)->needsFinishing());
        $this->assertSame([], app(UpgradeState::class)->pendingMigrations());

        $this->actingAs($this->admin())->withSession(['auth.two_factor_confirmed' => true])
            ->get('/admin')->assertOk()->assertDontSee('This update is not finished');
    }

    public function test_files_newer_than_the_database_raise_the_banner(): void
    {
        $this->stamp('0.9.0');

        $this->assertTrue(app(UpgradeState::class)->needsFinishing());

        $this->actingAs($this->admin())->withSession(['auth.two_factor_confirmed' => true])
            ->get('/admin')
            ->assertOk()
            ->assertSee('This update is not finished')
            ->assertSee('Finish the update');
    }

    public function test_an_editor_is_told_to_fetch_an_administrator(): void
    {
        $this->stamp('0.9.0');

        $this->actingAs($this->admin(User::ROLE_EDITOR))->withSession(['auth.two_factor_confirmed' => true])
            ->get('/admin')
            ->assertOk()
            ->assertSee('Ask an administrator to finish it')
            ->assertDontSee('Finish the update');

        $this->post('/admin/updates/finish')->assertForbidden();
    }

    public function test_finishing_an_up_to_date_site_does_nothing(): void
    {
        $this->stamp(cms_version());

        $this->actingAs($this->admin())->withSession(['auth.two_factor_confirmed' => true])
            ->post('/admin/updates/finish')
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('status', 'The database is already up to date.');
    }

    public function test_a_site_with_no_lock_file_is_not_nagged(): void
    {
        $this->assertNull(app(UpgradeState::class)->recordedVersion());
        $this->assertFalse(app(UpgradeState::class)->versionChanged());
    }
}
