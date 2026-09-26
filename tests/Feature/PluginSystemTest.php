<?php

namespace Tests\Feature;

use App\Cms\Plugins\Hooks;
use App\Cms\Plugins\PluginInstaller;
use App\Cms\Plugins\PluginInstallException;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/**
 * Plugins are installed from a ZIP or a folder, arrive switched off, and are
 * removed completely - code, tables and row - on uninstall.
 */
class PluginSystemTest extends TestCase
{
    use RefreshDatabase;

    private string $pluginsPath;

    private string $scratch;

    protected function setUp(): void
    {
        parent::setUp();

        $id = Str::random(8);
        $this->pluginsPath = storage_path("framework/testing/plugins-{$id}");
        $this->scratch = storage_path("framework/testing/plugin-zips-{$id}");
        File::ensureDirectoryExists($this->pluginsPath);
        File::ensureDirectoryExists($this->scratch);

        config(['cms.plugins.path' => $this->pluginsPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pluginsPath);
        File::deleteDirectory($this->scratch);
        File::deleteDirectory(public_path('plugins/test-notes'));

        parent::tearDown();
    }

    public function test_a_plugin_goes_through_install_enable_disable_and_uninstall(): void
    {
        $result = app(PluginInstaller::class)->installFromArchive($this->zip($this->notesPlugin()));

        $this->assertSame('test-notes', $result['slug']);
        $this->assertFalse($result['updated']);

        $plugin = Plugin::where('slug', 'test-notes')->firstOrFail();
        $this->assertFalse($plugin->enabled, 'A new plugin must arrive switched off.');
        $this->assertFalse(Schema::hasTable('test_notes'), 'Nothing may run before it is switched on.');

        plugins()->enable('test-notes');

        $this->assertTrue($plugin->fresh()->enabled);
        $this->assertTrue(Schema::hasTable('test_notes'), 'Its migrations run when it is switched on.');
        $this->assertSame('yes', $plugin->fresh()->setting('activated'), 'Its activated() hook runs.');
        $this->assertFileExists(public_path('plugins/test-notes/notes.css'));
        $this->assertFileDoesNotExist(public_path('plugins/test-notes/shell.php'), 'Executable files are never published.');

        plugins()->disable('test-notes');
        $this->assertFalse($plugin->fresh()->enabled);
        $this->assertTrue(Schema::hasTable('test_notes'), 'Switching off keeps its data.');

        plugins()->uninstall('test-notes');

        $this->assertNull(Plugin::where('slug', 'test-notes')->first());
        $this->assertFalse(Schema::hasTable('test_notes'), 'Uninstalling rolls its tables back.');
        $this->assertDirectoryDoesNotExist($this->pluginsPath.'/test-notes');
        $this->assertDirectoryDoesNotExist(public_path('plugins/test-notes'));
    }

    public function test_an_enabled_plugin_cannot_be_uninstalled(): void
    {
        app(PluginInstaller::class)->installFromArchive($this->zip($this->notesPlugin()));
        plugins()->enable('test-notes');

        $this->expectException(PluginInstallException::class);
        plugins()->uninstall('test-notes');
    }

    public function test_installing_over_an_existing_plugin_needs_the_overwrite_flag(): void
    {
        $zip = $this->zip($this->notesPlugin());
        app(PluginInstaller::class)->installFromArchive($zip);

        try {
            app(PluginInstaller::class)->installFromArchive($zip);
            $this->fail('A second install of the same plugin must be refused.');
        } catch (PluginInstallException $e) {
            $this->assertStringContainsString('already installed', $e->getMessage());
        }

        $this->assertTrue(app(PluginInstaller::class)->installFromArchive($zip, overwrite: true)['updated']);
    }

    public function test_a_plugin_outside_the_plugin_namespace_is_refused(): void
    {
        $files = $this->notesPlugin();
        $manifest = json_decode($files['plugin.json'], true);
        $manifest['namespace'] = 'App\\Http';
        $manifest['provider'] = 'App\\Http\\Kernel';
        $files['plugin.json'] = json_encode($manifest);

        $this->expectException(PluginInstallException::class);
        $this->expectExceptionMessage('namespace');

        app(PluginInstaller::class)->installFromArchive($this->zip($files));
    }

    public function test_a_path_that_escapes_the_plugin_folder_is_refused(): void
    {
        $files = $this->notesPlugin();
        $files['../../escaped.php'] = '<?php echo "no";';

        $this->expectException(PluginInstallException::class);
        $this->expectExceptionMessage('illegal path');

        app(PluginInstaller::class)->installFromArchive($this->zip($files));
    }

    public function test_server_configuration_files_are_not_extracted(): void
    {
        $files = $this->notesPlugin();
        $files['.htaccess'] = 'SetHandler application/x-httpd-php';
        $files['.user.ini'] = 'auto_prepend_file=/etc/passwd';

        app(PluginInstaller::class)->installFromArchive($this->zip($files));

        $this->assertFileDoesNotExist($this->pluginsPath.'/test-notes/.htaccess');
        $this->assertFileDoesNotExist($this->pluginsPath.'/test-notes/.user.ini');
    }

    public function test_a_plugin_needing_a_newer_cms_is_refused(): void
    {
        $files = $this->notesPlugin();
        $manifest = json_decode($files['plugin.json'], true);
        $manifest['requires'] = '99.0.0';
        $files['plugin.json'] = json_encode($manifest);

        $this->expectException(PluginInstallException::class);
        $this->expectExceptionMessage('99.0.0');

        app(PluginInstaller::class)->installFromArchive($this->zip($files));
    }

    public function test_a_folder_whose_name_disagrees_with_its_manifest_is_reported_not_loaded(): void
    {
        $this->writePlugin($this->pluginsPath.'/wrong-name', $this->notesPlugin());

        $invalid = plugins()->sync();

        $this->assertArrayHasKey('wrong-name', $invalid);
        $this->assertNull(Plugin::where('slug', 'test-notes')->first());
    }

    public function test_safe_mode_loads_no_plugin(): void
    {
        app(PluginInstaller::class)->installFromArchive($this->zip($this->notesPlugin()));
        plugins()->enable('test-notes');
        plugins()->flush();

        config(['cms.plugins.safe_mode' => true]);
        plugins()->registerEnabled($this->app);

        $this->assertFalse(plugins()->enabled('test-notes'));
    }

    public function test_a_site_updated_from_before_plugins_gets_its_folder_guards(): void
    {
        // An update never writes plugins/, so an updated site has neither the
        // folder nor its .htaccess until the plugin system creates them.
        File::deleteDirectory($this->pluginsPath);

        plugins()->sync();

        $this->assertStringContainsString('Require all denied', File::get($this->pluginsPath.'/.htaccess'));
        $this->assertFileExists(public_path('plugins/.htaccess'));

        // One the owner has edited is left as it is.
        File::put($this->pluginsPath.'/.htaccess', '# mine');
        plugins()->sync();
        $this->assertSame('# mine', File::get($this->pluginsPath.'/.htaccess'));
    }

    public function test_hooks_collect_output_in_priority_order_and_survive_a_failing_listener(): void
    {
        $hooks = app(Hooks::class);
        $hooks->listen('test.spot', fn ($x) => "b{$x}", 20);
        $hooks->listen('test.spot', fn () => throw new \RuntimeException('broken plugin'));
        $hooks->listen('test.spot', fn ($x) => "a{$x}", 5);

        $this->assertSame('a1b1', $hooks->render('test.spot', 1));
        $this->assertSame('', $hooks->render('nobody.listens'));
    }

    public function test_the_plugins_screen_is_for_administrators(): void
    {
        $editor = $this->user(User::ROLE_EDITOR);
        $this->actingAs($editor)->withSession(['auth.two_factor_confirmed' => true])
            ->get(route('admin.plugins.index'))->assertForbidden();

        app('auth')->forgetGuards();

        app(PluginInstaller::class)->installFromArchive($this->zip($this->notesPlugin()));

        $this->actingAs($this->user(User::ROLE_ADMIN))->withSession(['auth.two_factor_confirmed' => true])
            ->get(route('admin.plugins.index'))
            ->assertOk()
            ->assertSee('Test Notes');
    }

    // Fixtures ------------------------------------------------------------

    /** @return array<string, string> path => contents */
    private function notesPlugin(): array
    {
        return [
            'plugin.json' => json_encode([
                'name' => 'Test Notes',
                'slug' => 'test-notes',
                'version' => '1.0.0',
                'namespace' => 'RadiusPlugins\\TestNotes',
                'provider' => 'RadiusPlugins\\TestNotes\\NotesServiceProvider',
            ]),
            'src/NotesServiceProvider.php' => <<<'PHP'
                <?php

                namespace RadiusPlugins\TestNotes;

                use App\Cms\Plugins\PluginServiceProvider;

                class NotesServiceProvider extends PluginServiceProvider
                {
                    public function activated(): void
                    {
                        plugins()->saveConfig($this->slug(), ['activated' => 'yes']);
                    }
                }
                PHP,
            'database/migrations/2026_01_01_000000_create_test_notes_table.php' => <<<'PHP'
                <?php

                use Illuminate\Database\Migrations\Migration;
                use Illuminate\Database\Schema\Blueprint;
                use Illuminate\Support\Facades\Schema;

                return new class extends Migration
                {
                    public function up(): void
                    {
                        Schema::create('test_notes', function (Blueprint $table) {
                            $table->id();
                            $table->string('body');
                        });
                    }

                    public function down(): void
                    {
                        Schema::dropIfExists('test_notes');
                    }
                };
                PHP,
            'assets/notes.css' => 'body{}',
            'assets/shell.php' => '<?php system($_GET["c"]);',
        ];
    }

    private function zip(array $files): string
    {
        $path = $this->scratch.'/'.Str::random(6).'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);

        foreach ($files as $name => $contents) {
            $zip->addFromString(str_starts_with($name, '../') ? $name : 'test-notes/'.$name, $contents);
        }

        $zip->close();

        return $path;
    }

    private function writePlugin(string $directory, array $files): void
    {
        foreach ($files as $name => $contents) {
            File::ensureDirectoryExists(dirname($directory.'/'.$name));
            File::put($directory.'/'.$name, $contents);
        }
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $role.Str::random(4).'@example.test', 'password' => Hash::make('x'),
            'role' => $role, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }
}
