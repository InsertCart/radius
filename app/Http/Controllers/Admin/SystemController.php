<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Support\ExposureProbe;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

/**
 * Environment information, the audit trail, and the tail of the log file.
 */
class SystemController extends Controller
{
    public function __construct(private ExposureProbe $exposure) {}

    public function index(): View
    {
        return view('admin.system.index', [
            'environment' => $this->environment(),
            'warnings' => $this->warnings(),
            'storage' => $this->storage(),
            'exposure' => $this->exposure->cached(),
            'remediation' => $this->exposure->remediation(),
            'servedFromProjectRoot' => $this->exposedProjectRoot() !== null,
            'readsHtaccess' => $this->exposure->readsHtaccess(),
            'webServer' => $this->exposure->remediation()['server'],
        ]);
    }

    /**
     * Ask the server what it will actually hand out, and report back.
     *
     * Deliberately a button rather than something that runs on every page
     * load: it makes four HTTP requests, and the answer only changes when the
     * hosting configuration does.
     */
    public function checkExposure(): \Illuminate\Http\RedirectResponse
    {
        $result = $this->exposure->run();

        activity('system.security_checked', 'Ran the public-file security check.');

        return match ($result['status']) {
            ExposureProbe::EXPOSED => back()->with('error',
                'Files that should be private are readable over the web: '
                .implode(', ', array_keys($result['readable'])).'. See Security below for how to fix it.'),
            ExposureProbe::PROTECTED => back()->with('status',
                'Checked: none of the private files on this site can be downloaded over the web.'),
            default => back()->with('warning', $result['reason']),
        };
    }

    public function activity(Request $request): View
    {
        return view('admin.system.activity', [
            'logs' => ActivityLog::with('user')
                ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
                ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
                ->latest()
                ->paginate(50)
                ->withQueryString(),
            'actions' => ActivityLog::distinct()->orderBy('action')->pluck('action'),
            'filters' => $request->only(['action', 'user']),
        ]);
    }

    /**
     * The tail of today's log. Reading the whole file would be unwise: these
     * grow to hundreds of megabytes on a busy site.
     */
    public function logs(): View
    {
        $path = storage_path('logs/laravel.log');
        $lines = [];

        if (is_file($path)) {
            $lines = $this->tail($path, 300);
        }

        return view('admin.system.logs', [
            'lines' => $lines,
            'path' => $path,
            'size' => is_file($path) ? File::size($path) : 0,
        ]);
    }

