<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A theme is Blade, and Blade compiles to PHP and executes, so uploading or
 * activating one is equivalent to deploying code. Only an administrator may.
 */
class ThemeUploadAuthzTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');
    }

    private function editor(): User
    {
        return User::create([
            'name' => 'Ed', 'email' => 'ed@x.test', 'password' => Hash::make('password123'),
            'role' => User::ROLE_EDITOR, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    public function test_editor_cannot_upload_a_theme(): void
    {
        $bogus = UploadedFile::fake()->createWithContent('theme.zip', 'PK-not-really');

        $this->actingAs($this->editor())
            ->post('/admin/themes/upload', ['theme' => $bogus])
            ->assertForbidden();
    }

    public function test_editor_cannot_activate_or_delete_a_theme(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->post('/admin/themes/default/activate')->assertForbidden();
        $this->actingAs($editor)->delete('/admin/themes/default')->assertForbidden();
    }

    public function test_editor_cannot_reach_payment_credentials_or_modules(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->get('/admin/payments')->assertForbidden();
        $this->actingAs($editor)->get('/admin/modules')->assertForbidden();
    }
}
