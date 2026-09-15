<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product identity
    |--------------------------------------------------------------------------
    | Shown in the admin footer and the installer. Buyers may rebrand freely.
    */

    'name' => 'Radius',
    'version' => '1.1.0',

    /*
    |--------------------------------------------------------------------------
    | Admin panel
    |--------------------------------------------------------------------------
    | The admin prefix lives in .env so a buyer can obscure the login URL
    | without touching code.
    */

    'admin_prefix' => env('CMS_ADMIN_PREFIX', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Installer
    |--------------------------------------------------------------------------
    | The installer locks itself by writing storage/installed once it finishes.
    | Deleting that file re-opens the wizard.
    */

    'install_lock' => storage_path('installed'),

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    | Every optional feature is a module. Modules can be toggled from the admin
    | panel; a disabled module registers no routes, no menu entries and no view
    | composers, so it costs nothing at runtime.
    |
    | 'core' modules cannot be disabled.
    */

    'modules' => [
        'blog' => [
            'name' => 'Blog',
            'description' => 'Posts, categories, tags and comments.',
            'icon' => 'newspaper',
            'core' => false,
        ],
        'pages' => [
            'name' => 'Pages',
            'description' => 'Static pages with a block builder.',
            'icon' => 'document',
            'core' => true,
        ],
        'shop' => [
            'name' => 'eCommerce',
            'description' => 'Products, cart, checkout, orders and coupons.',
            'icon' => 'shopping-bag',
            'core' => false,
            'requires' => ['payments'],
        ],
        'payments' => [
            'name' => 'Payments',
            'description' => 'PayPal, Stripe, Razorpay, PayU, Cashfree and Wise.',
            'icon' => 'credit-card',
            'core' => false,
        ],
        'sms' => [
            'name' => 'SMS',
            'description' => 'Transactional SMS through MSG91 or Twilio.',
            'icon' => 'chat',
            'core' => false,
        ],
        'firebase' => [
            'name' => 'Firebase',
            'description' => 'Web push notifications and client SDK config.',
            'icon' => 'bell',
            'core' => false,
        ],
        'seo' => [
            'name' => 'SEO',
            'description' => 'Meta tags, sitemap.xml, robots.txt and schema.org.',
            'icon' => 'search',
            'core' => false,
        ],
        'media' => [
            'name' => 'Media library',
            'description' => 'Uploads, thumbnails and file management.',
            'icon' => 'photo',
            'core' => true,
        ],
        'themes' => [
            'name' => 'Themes',
            'description' => 'Upload, install and activate front-end templates.',
            'icon' => 'swatch',
            'core' => true,
        ],
        'users' => [
            'name' => 'Users',
            'description' => 'Customer accounts, roles and two-factor auth.',
            'icon' => 'users',
            'core' => true,
        ],
        'contact' => [
            'name' => 'Contact forms',
            'description' => 'Front-end contact form and submission inbox.',
            'icon' => 'envelope',
            'core' => false,
        ],
        'newsletter' => [
            'name' => 'Newsletter',
            'description' => 'Email subscriber capture and export.',
            'icon' => 'inbox',
            'core' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Themes
    |--------------------------------------------------------------------------
    */

    'themes' => [
        'path' => base_path('themes'),
        'default' => 'default',
        'asset_url' => 'themes',

        // A theme ZIP may only contain these extensions. Everything else is
        // dropped during extraction — this is the main defence against a
        // malicious template shipping a PHP web shell.
        'allowed_extensions' => [
            'blade.php', 'json', 'css', 'js', 'map', 'svg', 'png', 'jpg', 'jpeg',
            'gif', 'webp', 'avif', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'otf',
            'md', 'txt', 'mp4', 'webm',
        ],

        'max_upload_kb' => 40960, // 40 MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    */

    'media' => [
        'disk' => env('CMS_MEDIA_DISK', 'public'),
        'max_upload_kb' => 10240,
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'pdf', 'mp4', 'webm', 'zip', 'doc', 'docx', 'xls', 'xlsx'],
        'image_mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'],
        'thumbnails' => [
            'thumb' => [320, 320],
            'medium' => [768, 768],
            'large' => [1600, 1600],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paid downloads
    |--------------------------------------------------------------------------
    | Files sold as digital products. These never go on the media disk: that
    | one is served straight off the web server, so anybody who guessed or was
    | told the address would get the file without paying for it.
    |
    | They are stored on a disk with no public URL and streamed by a controller
    | that checks the order first. If you point this at S3, keep the bucket
    | private — the whole arrangement depends on it.
    */

    'downloads' => [
        'disk' => env('CMS_DOWNLOADS_DISK', 'private'),
        'directory' => 'downloads',
        'max_upload_kb' => env('CMS_DOWNLOADS_MAX_KB', 262144), // 256 MB

        'allowed_extensions' => [
            'zip', 'rar', '7z', 'gz', 'pdf', 'epub',
            'mp3', 'wav', 'm4a', 'ogg', 'mp4', 'webm',
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'csv', 'rtf', 'psd',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */

    'security' => [
        'login_max_attempts' => 5,
        'login_decay_minutes' => 5,
        'two_factor_window' => 1,      // ±30s clock drift tolerance
        'recovery_code_count' => 8,
    ],
];
