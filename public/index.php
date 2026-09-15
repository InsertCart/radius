<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
//
// A copy taken from version control has no vendor/ - it is build output, and
// deliberately not committed - so this is where such a copy stops. Left as a
// bare require it stops with "Failed to open stream", which says nothing about
// what to do next, and the same question gets asked every time.
if (! is_file(__DIR__.'/../vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    exit('<!doctype html><meta charset="utf-8"><title>Dependencies missing</title>'
        .'<div style="font:16px/1.6 system-ui,sans-serif;max-width:40rem;margin:4rem auto;padding:0 1.5rem;color:#0f172a">'
        .'<h1 style="font-size:1.25rem">This copy is not ready to run</h1>'
        .'<p>The <code>vendor</code> folder is missing, so none of the application code can load.</p>'
        .'<p><strong>If you downloaded this from a source repository</strong>, it is not a release. '
        .'Install the dependencies first:</p>'
        .'<pre style="background:#f1f5f9;padding:1rem;border-radius:.5rem;overflow:auto">'
        .'composer install --no-dev --optimize-autoloader'.PHP_EOL
        .'npm install &amp;&amp; npm run build</pre>'
        .'<p><strong>If you bought this product</strong>, the download you were given should already '
        .'contain <code>vendor</code>. Re-download it rather than copying files between folders, and '
        .'make sure your extraction tool did not skip any.</p>'
        .'</div>');
}

require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
