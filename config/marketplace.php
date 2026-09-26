<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Theme marketplace
    |--------------------------------------------------------------------------
    | Appearance -> Browse themes reads a catalogue of free themes from a JSON
    | file hosted by the publisher, and installs them with one click. See the
    | README for the catalogue format.
    |
    | Every archive installed from here goes through exactly the same checks as
    | a theme ZIP uploaded by hand, so turning this on does not relax anything.
    | Turning it off removes the screen and stops every outbound request it
    | makes.
    */

    'enabled' => env('CMS_MARKETPLACE_ENABLED', true),

    // A fork, or anyone running their own theme directory, points this at
    // their own catalogue.
    'catalogue_url' => env('CMS_MARKETPLACE_URL', 'https://www.insertcart.com/marketplace/themes.json'),

    // The plugin directory: the same catalogue format, listing plugins instead
    // of themes. System -> Browse plugins reads it. Empty turns that screen off
    // while leaving the theme directory alone.
    'plugins_catalogue_url' => env('CMS_PLUGIN_MARKETPLACE_URL', 'https://www.insertcart.com/marketplace/modules.json'),

    // Matches the limit on uploading a plugin by hand (cms.plugins.max_upload_kb).
    'plugins_max_download_bytes' => 20 * 1024 * 1024,

    // How long the catalogue is remembered. It is only fetched while an
    // administrator is using the marketplace, or when a theme installed from it
    // needs checking for updates.
    'cache_hours' => env('CMS_MARKETPLACE_CACHE_HOURS', 12),

    /*
    | The catalogue formats this release understands. A catalogue declaring a
    | higher number is refused with a plain explanation rather than guessed at.
    */
    'supported_format' => 1,

    /*
    |--------------------------------------------------------------------------
    | Download safety
    |--------------------------------------------------------------------------
    | A theme is Blade, and Blade compiles to PHP, so these are the same
    | guarantees the core updater insists on. Switch them off only for local
    | testing against a server that cannot offer https or publish a hash.
    */

    'require_checksum' => env('CMS_MARKETPLACE_REQUIRE_CHECKSUM', true),
    'require_https' => env('CMS_MARKETPLACE_REQUIRE_HTTPS', true),

    // Matches the limit on uploading a theme by hand (cms.themes.max_upload_kb).
    'max_download_bytes' => 40 * 1024 * 1024,

    'http_timeout' => 120,

    // Hosts theme archives may be downloaded from, checked on every redirect
    // too. Empty allows any public https host. Addresses on this server's own
    // network are always refused, whatever this says.
    'allowed_download_hosts' => [],

    // Screenshots are shown straight from the catalogue's server. Set false to
    // keep the admin panel from requesting third-party images at all.
    'remote_images' => env('CMS_MARKETPLACE_REMOTE_IMAGES', true),

];
