<?php

namespace App\Cms\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Shows visitors a "back in a few minutes" page while an update swaps files.
 *
 * While vendor/ is being replaced, a request can load half of one version and
 * half of the next and die with an error nobody can act on. The ordinary
 * maintenance switch cannot help, because it is application code running on
 * those same files. So this writes storage/framework/maintenance.php, the one
 * file public/index.php loads before Composer, and that file depends on nothing
 * else.
 *
 * Two properties keep it from harming a working site:
 *
 *  - It removes itself after a fixed time. An update that dies half-way cannot
 *    leave a site closed for good.
 *  - It never replaces a file it did not write, so a site put down on purpose
 *    with `php artisan down` stays down, and comes back only when its owner says.
 *
 * The administrator running the update gets a cookie that passes the page, or
 * they could not reach the screen that finishes the update.
 */
class UpdateHoldingPage
{
    public const COOKIE = 'cms_update_access';

    private const MARKER = '// cms-update-holding-page';

    private const DEFAULT_MINUTES = 30;

    public function path(): string
    {
        return storage_path('framework/maintenance.php');
    }

    /** True only while a page written by this class is in place. */
    public function active(): bool
    {
        return is_file($this->path())
            && str_contains((string) file_get_contents($this->path()), self::MARKER);
    }

    /**
     * Close the site to visitors.
     *
     * Never fatal. A site that cannot show this page is exactly as well off as
     * it was before the page existed, which is no reason to refuse the update.
     *
     * @return string|null the pass for the person running the update
     */
    public function open(): ?string
    {
        $path = $this->path();

        if (is_file($path) && ! $this->active()) {
            return null;
        }

        try {
            $minutes = max(5, (int) config('updates.holding_page_minutes', self::DEFAULT_MINUTES));
            $expires = time() + ($minutes * 60);
            $token = bin2hex(random_bytes(32));

            File::ensureDirectoryExists(dirname($path));

            // Written to a temporary file and moved, so a visitor arriving at
            // this instant can never execute half of it.
            File::replace($path, $this->script(hash('sha256', $token), $expires));

            $this->forgetCompiled($path);
            $this->grantAccess($token, $expires);

            return $token;
        } catch (\Throwable $e) {
            Log::warning('[updates] Could not put up the holding page: '.$e->getMessage());

            return null;
        }
    }

    /** Let visitors back in. Leaves a page it did not write alone. */
    public function close(): void
    {
        if (! $this->active()) {
            return;
        }

        @unlink($this->path());

        $this->forgetCompiled($this->path());
    }

    /**
     * Set directly rather than through the response: the framework would
     * encrypt it, and the page reading it runs before anything could decrypt.
     */
    private function grantAccess(string $token, int $expires): void
    {
        if (app()->runningInConsole() || headers_sent()) {
            return;
        }

        setcookie(self::COOKIE, $token, [
            'expires' => $expires,
            'path' => '/',
            'secure' => request()->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function forgetCompiled(string $path): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    /** The whole page as one file, needing nothing but PHP itself. */
    private function script(string $tokenHash, int $expires): string
    {
        $cookie = var_export(self::COOKIE, true);
        $hash = var_export($tokenHash, true);
        $html = var_export($this->html(), true);
        $json = var_export((string) json_encode([
            'message' => 'This site is being updated. Please try again in a few minutes.',
            'error' => 'updating',
        ]), true);
        $marker = self::MARKER;

        return <<<PHP
        <?php
        {$marker}
        // Written by the updater and removed when the update finishes. If an update
        // was abandoned this file removes itself; deleting it by hand is also safe.

        (static function () {
            if (time() >= {$expires}) {
                @unlink(__FILE__);

                return;
            }

            \$access = \$_COOKIE[{$cookie}] ?? null;

            if (is_string(\$access) && hash_equals({$hash}, hash('sha256', \$access))) {
                return;
            }

            http_response_code(503);
            header('Retry-After: 120');
            header('Cache-Control: no-store, max-age=0');

            if (strpos(\$_SERVER['HTTP_ACCEPT'] ?? '', 'json') !== false) {
                header('Content-Type: application/json; charset=utf-8');

                exit({$json});
            }

            header('Content-Type: text/html; charset=utf-8');

            exit({$html});
        })();

        PHP;
    }

    /** Styled inline: the built stylesheet may be the next file being replaced. */
    private function html(): string
    {
        $name = e($this->siteName());

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<meta name="robots" content="noindex"><meta http-equiv="refresh" content="60">'
            ."<title>Back soon &middot; {$name}</title></head>"
            .'<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#f1f5f9;'
            .'color:#0f172a;font:16px/1.6 system-ui,sans-serif;text-align:center">'
            .'<main style="max-width:28rem;padding:1.5rem">'
            ."<h1 style=\"margin:0;font-size:1.5rem\">{$name}</h1>"
            .'<p style="margin:.75rem 0 0;color:#475569">We are updating this site. '
            .'It will be back in a few minutes.</p></main></body></html>';
    }

    private function siteName(): string
    {
        try {
            return (string) setting('site_name', config('app.name'));
        } catch (\Throwable) {
            return (string) config('cms.name', 'This site');
        }
    }
}
