<?php

namespace Tests\Feature;

use App\Cms\Marketplace\Catalog;
use App\Cms\Marketplace\CatalogClient;
use App\Cms\Themes\ThemeManager;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

/**
 * The theme directory installs code on this server, so these pin the things
 * that must never loosen: only an administrator may use it, an archive must be
 * exactly the one the catalogue published, and a downloaded theme is scanned
 * exactly as an uploaded one is. They also pin its privacy promise - a site
 * that never uses the directory never contacts it.
 */
class ThemeMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private const CATALOGUE = 'https://themes.example.com/themes.json';

    private string $themesPath;

    private string $assetDir;

    /** The catalogue the fake directory server is currently publishing. */
    private array $published = [];

    /** @var array<string, string> download URL => archive bytes */
    private array $archives = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->themesPath = storage_path('framework/testing/marketplace-themes');
        $this->assetDir = 'themes-marketplace-test';

        File::deleteDirectory($this->themesPath);
        File::copyDirectory(base_path('themes/default'), $this->themesPath.'/default');

        config([
            'cms.themes.path' => $this->themesPath,
            'cms.themes.asset_url' => $this->assetDir,
            'marketplace.enabled' => true,
            'marketplace.catalogue_url' => self::CATALOGUE,
        ]);

        $this->get('/admin');
        app(ThemeManager::class)->sync();

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if ($url === self::CATALOGUE) {
                return Http::response($this->published);
            }

            return isset($this->archives[$url])
                ? Http::response($this->archives[$url])
                : Http::response('missing', 404);
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themesPath);
        File::deleteDirectory(public_path($this->assetDir));

        parent::tearDown();
    }

    // -- Fixtures ---------------------------------------------------------

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $role.'@x.test', 'password' => Hash::make('password-123'),
            'role' => $role, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@x.test')->first() ?? $this->user(User::ROLE_ADMIN);
    }

    /** @param array<string, string> $files path inside the archive => contents */
    private function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'theme').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();
        $bytes = file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    private function themeArchive(string $slug = 'aurora', string $version = '1.0.0', array $extra = []): string
    {
        return $this->zip(array_merge([
            'theme.json' => json_encode(['name' => ucfirst($slug), 'slug' => $slug, 'version' => $version]),
            'views/home.blade.php' => '<h1>{{ setting(\'site_name\') }}</h1>',
            'assets/css/theme.css' => 'body { color: #111; }',
        ], $extra));
    }

    /** Publishes an archive and returns its catalogue entry. */
    private function listing(string $bytes, array $overrides = []): array
    {
        $entry = array_merge([
            'slug' => 'aurora',
            'name' => 'Aurora',
            'version' => '1.0.0',
            'author' => 'Example',
            'description' => 'A calm editorial theme.',
            'tags' => ['blog', 'minimal'],
            'screenshot' => 'https://themes.example.com/aurora/screenshot.png',
            'sha256' => hash('sha256', $bytes),
            'size' => strlen($bytes),
        ], $overrides);

        $entry['download'] ??= 'https://themes.example.com/'.$entry['slug'].'-'.$entry['version'].'.zip';
        $this->archives[$entry['download']] = $bytes;

        return $entry;
    }

    private function publish(array ...$items): void
    {
        $this->published = ['format' => 1, 'items' => $items];
        app(CatalogClient::class)->flush();
    }

    private function install(string $slug = 'aurora')
    {
        return $this->actingAs($this->admin())
            ->from(route('admin.themes.marketplace.index'))
            ->post(route('admin.themes.marketplace.install', $slug));
    }

    // -- Access -----------------------------------------------------------

    public function test_only_an_administrator_may_use_the_directory(): void
    {
        $this->publish($this->listing($this->themeArchive()));
        $editor = $this->user(User::ROLE_EDITOR);

        $this->actingAs($editor)->get(route('admin.themes.marketplace.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('admin.themes.marketplace.show', 'aurora'))->assertForbidden();
        $this->actingAs($editor)->post(route('admin.themes.marketplace.install', 'aurora'))->assertForbidden();
        $this->actingAs($editor)->post(route('admin.themes.marketplace.refresh'))->assertForbidden();

        $this->assertDirectoryDoesNotExist($this->themesPath.'/aurora');
    }

    public function test_switching_the_directory_off_removes_it_and_sends_nothing(): void
    {
        config(['marketplace.enabled' => false]);

        $this->actingAs($this->admin())->get(route('admin.themes.marketplace.index'))->assertNotFound();
        $this->actingAs($this->admin())->post(route('admin.themes.marketplace.install', 'aurora'))->assertNotFound();
        $this->actingAs($this->admin())->get(route('admin.themes.index'))->assertOk()->assertDontSee('Browse themes');

        Http::assertNothingSent();
    }

    public function test_a_site_that_never_used_the_directory_never_contacts_it(): void
    {
        $this->publish($this->listing($this->themeArchive()));

        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($this->admin())->get(route('admin.themes.index'))->assertOk();

        Http::assertNothingSent();
    }

    // -- Browsing ---------------------------------------------------------

    public function test_the_directory_lists_free_themes_and_is_cached(): void
    {
        $this->publish(
            $this->listing($this->themeArchive()),
            $this->listing($this->themeArchive('premium'), ['slug' => 'premium', 'name' => 'Premium Pick', 'price' => 49]),
            $this->listing($this->themeArchive('licensed'), ['slug' => 'licensed', 'name' => 'Licensed Pick', 'requires_license' => true]),
            $this->listing($this->themeArchive('numeric'), ['slug' => 'numeric', 'name' => 'Numeric Version', 'version' => 1.10]),
        );

        $this->actingAs($this->admin())->get(route('admin.themes.marketplace.index'))
            ->assertOk()
            ->assertSee('Aurora')
            ->assertDontSee('Premium Pick')
            ->assertDontSee('Licensed Pick')
            ->assertDontSee('Numeric Version');

        $this->actingAs($this->admin())->get(route('admin.themes.marketplace.index'))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_search_matches_words_and_tags(): void
    {
        $catalog = Catalog::fromArray(['format' => 1, 'items' => [
            ['slug' => 'aurora', 'name' => 'Aurora', 'version' => '1.0.0', 'tags' => ['blog', 'dark'], 'description' => 'Editorial'],
            ['slug' => 'market', 'name' => 'Market', 'version' => '1.0.0', 'tags' => ['shop']],
        ]]);

        $this->assertSame(['aurora'], array_map(fn ($i) => $i->slug, $catalog->search('editorial')));
        $this->assertSame(['market'], array_map(fn ($i) => $i->slug, $catalog->search(null, 'shop')));
        $this->assertSame([], $catalog->search('aurora', 'shop'));
        $this->assertCount(2, $catalog->search());
    }

    public function test_an_unreachable_directory_is_reported_and_not_retried_on_every_page(): void
    {
        config(['marketplace.catalogue_url' => 'https://down.example.com/themes.json']);

        $this->actingAs($this->admin())->get(route('admin.themes.marketplace.index'))
            ->assertOk()
            ->assertSee('The theme directory is not available right now.');

        $this->actingAs($this->admin())->get(route('admin.themes.marketplace.index'))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_a_catalogue_in_a_newer_format_is_refused_plainly(): void
    {
        $this->published = ['format' => 99, 'items' => []];

        $this->actingAs($this->admin())->get(route('admin.themes.marketplace.index'))
            ->assertOk()
            ->assertSee('newer format (version 99)');
    }

    public function test_unsafe_links_in_a_listing_are_dropped(): void
    {
        $this->publish($this->listing($this->themeArchive(), [
            'author_url' => 'javascript:alert(1)',
            'preview_url' => 'javascript:alert(2)',
            'screenshot' => 'http://themes.example.com/insecure.png',
        ]));

        $this->actingAs($this->admin())->get(route('admin.themes.marketplace.show', 'aurora'))
            ->assertOk()
            ->assertDontSee('javascript:', false)
            ->assertDontSee('insecure.png', false);
    }

    // -- Installing -------------------------------------------------------

    public function test_a_listed_theme_installs_and_is_remembered_as_from_the_directory(): void
    {
        $this->publish($this->listing($this->themeArchive(extra: ['shell.php' => '<?php system($_GET["c"]);'])));

        $this->install()->assertRedirect(route('admin.themes.index'))->assertSessionHas('status');

        $this->assertFileExists($this->themesPath.'/aurora/views/home.blade.php');
        $this->assertFileDoesNotExist($this->themesPath.'/aurora/shell.php');
        $this->assertFileExists(public_path($this->assetDir.'/aurora/css/theme.css'));

        $theme = Theme::where('slug', 'aurora')->firstOrFail();
        $this->assertSame(Theme::SOURCE_MARKETPLACE, $theme->source);
        $this->assertSame('aurora', $theme->source_slug);
        $this->assertNotNull($theme->installed_at);
    }

    public function test_a_download_that_does_not_match_its_checksum_installs_nothing(): void
    {
        $entry = $this->listing($this->themeArchive());
        $this->archives[$entry['download']] = $this->themeArchive(extra: ['views/extra.blade.php' => 'tampered']);
        $this->publish($entry);

        $this->install()->assertSessionHas('error', fn ($message) => str_contains($message, 'does not match the checksum'));

        $this->assertDirectoryDoesNotExist($this->themesPath.'/aurora');
        $this->assertNull(Theme::where('slug', 'aurora')->first());
    }

    public function test_a_listing_without_a_checksum_is_refused(): void
    {
        $this->publish($this->listing($this->themeArchive(), ['sha256' => null]));

        $this->install()->assertSessionHas('error', fn ($message) => str_contains($message, 'does not publish a SHA-256 checksum'));
        $this->assertDirectoryDoesNotExist($this->themesPath.'/aurora');
    }

    public function test_a_plain_http_download_is_refused(): void
    {
        $this->publish($this->listing($this->themeArchive(), ['download' => 'http://themes.example.com/aurora.zip']));

        $this->install()->assertSessionHas('error', fn ($message) => str_contains($message, 'insecure http://'));
        $this->assertDirectoryDoesNotExist($this->themesPath.'/aurora');
    }

    public function test_a_download_pointing_at_this_servers_own_network_is_refused(): void
    {
        $this->publish($this->listing($this->themeArchive(), ['download' => 'https://127.0.0.1/aurora.zip']));

        $this->install()->assertSessionHas('error', fn ($message) => str_contains($message, 'does not allow'));

        Http::assertNotSent(fn ($request) => str_contains((string) $request->url(), '127.0.0.1'));
        $this->assertDirectoryDoesNotExist($this->themesPath.'/aurora');
    }

    public function test_a_downloaded_theme_is_scanned_exactly_like_an_upload(): void
    {
        $this->publish($this->listing($this->themeArchive(extra: [
            'views/layout.blade.php' => '<?php echo shell_exec("id"); ?>',
        ])));

        $this->install()->assertSessionHas('error', fn ($message) => str_contains($message, 'executable PHP'));

        $this->assertDirectoryDoesNotExist($this->themesPath.'/aurora');
    }

    public function test_an_archive_cannot_install_under_a_different_slug_than_it_was_listed_as(): void
    {
        // Listed as "aurora", but the archive claims to be the site's other theme.
        File::copyDirectory(base_path('themes/default'), $this->themesPath.'/storefront');
        File::put($this->themesPath.'/storefront/theme.json', json_encode(['name' => 'Storefront', 'slug' => 'storefront', 'version' => '1.0.0']));
        app(ThemeManager::class)->sync();
        Theme::where('slug', 'storefront')->update(['source' => Theme::SOURCE_MARKETPLACE, 'source_slug' => 'aurora']);

        $this->publish($this->listing($this->themeArchive('storefront', '2.0.0'), ['slug' => 'aurora', 'version' => '2.0.0']));

        $this->install()->assertSessionHas('error', fn ($message) => str_contains($message, 'was listed as [aurora]'));

        $this->assertSame('1.0.0', json_decode(File::get($this->themesPath.'/storefront/theme.json'), true)['version']);
        $this->assertDirectoryDoesNotExist($this->themesPath.'/aurora');
    }

    public function test_a_theme_uploaded_by_hand_is_never_replaced_from_the_directory(): void
    {
        File::copyDirectory(base_path('themes/default'), $this->themesPath.'/aurora');
        File::put($this->themesPath.'/aurora/theme.json', json_encode(['name' => 'My Aurora', 'slug' => 'aurora', 'version' => '0.1.0']));
        app(ThemeManager::class)->sync();

        $this->publish($this->listing($this->themeArchive('aurora', '5.0.0'), ['version' => '5.0.0']));

        $this->install()->assertSessionHas('error', fn ($message) => str_contains($message, 'was not installed from the theme directory'));

        $this->assertSame('My Aurora', json_decode(File::get($this->themesPath.'/aurora/theme.json'), true)['name']);
    }

    public function test_a_theme_needing_a_newer_cms_is_refused(): void
    {
        $this->publish($this->listing($this->themeArchive(), ['requires' => '99.0.0']));

        $this->install()->assertSessionHas('error', fn ($message) => str_contains($message, 'needs version 99.0.0'));
        $this->assertDirectoryDoesNotExist($this->themesPath.'/aurora');
    }

    // -- Updates ----------------------------------------------------------

    public function test_a_newer_listed_version_is_offered_and_installs_in_place(): void
    {
        $this->publish($this->listing($this->themeArchive('aurora', '1.0.0')));
        $this->install()->assertSessionHas('status');

        // Same version: nothing to offer.
        $this->actingAs($this->admin())->get(route('admin.themes.index'))->assertOk()->assertDontSee('Update to');

        $this->publish($this->listing($this->themeArchive('aurora', '1.1.0'), ['version' => '1.1.0']));

        $this->actingAs($this->admin())->get(route('admin.themes.index'))
            ->assertOk()
            ->assertSee('Update to 1.1.0');

        $this->install()->assertSessionHas('status', 'Aurora updated to version 1.1.0.');

        $theme = Theme::where('slug', 'aurora')->firstOrFail();
        $this->assertSame('1.1.0', $theme->version);
        $this->assertSame(Theme::SOURCE_MARKETPLACE, $theme->source);

        $this->actingAs($this->admin())->get(route('admin.themes.index'))->assertOk()->assertDontSee('Update to');
    }

    public function test_where_a_theme_came_from_survives_a_rescan(): void
    {
        $this->publish($this->listing($this->themeArchive()));
        $this->install()->assertSessionHas('status');

        app(ThemeManager::class)->sync();
        $this->actingAs($this->admin())->get(route('admin.themes.index'))->assertOk();

        $this->assertSame(Theme::SOURCE_MARKETPLACE, Theme::where('slug', 'aurora')->value('source'));
    }

    public function test_uploading_over_a_directory_theme_stops_it_receiving_directory_updates(): void
    {
        $this->publish($this->listing($this->themeArchive()));
        $this->install()->assertSessionHas('status');

        $upload = UploadedFile::fake()->createWithContent('aurora.zip', $this->themeArchive('aurora', '1.0.0-custom'));

        $this->actingAs($this->admin())
            ->post(route('admin.themes.upload'), ['theme' => $upload, 'overwrite' => 1])
            ->assertSessionHas('status');

        $this->assertSame(Theme::SOURCE_MANUAL, Theme::where('slug', 'aurora')->value('source'));
    }

    // -- Curation helper --------------------------------------------------

    public function test_the_entry_command_prints_a_matching_checksum(): void
    {
        $bytes = $this->themeArchive('aurora', '2.3.0');
        $path = storage_path('framework/testing/aurora-2.3.0.zip');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $bytes);

        try {
            $status = Artisan::call('cms:marketplace-entry', ['zip' => $path, '--url' => 'https://themes.example.com/aurora']);
            $output = Artisan::output();

            $this->assertSame(0, $status);

            // The printed entry must be one the directory will accept as-is.
            $entry = json_decode(substr($output, 0, strrpos($output, '}') + 1), true);
            $this->assertSame(hash('sha256', $bytes), $entry['sha256']);
            $this->assertSame('2.3.0', $entry['version']);
            $this->assertSame('https://themes.example.com/aurora/aurora-2.3.0.zip', $entry['download']);

            $item = Catalog::fromArray(['format' => 1, 'items' => [$entry]])->find('aurora');
            $this->assertNotNull($item);
            $this->assertSame(strlen($bytes), $item->size);
        } finally {
            @unlink($path);
        }
    }
}
