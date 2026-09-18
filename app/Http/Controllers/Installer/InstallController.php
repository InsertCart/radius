<?php

namespace App\Http\Controllers\Installer;

use App\Cms\Cdn\CdnManager;
use App\Cms\Modules\ModuleManager;
use App\Cms\Payments\PaymentManager;
use App\Cms\Settings\SettingsRepository;
use App\Cms\Shop\Currencies;
use App\Cms\Themes\ThemeManager;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The setup wizard a buyer runs after uploading the files.
 *
 * Four steps: check the server, connect a database, describe the site, create
 * an admin. Each step's answers are held in the session until the final step
 * writes .env, runs the migrations and drops the lock file.
 */
class InstallController extends Controller
{
    public function __construct(
        private SettingsRepository $settings,
        private ModuleManager $modules,
        private ThemeManager $themes,
        private PaymentManager $payments,
        private CdnManager $cdn,
    ) {}

    // Step 1: requirements ------------------------------------------------

    public function requirements(): View
    {
        $checks = $this->requirementChecks();
        $permissions = $this->permissionChecks();

        return view('installer.requirements', [
            'checks' => $checks,
            'permissions' => $permissions,
            'canContinue' => ! collect($checks)->contains('passed', false)
                && ! collect($permissions)->contains('passed', false),
        ]);
    }

    /** @return array<string, array{label: string, required: string, current: string, passed: bool}> */
    private function requirementChecks(): array
    {
        $checks = [
            'php' => [
                'label' => 'PHP version',
                'required' => '8.2 or newer',
                'current' => PHP_VERSION,
                'passed' => version_compare(PHP_VERSION, '8.2.0', '>='),
            ],
        ];

        $extensions = [
            'pdo_mysql' => 'Database access',
            'mbstring' => 'Multibyte strings',
            'openssl' => 'Encryption',
            'tokenizer' => 'Template compilation',
            'json' => 'JSON handling',
            'curl' => 'Payment and SMS APIs',
            'fileinfo' => 'Upload validation',
            'zip' => 'Theme uploads',
            'gd' => 'Image thumbnails',
            'xml' => 'Sitemap generation',
            'ctype' => 'Character checks',
        ];

        foreach ($extensions as $extension => $purpose) {
            $loaded = extension_loaded($extension);

            $checks['ext_'.$extension] = [
                'label' => "PHP extension: {$extension}",
                'required' => $purpose,
                'current' => $loaded ? 'Loaded' : 'Missing',
                'passed' => $loaded,
            ];
        }

        return $checks;
    }

