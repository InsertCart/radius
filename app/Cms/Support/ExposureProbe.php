<?php

namespace App\Cms\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks the web server, over HTTP, whether it will hand out files it should not.
 *
 * The question this answers used to be put to the site owner: "your project
 * folder is in the web root, request /.env in a browser to check". That is a
 * poor thing to ask. It fires on almost every shared-hosting install, where
 * pointing the document root elsewhere is often not even possible, so the
 * advice is unfollowable - and a warning that cannot be acted on teaches people
 * to ignore warnings. It is also speculative: it describes a layout, not an
 * actual leak, so most of the time it is simply wrong.
 *
 * Whether .env is readable is a matter of fact, and the server can settle it by
 * asking itself. That turns a permanent scary-sounding notice into either
 * silence or a real emergency, and the difference between those two is worth a
 * great deal.
 */
class ExposureProbe
{
    private const CACHE_KEY = 'cms.security.exposure';

    public const PROTECTED = 'protected';

    public const EXPOSED = 'exposed';

    public const UNKNOWN = 'unknown';

    /**
     * Paths that must never be readable, and what it costs if they are.
     *
     * .env is first because it is the one that matters: it carries the database
     * password and the key that signs every session cookie.
     */
    private const TARGETS = [
        '.env' => 'your database password and the key that signs every session cookie',
        'composer.json' => 'the exact library versions this site runs, which makes known vulnerabilities easy to look up',
        'storage/logs/laravel.log' => 'error logs, which often quote real data and file paths',
        'storage/installed' => 'internal setup details',
    ];

    public function cached(): array
    {
        return Cache::get(self::CACHE_KEY) ?: [
            'status' => self::UNKNOWN,
            'readable' => [],
            'checked' => [],
            'checked_at' => null,
            'reason' => 'This site has not been checked yet.',
        ];
    }

    /** Run the check now and cache the verdict. */
    public function run(): array
    {
        $base = rtrim(url('/'), '/');
        $readable = [];
        $checked = [];
        $failures = 0;

        foreach (self::TARGETS as $path => $meaning) {
            $code = $this->status($base.'/'.$path);
            $checked[$path] = $code;

            if ($code === null) {
                $failures++;

                continue;
            }

            // Anything the server is willing to return is readable. 403 and 404
            // are the results we want; a redirect is a pass too, because it
            // means something intercepted the request rather than serving it.
            if ($code >= 200 && $code < 300) {
                $readable[$path] = $meaning;
            }
        }

        // Every request failed, so nothing was learned. Reporting "protected"
        // here would be a lie, and a comforting one - the worst kind.
        $status = match (true) {
            $failures === count(self::TARGETS) => self::UNKNOWN,
            $readable !== [] => self::EXPOSED,
            default => self::PROTECTED,
        };

        $result = [
            'status' => $status,
            'readable' => $readable,
            'checked' => $checked,
            'checked_at' => now()->toIso8601String(),
            'reason' => $status === self::UNKNOWN
                ? 'This server could not make a request to itself. That is normal in Docker or behind a load '
                    .'balancer, where the site address does not resolve from inside the container, and on hosts '
                    .'that block outbound connections - none of that means anything is wrong. Check by hand '
                    .'instead: open '.$base.'/.env in a browser. Anything other than 403 or 404 means it is readable.'
                : null,
        ];

        Cache::put(self::CACHE_KEY, $result, now()->addDay());

        return $result;
    }

    /** The status the server returns, or null when the request never arrived. */
    private function status(string $url): ?int
    {
        try {
            return Http::timeout(8)
                ->connectTimeout(5)
                // A redirect means something handled the request instead of
                // serving the file, which counts as a pass. There is no reason
                // to follow it, and following invites a loop.
                ->withoutRedirecting()
                // The certificate may be self-signed or missing on a staging
                // box. Identity is not in question here - the only thing being
                // measured is what this very server returns.
                ->withoutVerifying()
                ->withUserAgent(config('cms.name', 'CMS').' security self-check')
                ->get($url)
                ->status();
        } catch (\Throwable $e) {
            Log::debug('[security] Self-check could not reach '.$url.': '.$e->getMessage());

            return null;
        }
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * What to do about it, phrased for the server they are actually on.
     *
     * Apache and LiteSpeed read .htaccess, so if files are still readable the
     * host has overrides switched off and the fix is a hosting setting rather
     * than a file. nginx never reads .htaccess at all, so those owners need a
     * config block - and telling them to "check your .htaccess" would waste
     * their afternoon.
     */
    /** The web server's own description of itself, lower-cased. */
    public function server(): string
    {
        return strtolower((string) request()->server('SERVER_SOFTWARE'));
    }

    /**
     * Whether this server reads the .htaccess files shipped with the CMS.
     *
     * nginx never does. That matters even when nothing is currently leaking:
     * the rules that stop an uploaded file being executed are in those files
     * too, so on nginx they have to be restored in the vhost. Saying so only
     * after a leak has been found would be saying it too late.
     */
    public function readsHtaccess(): bool
    {
        $server = $this->server();

        if (str_contains($server, 'nginx')) {
            return false;
        }

        // Apache, LiteSpeed and OpenLiteSpeed all honour .htaccess. An unknown
        // server is assumed to, because the alternative is telling every owner
        // on an unrecognised stack that their site is misconfigured.
        return true;
    }

    public function remediation(): array
    {
        $server = $this->server();

        if (str_contains($server, 'nginx')) {
            return [
                'server' => 'nginx',
                'summary' => 'nginx does not read .htaccess files, so the protection shipped with this CMS does nothing on your server. '
                    .'A complete server block is included with the CMS as nginx.conf.example, with notes for CloudPanel. '
                    .'The essentials are below.',
                'snippet' => "root /path/to/this/project/public;\n\nlocation / {\n    try_files \$uri \$uri/ /index.php?\$query_string;\n}\n\nlocation ~ /\. { deny all; }\nlocation ~ ^/(app|bootstrap|config|database|resources|routes|storage|tests|themes|vendor)/ { deny all; }",
            ];
        }

        if (str_contains($server, 'apache') || str_contains($server, 'litespeed')) {
            return [
                'server' => str_contains($server, 'litespeed') ? 'LiteSpeed' : 'Apache',
                'summary' => 'This CMS ships an .htaccess that blocks these files, so your server is ignoring it - which means AllowOverride is None for this directory. Ask your host to allow overrides, or point the document root at the public/ folder.',
                'snippet' => "<Directory /path/to/this/project>\n    AllowOverride All\n</Directory>",
            ];
        }

        return [
            'server' => $server ?: 'your web server',
            'summary' => 'Point the document root at the public/ folder. Nothing outside it is meant to be reachable over the web.',
            'snippet' => null,
        ];
    }
}
