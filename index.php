<?php

/*
|--------------------------------------------------------------------------
| Wrong-document-root guard
|--------------------------------------------------------------------------
| This file only ever runs when a web server has been pointed at the project
| folder instead of the public/ folder inside it.
|
| On Apache that never happens: the .htaccess beside this file rewrites every
| request into public/ before a directory index is considered. nginx does not
| read .htaccess, so there the server looks for an index here, and until this
| file existed it found nothing and answered "403 Forbidden" - which tells the
| site owner precisely nothing.
|
| It deliberately does NOT boot the application. It could - public/index.php is
| one require away - but on nginx that would mean serving the site with the
| whole project inside the web root, where .env is a plain file the server will
| hand to anyone who asks for it. No code running in PHP can prevent that,
| because nginx serves static files without ever consulting PHP. A CMS that
| quietly worked in that state would be shipping a credential leak to exactly
| the people least likely to notice it.
|
| So: one screen, in plain language, naming the single setting to change.
*/

$projectRoot = __DIR__;
$hasVendor = is_file($projectRoot.'/vendor/autoload.php');
$hasBuild = is_file($projectRoot.'/public/build/manifest.json');

// Best guess at what to tell them to type, so the instructions are concrete
// rather than a template they have to translate.
$documentRoot = rtrim(strtr((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), DIRECTORY_SEPARATOR, '/'), '/');
$suggested = $documentRoot !== '' ? $documentRoot.'/public' : '/path/to/this/folder/public';

$server = (string) ($_SERVER['SERVER_SOFTWARE'] ?? '');
$isNginx = stripos($server, 'nginx') !== false;

// Answered as 200, not 500. This is a setup instruction, not a server fault,
// and the whole value of the page is that somebody reads it - control panels,
// CDNs and some browsers replace a 5xx body with an error page of their own,
// which would put the bare 403 back in a different costume.
http_response_code(200);
header('Content-Type: text/html; charset=utf-8');

?><!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Almost there &mdash; one setting to change</title>
<style>
    :root { color-scheme: light dark; }
    body { font: 16px/1.65 system-ui, -apple-system, "Segoe UI", sans-serif;
           max-width: 44rem; margin: 0 auto; padding: 3rem 1.5rem; color: #0f172a; background: #fff; }
    h1 { font-size: 1.4rem; margin: 0 0 .4rem; }
    h2 { font-size: 1.05rem; margin: 2rem 0 .5rem; }
    .lead { color: #475569; margin: 0 0 2rem; }
    ol { padding-left: 1.2rem; }
    li { margin: .55rem 0; }
    code { background: #f1f5f9; padding: .12rem .4rem; border-radius: .25rem; font-size: .9em; }
    pre { background: #0f172a; color: #e2e8f0; padding: 1rem; border-radius: .5rem; overflow-x: auto; font-size: .85rem; }
    .box { border: 1px solid #e2e8f0; border-radius: .6rem; padding: 1rem 1.25rem; margin: 1.25rem 0; }
    .warn { border-color: #fca5a5; background: #fef2f2; }
    .why { color: #64748b; font-size: .92rem; }
    @media (prefers-color-scheme: dark) {
        body { background: #0b1120; color: #e2e8f0; }
        code { background: #1e293b; }
        .box { border-color: #1e293b; }
        .warn { border-color: #7f1d1d; background: #1f1315; }
        .lead, .why { color: #94a3b8; }
    }
</style>

<h1>Almost there &mdash; one setting to change</h1>
<p class="lead">
    Your web server is pointing at the wrong folder. Everything is installed correctly;
    it just needs to serve <code>public</code> rather than the folder above it.
</p>

<div class="box">
    <strong>Set your site&rsquo;s document root to:</strong>
    <pre><?= htmlspecialchars($suggested, ENT_QUOTES) ?></pre>
</div>

<?php if ($isNginx): ?>
    <h2>On CloudPanel</h2>
    <ol>
        <li>Open your site, then <strong>Settings</strong>.</li>
        <li>Find <strong>Site Root</strong> and add <code>/public</code> to the end of it.</li>
        <li>Save. CloudPanel reloads nginx for you.</li>
        <li>Reload this page.</li>
    </ol>
    <p class="why">
        Other panels call the same setting <em>Document Root</em>, <em>Web Root</em> or
        <em>Public Directory</em>. If you edit nginx by hand, it is the <code>root</code> line
        in your server block &mdash; and <code>nginx.conf.example</code> in this folder is a
        complete, commented one you can copy.
    </p>
<?php else: ?>
    <h2>What to change</h2>
    <ol>
        <li>In your hosting control panel, find <strong>Document Root</strong> (sometimes
            <em>Site Root</em> or <em>Web Root</em>) for this domain.</li>
        <li>Add <code>/public</code> to the end of it.</li>
        <li>Save, then reload this page.</li>
    </ol>
<?php endif; ?>

<h2>Why this matters</h2>
<p class="why">
    Serving the folder above <code>public</code> puts your configuration file inside the web
    root. That file holds your database password and the key that signs every login session,
    and the web server will hand it to anyone who requests it &mdash; no code running here can
    stop that. Pointing the root at <code>public</code> puts it out of reach entirely.
</p>

<?php if (! $hasVendor || ! $hasBuild): ?>
    <div class="box warn">
        <strong>This copy is also missing files it needs to run.</strong>
        <p style="margin:.5rem 0 0">
            <?php if (! $hasVendor): ?><code>vendor/</code><?php endif; ?>
            <?php if (! $hasVendor && ! $hasBuild): ?> and <?php endif; ?>
            <?php if (! $hasBuild): ?><code>public/build/</code><?php endif; ?>
            <?= (! $hasVendor && ! $hasBuild) ? ' are' : ' is' ?> not here.
        </p>
        <p style="margin:.5rem 0 0">
            That happens when the code is downloaded from the source repository rather than
            from a published release. A source download is not a runnable copy: those folders
            are built, not stored in version control. Download the <strong>release ZIP</strong>
            from the project&rsquo;s Releases page &mdash; the file named
            <code>radius-&lt;version&gt;.zip</code>, not &ldquo;Source code (zip)&rdquo;.
        </p>
    </div>
<?php endif; ?>