    /** Reads the last $count lines without loading the whole file. */
    private function tail(string $path, int $count): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return [];
        }

        $buffer = '';
        $chunk = 8192;
        $position = -1;
        $size = filesize($path);
        $lines = 0;

        while ($lines < $count && -$position < $size) {
            $seek = min($chunk, $size + $position + 1);
            $position -= $seek;

            fseek($handle, max(0, $size + $position + 1), SEEK_SET);
            $buffer = fread($handle, $seek).$buffer;
            $lines = substr_count($buffer, "\n");
        }

        fclose($handle);

        return array_slice(explode("\n", trim($buffer)), -$count);
    }

    private function environment(): array
    {
        return [
            'CMS version' => cms_version(),
            'Laravel' => app()->version(),
            'PHP' => PHP_VERSION,
            'Database' => $this->databaseVersion(),
            'Environment' => app()->environment(),
            'Debug mode' => config('app.debug') ? 'On' : 'Off',
            'URL' => config('app.url'),
            'Timezone' => config('app.timezone'),
            'Cache driver' => config('cache.default'),
            'Session driver' => config('session.driver'),
            'Queue driver' => config('queue.default'),
            'Mail driver' => config('mail.default'),
            'Max upload' => ini_get('upload_max_filesize'),
            'Memory limit' => ini_get('memory_limit'),
            'Max execution' => ini_get('max_execution_time').'s',
        ];
    }

    private function databaseVersion(): string
    {
        try {
            return (string) DB::selectOne('select version() as v')->v;
        } catch (\Throwable $e) {
            return 'Unavailable';
        }
    }

    /** Configuration that would be a problem on a live site. */
    private function warnings(): array
    {
        $warnings = [];

        // Checked from inside the web request, not the command line: the two
        // can load different php.ini files, and it is the web server that
        // handles uploads.
        $needed = [
            'gd' => 'Image thumbnails and re-encoding are disabled',
            'zip' => 'Theme uploads will fail',
            'fileinfo' => 'Uploaded files cannot be type-checked, so uploads are refused',
            'intl' => 'Some formatting falls back to English defaults',
        ];

        foreach ($needed as $extension => $effect) {
            if (! extension_loaded($extension)) {
                $warnings[] = "The {$extension} PHP extension is not loaded by your web server. {$effect}. Enable it in php.ini, then restart the web server.";
            }
        }

        if (config('app.debug')) {
            $warnings[] = 'APP_DEBUG is on. Set it to false before going live - it exposes stack traces and environment values to visitors.';
        }

        if (app()->environment('local')) {
            $warnings[] = 'APP_ENV is still "local". Set it to "production" on a live site.';
        }

        if (blank(config('app.key'))) {
            $warnings[] = 'No APP_KEY is set. Encrypted settings and gateway credentials will not work.';
        }

        if (! file_exists(public_path('storage'))) {
            $warnings[] = 'The public storage symlink is missing, so uploaded files will not load. Create it from the Tools panel.';
        }

        if (str_starts_with((string) config('app.url'), 'http://') && ! app()->environment('local')) {
            $warnings[] = 'APP_URL is not HTTPS. Card payments and service workers both require a secure origin.';
        }

        if (config('cms.admin_prefix') === 'admin') {
            $warnings[] = 'The admin panel is on the default /admin path. Set CMS_ADMIN_PREFIX in .env to something less predictable.';
        }

        // Reported from a real request this server made to itself, not from
        // the folder layout. The layout only says a leak is possible; the
        // probe says whether there is one. Warning on the first would fire on
        // most shared hosting, where it is usually a false alarm and rarely
        // something the owner can act on.
        $exposure = $this->exposure->cached();

        if ($exposure['status'] === ExposureProbe::EXPOSED) {
            $files = implode(', ', array_keys($exposure['readable']));

            $warnings[] = "Anyone on the internet can download {$files} from this site right now. "
                .'That gives away '.reset($exposure['readable']).'. '
                .'Fix this before anything else - see Security below.';
        }

        // Never run, on a layout where a leak is possible. Silence here would
        // be worse than the old speculative warning: an exposed site would
        // show nothing at all. So this asks for one click rather than
        // asserting something nobody has established.
        if ($exposure['status'] !== ExposureProbe::EXPOSED
            && $exposure['checked_at'] === null
            && $this->exposedProjectRoot() !== null) {
            $warnings[] = 'This site is served from the project folder, so files like .env sit inside the web root. '
                .'They should be blocked, but nobody has confirmed it on this server yet - run the check under Security below.';
        }

        // nginx ignores every .htaccess in the project, including the ones that
        // stop an uploaded file being executed. Worth saying even when nothing
        // is leaking, because the owner cannot tell by looking.
        if (! $this->exposure->readsHtaccess()) {
            $warnings[] = 'This site runs on nginx, which ignores the .htaccess files shipped with the CMS - '
                .'so the rules that keep .env private and stop uploaded files being executed are not in effect. '
                .'Copy them into your server block: see nginx.conf.example in the project folder, and Security below.';
        }

        // Web pages already use the short address - the request proves it
        // works - but queued mail and scheduled jobs have no request and fall
        // back to APP_URL, so those links still carry /public and redirect.
        if (preg_match('#/public/?$#', (string) config('app.url'))
            && rtrim((string) request()->root(), '/') !== rtrim((string) config('app.url'), '/')) {
            $warnings[] = 'APP_URL in .env still ends in /public, while this site answers without it. '
                .'Pages and sitemaps already use the short address, but links in emails do not. '
                .'Set APP_URL to '.rtrim((string) request()->root(), '/').' to make them agree.';
        }

        if (config('cms.downloads.disk') === config('cms.media.disk')) {
            $warnings[] = 'Paid downloads are set to the same disk as the media library, which is served publicly. '
                .'Set CMS_DOWNLOADS_DISK back to "private" - otherwise anyone with the file address can download a paid product without buying it.';
        }

        return $warnings;
    }

    /**
     * Whether the project folder, rather than public/, is the web root.
     *
     * Worked out from the request itself: DOCUMENT_ROOT is where the server
     * starts looking for files, so if the project directory sits inside it the
     * whole codebase - .env included - is addressable. Returns the file that
     * would be exposed, or null when the document root is set up correctly.
     */
    private function exposedProjectRoot(): ?string
    {
        $documentRoot = realpath((string) request()->server('DOCUMENT_ROOT'));

        if (! $documentRoot) {
            return null;
        }

        $normalise = fn (string $path) => rtrim(str_replace('\\', '/', $path), '/');

        $documentRoot = $normalise($documentRoot);
        $projectRoot = $normalise((string) realpath(base_path()));

        // Correctly set up, the document root is public/ - which is *below*
        // the project root, so the project does not sit inside it.
        if (! str_starts_with($projectRoot.'/', $documentRoot.'/')) {
            return null;
        }

        return file_exists(base_path('.env')) ? '.env' : 'your configuration';
    }

    private function storage(): array
    {
        $paths = [
            'Uploads' => storage_path('app/public'),
            'Logs' => storage_path('logs'),
            'Cache' => storage_path('framework/cache'),
            'Themes' => base_path('themes'),
        ];

        $results = [];

        foreach ($paths as $label => $path) {
            $results[$label] = [
                'path' => $path,
                'exists' => is_dir($path),
                'writable' => is_dir($path) && is_writable($path),
                'size' => is_dir($path) ? $this->directorySize($path) : 0,
            ];
        }

        return $results;
    }

    private function directorySize(string $path): int
    {
        $bytes = 0;

        try {
            foreach (File::allFiles($path) as $file) {
                $bytes += $file->getSize();
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return $bytes;
    }
}
