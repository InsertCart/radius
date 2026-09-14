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
            'public/favicon.ico',
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
];
