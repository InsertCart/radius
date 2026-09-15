<?php

namespace App\Cms\Support;

/**
 * Gets a freshly extracted copy to the point where the setup wizard can run.
 *
 * Laravel needs an APP_KEY before it can encrypt a session cookie, and it needs
 * a session before it can show a form. With no .env file the framework throws
 * MissingAppKeyException while booting - so the buyer's very first request dies
 * with a 500 and they never reach the wizard that would have written the .env
 * for them. It is a genuine chicken-and-egg, and it has to be broken before the
 * application boots.
 *
 * So this runs from bootstrap/app.php, in plain PHP, before the framework has
 * loaded any configuration: if there is no .env it creates one from
 * .env.example and gives this installation its own APP_KEY.
 *
 * The key is generated here rather than shipped in .env.example on purpose. A
 * key baked into the download would be identical on every site that bought the
 * product, and anyone holding it could forge session cookies for all of them.
 */
class FirstRun
{
    public static function ensureEnvironmentFile(string $basePath): void
    {
        $env = $basePath.DIRECTORY_SEPARATOR.'.env';

        if (is_file($env)) {
            return;
        }

        $example = $basePath.DIRECTORY_SEPARATOR.'.env.example';

        if (! is_file($example)) {
            return;
        }

        $contents = @file_get_contents($example);

        if ($contents === false) {
            return;
        }

        $contents = self::set($contents, 'APP_KEY', self::generateKey());

        // Setup has to work before there is a database to work with. A
        // database-backed session or cache sends the wizard's first request
        // looking for tables nothing has created yet, and the buyer gets a
        // stack trace instead of a form. Files need nothing but a writable
        // storage folder, which the wizard checks for anyway.
        foreach (['SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
            $contents = self::set($contents, $key, $value);
        }

        // .env.example carries the address of whoever built the release, which
        // is never the address of the site now being installed. Guessing from
        // the request is not authoritative - the wizard asks, and its answer
        // wins - but it means assets and links work while setup is running.
        if ($url = self::guessUrl()) {
            $contents = self::set($contents, 'APP_URL', $url);
        }

        // Written atomically so two simultaneous first requests cannot leave a
        // half-written .env behind - which would be far harder to recover from
        // than having none at all.
        $temporary = $env.'.'.getmypid().'.tmp';

        if (@file_put_contents($temporary, $contents, LOCK_EX) === false) {
            return;
        }

        // rename() refuses to clobber on Windows, so a .env that appeared while
        // this was being written is kept and the temporary file discarded.
        if (is_file($env) || ! @rename($temporary, $env)) {
            @unlink($temporary);
        }
    }

    private static function generateKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /** Replace a key if present, append it if not. */
    private static function set(string $contents, string $key, string $value): string
    {
        $line = $key.'="'.str_replace('"', '\"', $value).'"';

        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents)) {
            return preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents, 1);
        }

        return rtrim($contents)."\n".$line."\n";
    }

    /**
     * The address this request arrived on, as far as it can be trusted.
     *
     * Only used to fill in a default the site owner immediately confirms in the
     * wizard, so a spoofed Host header buys an attacker nothing here.
     */
    private static function guessUrl(): ?string
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null;

        if (! is_string($host) || $host === '' || ! preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
            return null;
        }

        $secure = (! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

        // Where the front controller lives, e.g. /mysite/public.
        $path = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');

        // The root .htaccess lets the site be reached without /public in the
        // address, and most installs are used that way. When that has happened
        // the requested URL has no public/ segment while SCRIPT_NAME does, and
        // the site's real base is one level up. Getting this wrong would not
        // break anything - both addresses resolve - but every generated link
        // would carry a /public nobody typed.
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

        if (str_ends_with($path, '/public') && ! str_starts_with($uri, $path)) {
            $path = substr($path, 0, -strlen('/public'));
        }

        return ($secure ? 'https://' : 'http://').$host.$path;
    }
}
