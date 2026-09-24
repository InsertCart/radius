<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bundle format
    |--------------------------------------------------------------------------
    | The version stamped into every export's manifest.json, and the highest
    | one an import will accept. A bundle declaring a higher number is refused
    | with "made by a newer Radius" rather than guessed at, which is what lets
    | the shape of a record change later without corrupting an old site's data.
    */

    'format' => 1,

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    | An export from a large site is big, and a WordPress WXR file for a blog
    | with ten years of posts is bigger. These are the ceilings for what the
    | browser may hand over; anything larger belongs on the command line, where
    | there is no upload limit and no request timeout.
    */

    'max_upload_kb' => env('CMS_TRANSFER_MAX_UPLOAD_KB', 102400), // 100 MB

    // Guards against a zip bomb dressed up as a content bundle.
    'max_archive_entries' => 60000,
    'max_uncompressed_bytes' => 2 * 1024 * 1024 * 1024,

    /*
    |--------------------------------------------------------------------------
    | Where working files live
    |--------------------------------------------------------------------------
    | Uploaded bundles and half-finished exports are written here. It is under
    | storage/app/private, so nothing in it is reachable over the web even for
    | the minutes it exists.
    */

    'workspace' => storage_path('app/private/transfer'),

    // An upload left behind by an abandoned import is deleted after this long.
    'keep_uploads_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Pulling images from another site
    |--------------------------------------------------------------------------
    | A WordPress export names its images by URL rather than shipping them, so
    | importing one means fetching files from an address in somebody else's
    | file. That is a request this server makes on a stranger's instruction, so
    | private and link-local addresses are refused: without that, an import
    | file is a way to make this server read its own cloud metadata endpoint.
    */

    'download' => [
        'enabled' => env('CMS_TRANSFER_DOWNLOAD_MEDIA', true),
        'timeout' => 30,
        'max_bytes' => 25 * 1024 * 1024,
        'block_private_hosts' => env('CMS_TRANSFER_BLOCK_PRIVATE_HOSTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Run limits
    |--------------------------------------------------------------------------
    | How long an import running off a web request is allowed to take before it
    | stops and reports what it managed. The command line ignores this: there
    | is no browser waiting there, so a big site can take the hour it needs.
    */

    'web_time_limit' => env('CMS_TRANSFER_TIME_LIMIT', 600),

    // Records read into memory at a time while a bundle is being imported.
    'chunk' => 200,

    // Lines the report keeps per type before it stops collecting detail. A
    // failed import of 40,000 posts should not itself exhaust the memory limit.
    'max_notes' => 50,
];
