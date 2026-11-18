<?php

namespace Tests\Feature;

use App\Cms\Support\PhpRequirement;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What has to stay true for the move to the next Laravel version to be safe for
 * sites already running.
 */
class LaravelUpgradeReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_are_told_about_the_coming_php_requirement(): void
    {
        $lock = storage_path('framework/testing/installed-readiness-'.getmypid().'.json');
        File::ensureDirectoryExists(dirname($lock));
        File::put($lock, json_encode(['version' => cms_version()]));
        config(['cms.install_lock' => $lock]);

        $admin = User::create([
            'name' => 'Staff', 'email' => 'admin@example.test', 'password' => Hash::make('x'),
            'role' => User::ROLE_ADMIN, 'status' => 'active', 'email_verified_at' => now(),
        ]);

        try {
            $response = $this->actingAs($admin)
                ->withSession(['auth.two_factor_confirmed' => true])
                ->get('/admin/updates')
                ->assertOk();

            PhpRequirement::meetsUpcoming()
                ? $response->assertDontSee('Plan your PHP upgrade')
                : $response->assertSee('Plan your PHP upgrade');
        } finally {
            File::delete($lock);
        }
    }

    /** @return array<string, array{string}> */
    public static function paymentCallbacks(): array
    {
        return [
            'customer returning' => ['checkout.return'],
            'customer cancelling' => ['checkout.cancel'],
            'provider webhook' => ['checkout.webhook'],
        ];
    }

    /**
     * Asserted on the route itself because a request cannot show it: Laravel
     * skips forgery protection entirely while running tests.
     */
    #[DataProvider('paymentCallbacks')]
    public function test_payment_callbacks_skip_forgery_protection_under_either_name(string $name): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "The {$name} route is missing.");
        $this->assertContains(ValidateCsrfToken::class, $route->excludedMiddleware());
        $this->assertContains(PreventRequestForgery::class, $route->excludedMiddleware());
        $this->assertNotContains(ValidateCsrfToken::class, Route::gatherRouteMiddleware($route));
    }

    public function test_every_statement_of_the_php_minimum_agrees(): void
    {
        $composer = json_decode(File::get(base_path('composer.json')), true);
        $minimum = PhpRequirement::short(PhpRequirement::MINIMUM);

        $this->assertSame('^'.$minimum, $composer['require']['php']);
        $this->assertSame(PhpRequirement::MINIMUM, $composer['config']['platform']['php']);
        $this->assertStringContainsString(
            "php-version: '{$minimum}'",
            File::get(base_path('.github/workflows/release.yml')),
        );
    }

    public function test_only_servers_behind_the_coming_requirement_are_warned(): void
    {
        $this->assertStringContainsString(
            'PHP '.PhpRequirement::short(PhpRequirement::UPCOMING).' or newer',
            (string) PhpRequirement::upcomingNotice('8.2.12'),
        );

        $this->assertNull(PhpRequirement::upcomingNotice(PhpRequirement::UPCOMING));
    }
}
