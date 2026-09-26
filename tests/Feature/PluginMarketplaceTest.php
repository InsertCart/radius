<?php

namespace Tests\Feature;

use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/**
 * The plugin directory installs code, so these pin what must never loosen:
 * only the exact archive the catalogue published is installed, paid plugins
 * are never downloaded, a hand-installed plugin is never replaced, and turning
 * plugin uploads off turns the directory off too.
 */
class PluginMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private const CATALOGUE = 'https://plugins.example.com/modules.json';

    private string $pluginsPath;

    private array $published = ['format' => 1, 'items' => []];

    /** @var array<string, string> download URL => archive bytes */
    private array $archives = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginsPath = storage_path('framework/testing/plugins-market-'.Str::random(6));
        File::ensureDirectoryExists($this->pluginsPath);

        config([
            'cms.plugins.path' => $this->pluginsPath,
            'marketplace.enabled' => true,
            'marketplace.plugins_catalogue_url' => self::CATALOGUE,
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if ($url === self::CATALOGUE) {
                return Http::response($this->published);
            }

            return isset($this->archives[$url]) ? Http::response($this->archives[$url]) : Http::response('missing', 404);
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pluginsPath);

        parent::tearDown();
    }

    public function test_a_free_plugin_installs_from_the_directory_switched_off(): void
    {
        $this->publish('1.0.0');

        $this->asAdmin()->post(route('admin.plugins.marketplace.install', 'dir-notes'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertSessionHas('status');

        $plugin = Plugin::where('slug', 'dir-notes')->firstOrFail();
        $this->assertFalse($plugin->enabled);
        $this->assertTrue($plugin->isFromMarketplace());
        $this->assertFileExists($this->pluginsPath.'/dir-notes/plugin.json');
    }

    public function test_an_archive_that_does_not_match_its_checksum_is_refused(): void
    {
        $this->publish('1.0.0', sha256: str_repeat('a', 64));

        $this->asAdmin()->post(route('admin.plugins.marketplace.install', 'dir-notes'))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'checksum'));

        $this->assertNull(Plugin::where('slug', 'dir-notes')->first());
        $this->assertDirectoryDoesNotExist($this->pluginsPath.'/dir-notes');
    }

    public function test_an_archive_that_installs_a_different_plugin_than_listed_is_refused(): void
    {
        $this->publish('1.0.0', listedSlug: 'something-else');

        $this->asAdmin()->post(route('admin.plugins.marketplace.install', 'something-else'))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'listed as'));

        $this->assertDirectoryDoesNotExist($this->pluginsPath.'/dir-notes');
    }

    public function test_a_paid_plugin_is_never_downloaded_and_links_to_where_it_is_sold(): void
    {
        $this->publish('1.0.0', extra: ['price' => 29, 'currency' => 'USD', 'purchase_url' => 'https://shop.example.com/dir-notes']);

        $this->asAdmin()->get(route('admin.plugins.marketplace.show', 'dir-notes'))
            ->assertOk()
            ->assertSee('USD 29.00')
            ->assertSee('https://shop.example.com/dir-notes', false)
            ->assertDontSee(route('admin.plugins.marketplace.install', 'dir-notes'), false);

        $this->asAdmin()->post(route('admin.plugins.marketplace.install', 'dir-notes'))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'paid plugin'));

        Http::assertNotSent(fn ($request) => str_ends_with((string) $request->url(), '.zip'));
    }

    public function test_a_newer_directory_version_is_offered_and_installed(): void
    {
        $this->publish('1.0.0');
        $this->asAdmin()->post(route('admin.plugins.marketplace.install', 'dir-notes'));
        plugins()->enable('dir-notes');

        $this->publish('1.1.0');
        app(\App\Cms\Plugins\PluginCatalogClient::class)->flush();

        $this->asAdmin()->get(route('admin.plugins.index'))->assertOk()->assertSee('Update to 1.1.0');

        $this->asAdmin()->post(route('admin.plugins.marketplace.install', 'dir-notes'))->assertSessionHas('status');

        $plugin = Plugin::where('slug', 'dir-notes')->first();
        $this->assertSame('1.1.0', $plugin->version);
        $this->assertTrue($plugin->enabled, 'An update keeps the plugin switched on.');
    }

    public function test_a_hand_installed_plugin_with_the_same_name_is_never_replaced(): void
    {
        File::ensureDirectoryExists($this->pluginsPath.'/dir-notes/src');
        File::put($this->pluginsPath.'/dir-notes/plugin.json', json_encode($this->manifest('0.9.0')));
        plugins()->sync();

        $this->publish('1.0.0');

        $this->asAdmin()->post(route('admin.plugins.marketplace.install', 'dir-notes'))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'will not be replaced'));

        $this->assertSame('0.9.0', Plugin::where('slug', 'dir-notes')->value('version'));
    }

    public function test_turning_plugin_uploads_off_turns_the_directory_off(): void
    {
        config(['cms.plugins.allow_upload' => false]);
        $this->publish('1.0.0');

        $this->asAdmin()->get(route('admin.plugins.marketplace.index'))->assertNotFound();
        $this->asAdmin()->post(route('admin.plugins.marketplace.install', 'dir-notes'))->assertNotFound();
    }

    public function test_editors_cannot_use_the_directory(): void
    {
        $this->publish('1.0.0');

        $this->actingAs($this->user(User::ROLE_EDITOR))->withSession(['auth.two_factor_confirmed' => true])
            ->post(route('admin.plugins.marketplace.install', 'dir-notes'))
            ->assertForbidden();
    }

    public function test_make_creates_a_plugin_that_switches_on_and_shows_its_screen(): void
    {
        $this->artisan('cms:plugin', ['action' => 'make', 'slug' => 'made-here'])->assertSuccessful();

        $this->assertFileExists($this->pluginsPath.'/made-here/src/MadeHereServiceProvider.php');

        $this->artisan('cms:plugin', ['action' => 'enable', 'slug' => 'made-here'])->assertSuccessful();
        plugins()->flush();
        plugins()->registerEnabled($this->app);
        app('router')->getRoutes()->refreshNameLookups();

        $this->asAdmin()->get(route('admin.made-here.settings'))->assertOk()->assertSee('It works');
    }

    // Fixtures ------------------------------------------------------------

    private function publish(string $version, ?string $sha256 = null, string $listedSlug = 'dir-notes', array $extra = []): void
    {
        $url = "https://plugins.example.com/dir-notes/dir-notes-{$version}.zip";
        $this->archives[$url] = $this->zip($version);

        $this->published = ['format' => 1, 'items' => [array_merge([
            'slug' => $listedSlug,
            'name' => 'Directory Notes',
            'version' => $version,
            'download' => $url,
            'sha256' => $sha256 ?? hash('sha256', $this->archives[$url]),
            'size' => strlen($this->archives[$url]),
        ], $extra)]];

        app(\App\Cms\Plugins\PluginCatalogClient::class)->flush();
    }

    private function manifest(string $version): array
    {
        return [
            'name' => 'Directory Notes',
            'slug' => 'dir-notes',
            'version' => $version,
            'namespace' => 'RadiusPlugins\\DirNotes',
            'provider' => 'RadiusPlugins\\DirNotes\\DirNotesServiceProvider',
        ];
    }

    private function zip(string $version): string
    {
        $path = tempnam(sys_get_temp_dir(), 'plg');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('dir-notes/plugin.json', json_encode($this->manifest($version)));
        $zip->addFromString('dir-notes/src/DirNotesServiceProvider.php', "<?php\n\nnamespace RadiusPlugins\\DirNotes;\n\nclass DirNotesServiceProvider extends \\App\\Cms\\Plugins\\PluginServiceProvider {}\n");
        $zip->close();

        $bytes = file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->user(User::ROLE_ADMIN))->withSession(['auth.two_factor_confirmed' => true]);
    }

    private function user(string $role): User
    {
        return User::firstOrCreate(['email' => "{$role}@plugins.test"], [
            'name' => ucfirst($role), 'password' => Hash::make('x'),
            'role' => $role, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }
}
