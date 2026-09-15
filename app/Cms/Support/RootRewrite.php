<?php

namespace App\Cms\Support;

/**
 * Lets the site answer on https://example.com/ when the document root is the
 * project folder rather than public/.
 *
 * The root .htaccess maps every request into public/, which is what keeps the
 * source code and .env out of reach. But that leaves the request describing
 * itself inconsistently: the browser asked for /shop, while SCRIPT_NAME still
 * says the front controller lives at /public/index.php. Symfony works out where
 * the application is mounted by comparing those two, decides the site lives
 * under /public, and hands Laravel a path of "/shop" that it thinks is
 * "/public/shop" - so every route misses and the whole site answers 404.
 *
 * Correcting SCRIPT_NAME before the Request object is built fixes routing,
 * generated links and redirects in one go, and costs nothing on a server that
 * is set up the recommended way: when the document root already points at
 * public/, the request and the script agree and this does nothing.
 */
class RootRewrite
{
    public static function align(): void
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

        if ($script === '') {
            return;
        }

        // SCRIPT_NAME is a URL path, so it is always forward-slashed.
        $directory = rtrim(dirname($script), '/');

        // Only ever adjusts an address that really does end in /public.
        if (! str_ends_with($directory, '/public')) {
            return;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) parse_url($uri, PHP_URL_PATH);

        // The visitor asked for the public/ address themselves, so nothing has
        // been rewritten and the two already agree.
        if ($path === $directory || str_starts_with($path, $directory.'/')) {
            return;
        }

        $root = substr($directory, 0, -strlen('/public'));

        // Confirm the request really sits under the project before rewriting
        // the script path to match. Anything else - an alias, a proxy with an
        // unexpected prefix - is left alone rather than guessed at.
        if ($root !== '' && ! str_starts_with($path, $root.'/') && $path !== $root) {
            return;
        }

        $_SERVER['SCRIPT_NAME'] = $root.'/index.php';
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    }
}
