<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where releases come from
    |--------------------------------------------------------------------------
    | A JSON file you host and edit by hand. See README for its format.
    |
    | HTTPS is required for the download, and not as a formality: the archive
    | becomes PHP that runs on this server, so anyone able to tamper with the
    | response owns the site.
    */

    'enabled' => env('CMS_UPDATES_ENABLED', true),
    'manifest_url' => env('CMS_UPDATE_URL'),

    /*
    | How long a version check is cached. The check is cheap but it runs off an
    | admin page load, and nobody needs to know within the hour.
    */
    'check_interval_hours' => env('CMS_UPDATE_CHECK_HOURS', 24),

    /*
    | The manifest formats this release understands. A manifest declaring a
    | higher number is refused with "update manually" rather than guessed at,
    | which is what lets the format change later without breaking old sites.
    */
    'supported_format' => 1,

    /*
    |--------------------------------------------------------------------------
    | Download safety
    |--------------------------------------------------------------------------
    */

    // Turning this off means installing code nobody verified. It exists only
    // for local testing against a file server that cannot publish a hash.
    'require_checksum' => env('CMS_UPDATE_REQUIRE_CHECKSUM', true),
    'require_https' => env('CMS_UPDATE_REQUIRE_HTTPS', true),

    'max_download_bytes' => 300 * 1024 * 1024,
    'max_archive_entries' => 30000,
    'max_uncompressed_bytes' => 600 * 1024 * 1024,

    'http_timeout' => 900,

    /*
    |--------------------------------------------------------------------------
    | What an update is allowed to write
    |--------------------------------------------------------------------------
    | The applier works from these lists and nothing else. A path that appears
    | in neither is never written, so even a malformed or hostile release
    | cannot reach the database credentials, the uploads or a bought theme.
    |
    | Paths are relative to the project root and use forward slashes.
    */

    'paths' => [

        /*
         * Replaced wholesale: the old directory is moved aside and the new one
         * takes its place. Necessary wherever a file deleted in the new release
         * must not survive - a stale class in app/ or an orphaned package in
         * vendor/ breaks the autoloader in ways that are miserable to debug.
         */
        'replace' => [
            'app',
            'bootstrap/app.php',
            'bootstrap/providers.php',
            'database/migrations',
            'public/build',
            'resources',
            'routes',
            'vendor',
        ],

        /*
         * Merged file by file. Files the release does not mention are left
         * alone, because a buyer may legitimately have added their own - an
         * extra config file, a second stylesheet - and an update has no
         * business deleting them.
         */
        'merge' => [
            '.env.example',
            '.htaccess',
            'artisan',
            'composer.json',
            'composer.lock',
            'config',
            'package.json',
            'public/.htaccess',
            'public/favicon',
            'public/favicon-light',
            'public/favicon.ico',
            'public/images',
            'public/index.php',
            'public/robots.txt',
            'themes/default',
            'vite.config.js',
        ],

        /*
         * Never written, whatever a release contains. Belt and braces: nothing
         * here is in the two lists above, but stating it makes the intent
         * checkable and gives the applier something to assert against.
         */
        'protected' => [
            '.env',
            'bootstrap/cache',
            'public/storage',
            'public/themes',
            'storage',
        ],

        /*
         * Files in the merge set whose local edits should be preserved. Hashes
         * are recorded after each update; anything that no longer matches was
         * changed on this site and is skipped unless the admin says otherwise.
         *
         * Deliberately not app/ or vendor/ - those are replaced wholesale, and
         * hashing tens of thousands of vendor files on every update would cost
         * more than it could ever save.
         */
        'track_edits' => [
            'config',
            'themes/default',
            'public/.htaccess',
            'public/index.php',
            '.htaccess',

            // The shipped logo and icons. Replacing these files in place is a
            // documented way to rebrand, so an update that overwrote them would
            // silently put our logo back on somebody else's site.
            'public/favicon',
            'public/favicon-light',
            'public/favicon.ico',
            'public/images',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backups
    |--------------------------------------------------------------------------
    | Written to the private disk, which has no public URL - a database dump in
    | a web-reachable folder would be a worse problem than the one backups
    | solve.
    */

    'backups' => [
        'directory' => 'backups',
        'keep' => 5,

        // Rows read per query when dumping. Lower uses less memory on shared
        // hosting; higher is faster on a real server.
        'dump_chunk' => 500,
    ],

    /*
    | Where the downloaded archive and the unpacked staging tree live while an
    | update is in progress. Also on the private disk.
    */
    'workspace' => 'updates',

    /*
    |--------------------------------------------------------------------------
    | Building a release (php artisan cms:release)
    |--------------------------------------------------------------------------
    | What goes into the ZIP that buyers download and the updater installs.
    |
    | A checkout from version control is NOT a release: vendor/ and
    | public/build are both ignored by git, so an archive made from the repo
    | cannot even boot. This list is what turns a working tree into something
    | somebody else can extract and run.
    */

    'package' => [

        /*
         * Never included. Each of these is either specific to this machine, or
         * would actively break the buyer's install.
         *
         * storage/installed is the one that matters most: it is the lock that
         * tells the CMS setup is finished. Ship it and the buyer's setup wizard
         * never opens, which looks exactly like a broken product.
         */
        'exclude' => [
            '.git',
            '.github',
            '.env',
            '.env.backup',
            '.env.production',
            '.phpunit.result.cache',
            'node_modules',
            'tests',
            'phpunit.xml',
            'public/hot',
            'public/storage',
            'storage/installed',
            'storage/app/shipped-checksums.json',
            'storage/app/update-pending.json',
            'storage/app/update-last.json',
        ],

        /*
         * Directories shipped as empty scaffolding. The folder and its
         * .gitignore/.htaccess are kept - the buyer needs the structure and the
         * Apache rules - but the contents are this site's data, not the
         * product's.
         */
        'empty' => [
            'bootstrap/cache',
            'public/themes',
            'storage/app/private',
            'storage/app/public',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/testing',
            'storage/framework/views',
            'storage/logs',
        ],

        /*
         * Must exist, or the archive is not a working release. vendor/ and
         * public/build are both absent from a fresh checkout, and a buyer
         * cannot produce either without Composer and npm.
         */
        'required' => [
            'vendor/autoload.php',
            'public/build/manifest.json',
            'public/index.php',
            'config/cms.php',
            'artisan',
        ],
    ],
];