    /** @return array<string, array{label: string, current: string, passed: bool}> */
    private function permissionChecks(): array
    {
        $paths = [
            'storage/framework' => storage_path('framework'),
            'storage/logs' => storage_path('logs'),
            'storage/app' => storage_path('app'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            '.env' => base_path('.env'),
            'themes' => base_path('themes'),
            'public' => public_path(),
        ];

        $results = [];

        foreach ($paths as $label => $path) {
            $writable = file_exists($path) ? is_writable($path) : is_writable(dirname($path));

            $results[$label] = [
                'label' => $label,
                'current' => $writable ? 'Writable' : 'Not writable',
                'passed' => $writable,
            ];
        }

        return $results;
    }

    // Step 2: database ----------------------------------------------------

    public function database(): View
    {
        return view('installer.database', [
            'values' => session('install.database', [
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => '',
                'username' => 'root',
                'password' => '',
            ]),
        ]);
    }

    public function saveDatabase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'numeric'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:64'],
            'password' => ['nullable', 'string'],
        ]);

        // Test the credentials against a throwaway connection before writing
        // them anywhere, so a typo is caught here rather than after .env has
        // been rewritten.
        $error = $this->testConnection($data);

        if ($error) {
            return back()->withInput()->with('error', $error);
        }

        session(['install.database' => $data]);

        return redirect()->route('install.site');
    }

    private function testConnection(array $data): ?string
    {
        Config::set('database.connections.install_test', [
            'driver' => 'mysql',
            'host' => $data['host'],
            'port' => $data['port'],
            'database' => $data['database'],
            'username' => $data['username'],
            'password' => $data['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        try {
            DB::connection('install_test')->getPdo();

            return null;
        } catch (\Throwable $e) {
            return 'Could not connect: '.$e->getMessage();
        } finally {
            DB::purge('install_test');
        }
    }

    // Step 3: site details ------------------------------------------------

    public function site(): RedirectResponse|View
    {
        if (! session()->has('install.database')) {
            return redirect()->route('install.database');
        }

        return view('installer.site', [
            'values' => session('install.site', [
                'site_name' => 'My Website',
                'site_email' => '',
                'app_url' => rtrim(url('/'), '/'),
                'timezone' => 'UTC',
                'currency' => 'USD',
            ]),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'currencies' => Currencies::options(),
        ]);
    }

    public function saveSite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'site_email' => ['required', 'email', 'max:190'],
            'app_url' => ['required', 'url', 'max:190'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3', Rule::in(array_keys(Currencies::options()))],
        ]);

        session(['install.site' => $data]);

        return redirect()->route('install.account');
    }

    // Step 4: admin account -----------------------------------------------

    public function account(): RedirectResponse|View
    {
        if (! session()->has('install.site')) {
            return redirect()->route('install.site');
        }

        return view('installer.account');
    }

    public function saveAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()->mixedCase()],
        ]);

        session(['install.account' => $data]);

        return redirect()->route('install.finish');
    }

    // Final step: write everything ----------------------------------------

    public function finish(): RedirectResponse|View
    {
        $database = session('install.database');
        $site = session('install.site');
        $account = session('install.account');

        if (! $database || ! $site || ! $account) {
            return redirect()->route('install.requirements')
                ->with('error', 'Your setup session expired. Please start again.');
        }

        try {
            $this->writeEnv($database, $site);
            $this->connectTo($database);

            Artisan::call('migrate', ['--force' => true]);

            $this->seedDefaults($site);
            $this->createAdmin($account);
            $this->linkStorage();

            File::put(config('cms.install_lock'), json_encode([
                'version' => cms_version(),
                'installed_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            session()->forget(['install.database', 'install.site', 'install.account']);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('install.requirements')
                ->with('error', 'Installation failed: '.$e->getMessage());
        }

        return view('installer.finish', [
            'adminUrl' => route('admin.login'),
            'siteUrl' => url('/'),
            'email' => $account['email'],
        ]);
    }

    /**
     * Rewrites .env in place, replacing existing keys and appending new ones.
     * Values are quoted so a password containing a space or a hash survives.
     */
    private function writeEnv(array $database, array $site): void
    {
        $path = base_path('.env');

        if (! is_file($path)) {
            File::copy(base_path('.env.example'), $path);
        }

        $contents = File::get($path);

        $values = [
            'APP_NAME' => $site['site_name'],
            'APP_URL' => rtrim($site['app_url'], '/'),
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $database['host'],
            'DB_PORT' => $database['port'],
            'DB_DATABASE' => $database['database'],
            'DB_USERNAME' => $database['username'],
            'DB_PASSWORD' => $database['password'] ?? '',
        ];

        foreach ($values as $key => $value) {
            $line = $key.'="'.str_replace('"', '\"', (string) $value).'"';

            $contents = preg_match("/^{$key}=.*$/m", $contents)
                ? preg_replace("/^{$key}=.*$/m", $line, $contents)
                : $contents."\n".$line;
        }

        File::put($path, $contents);

        // A generated APP_KEY is what makes the encrypted settings and gateway
        // credentials meaningful, so make sure one exists.
        if (blank(config('app.key'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }
    }

    /** Point the live connection at the new database for the rest of this request. */
    private function connectTo(array $database): void
    {
        Config::set('database.connections.mysql', array_merge(
            config('database.connections.mysql'),
            [
                'host' => $database['host'],
                'port' => $database['port'],
                'database' => $database['database'],
                'username' => $database['username'],
                'password' => $database['password'] ?? '',
            ]
        ));

        Config::set('database.default', 'mysql');
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    private function seedDefaults(array $site): void
    {
        $this->modules->sync();
        $this->payments->sync();
        $this->themes->sync();
        $this->cdn->sync();

        $this->settings->seedMissingDefaults();

        $this->settings->setMany([
            'site_name' => $site['site_name'],
            'site_email' => $site['site_email'],
            'meta_title' => $site['site_name'],
            'schema_org_name' => $site['site_name'],
            'timezone' => $site['timezone'],
            'shop_currency' => $site['currency'],
            'shop_currency_symbol' => Currencies::symbolFor($site['currency']) ?? '$',
            'mail_from_address' => $site['site_email'],
            'mail_from_name' => $site['site_name'],
        ]);

        Artisan::call('db:seed', ['--class' => 'ContentSeeder', '--force' => true]);
    }

    private function createAdmin(array $account): void
    {
        User::updateOrCreate(
            ['email' => $account['email']],
            [
                'name' => $account['name'],
                'password' => Hash::make($account['password']),
                'role' => User::ROLE_ADMIN,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
    }

    /** Best effort: symlinks fail on some shared hosts, which is not fatal. */
    private function linkStorage(): void
    {
        try {
            if (! file_exists(public_path('storage'))) {
                Artisan::call('storage:link');
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
