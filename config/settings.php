<?php

/*
|--------------------------------------------------------------------------
| Settings schema
|--------------------------------------------------------------------------
| Every configurable option in the CMS is declared here. The admin settings
| screens are generated from this file, so adding a new option means adding
| one array entry - no controller, route or view changes required.
|
| Field types: text, textarea, number, boolean, select, email, url, color,
|              secret, image, code, notice
|
| 'module' ties a group to a module: when the module is disabled the group
| disappears from the admin navigation.
| 'secret' values are encrypted at rest and never echoed back to the browser.
*/

return [

    'general' => [
        'label' => 'General',
        'icon' => 'cog',
        'fields' => [
            'site_name' => ['type' => 'text', 'label' => 'Site name', 'default' => 'Radius', 'rules' => 'required|string|max:120'],
            'site_tagline' => ['type' => 'text', 'label' => 'Tagline', 'default' => 'A fast, modular CMS'],
            'site_email' => ['type' => 'email', 'label' => 'Contact email', 'default' => null],
            'site_phone' => ['type' => 'text', 'label' => 'Contact phone'],
            'site_address' => ['type' => 'textarea', 'label' => 'Address'],
            'site_logo' => ['type' => 'image', 'label' => 'Logo', 'help' => 'Used on light backgrounds. Leave empty to keep the one this CMS ships with.'],
            'site_logo_light' => ['type' => 'image', 'label' => 'Logo for dark backgrounds', 'help' => 'A light-coloured version of your logo, used where the background is dark. Falls back to the logo above, so only upload one if your logo disappears there.'],
            'site_favicon' => ['type' => 'image', 'label' => 'Favicon'],
            'timezone' => ['type' => 'select', 'label' => 'Timezone', 'default' => 'UTC', 'options' => 'timezones'],
            'date_format' => ['type' => 'select', 'label' => 'Date format', 'default' => 'd M Y', 'options' => [
                'd M Y' => '01 Jan 2026',
                'Y-m-d' => '2026-01-01',
                'm/d/Y' => '01/01/2026 (US)',
                'd/m/Y' => '01/01/2026 (EU)',
            ]],
            'locale' => ['type' => 'select', 'label' => 'Language', 'default' => 'en', 'options' => ['en' => 'English']],
            'maintenance_mode' => ['type' => 'boolean', 'label' => 'Maintenance mode', 'default' => false, 'help' => 'Shows a holding page to visitors. Signed-in admins can still browse the site.'],
            'maintenance_message' => ['type' => 'textarea', 'label' => 'Maintenance message', 'default' => 'We are performing scheduled maintenance. Please check back shortly.'],
        ],
    ],

    'appearance' => [
        'label' => 'Appearance',
        'icon' => 'swatch',
        'module' => 'themes',
        'fields' => [
            'active_theme' => ['type' => 'select', 'label' => 'Active theme', 'default' => 'default', 'options' => 'themes'],
            'primary_color' => ['type' => 'color', 'label' => 'Primary color', 'default' => '#2563eb'],
            'dark_mode' => ['type' => 'select', 'label' => 'Color scheme', 'default' => 'system', 'options' => [
                'system' => 'Follow visitor system setting',
                'light' => 'Always light',
                'dark' => 'Always dark',
            ]],
            'posts_per_page' => ['type' => 'number', 'label' => 'Items per page', 'default' => 12, 'rules' => 'integer|min:1|max:100'],
            'custom_css' => ['type' => 'code', 'label' => 'Custom CSS', 'help' => 'Injected into the front-end head.'],
            'custom_js' => ['type' => 'code', 'label' => 'Custom JS', 'help' => 'Injected before the closing body tag.'],
            'header_scripts' => ['type' => 'code', 'label' => 'Header scripts', 'help' => 'Analytics, pixels and verification tags.'],
        ],
    ],

    'search' => [
        'label' => 'Search',
        'icon' => 'search',
        'fields' => [
            'search_engine' => ['type' => 'select', 'label' => 'Search engine', 'default' => 'database', 'options' => 'search_engines',
                'help' => 'Database searches your content directly and needs no setup. Index answers from a prebuilt word index, so searching never slows your database down: better for large sites or heavy traffic. The index is built when you save this, and kept current as you edit.'],
            'search_instant' => ['type' => 'boolean', 'label' => 'Show results while typing', 'default' => true,
                'help' => 'A dropdown of matches appears under search boxes as visitors type. Pressing Enter still opens the full results.'],
            'search_min_chars' => ['type' => 'number', 'label' => 'Start after this many characters', 'default' => 2, 'rules' => 'integer|min:1|max:10'],
            'search_suggest_limit' => ['type' => 'number', 'label' => 'Results per group while typing', 'default' => 5, 'rules' => 'integer|min:1|max:20'],
            'search_posts' => ['type' => 'boolean', 'label' => 'Search blog posts', 'default' => true],
            'search_products' => ['type' => 'boolean', 'label' => 'Search products', 'default' => true],
            'search_pages' => ['type' => 'boolean', 'label' => 'Search pages', 'default' => true],
            'search_page' => ['type' => 'boolean', 'label' => 'Site-wide results page at /search', 'default' => true,
                'help' => 'Lists matches from everything above on one page. Turn it off if you already have a page with the address /search.'],
            'search_rate_limit' => ['type' => 'number', 'label' => 'Live searches allowed per visitor per minute', 'default' => 60, 'rules' => 'integer|min:10|max:1000',
                'help' => 'Protects the site from scripts hammering the search. A person typing uses a handful.'],
            'search_cache_seconds' => ['type' => 'number', 'label' => 'Remember live results for (seconds)', 'default' => 60, 'rules' => 'integer|min:0|max:3600',
                'depends' => ['search_engine' => 'database'],
                'help' => 'Repeated searches are answered without querying the database again. Set to 0 to turn off.'],
        ],
    ],

    'seo' => [
        'label' => 'SEO',
        'icon' => 'search',
        'module' => 'seo',
        'fields' => [
            'meta_title' => ['type' => 'text', 'label' => 'Default meta title', 'default' => 'Radius'],
            'title_separator' => ['type' => 'text', 'label' => 'Title separator', 'default' => '|'],
            'meta_description' => ['type' => 'textarea', 'label' => 'Default meta description'],
            'meta_keywords' => ['type' => 'text', 'label' => 'Default meta keywords'],
            'og_image' => ['type' => 'image', 'label' => 'Default social share image'],
            'schema_type' => ['type' => 'select', 'label' => 'Site schema type', 'default' => 'Organization', 'options' => 'schema_types',
                'help' => 'The schema.org type describing this site as a whole. Posts and products emit their own types on top of this.'],
            'schema_org_name' => ['type' => 'text', 'label' => 'Organization / person name'],
            'schema_org_logo' => ['type' => 'image', 'label' => 'Schema logo'],
            'twitter_handle' => ['type' => 'text', 'label' => 'Twitter / X handle', 'help' => 'Without the @ sign.'],
            'sitemap_enabled' => ['type' => 'boolean', 'label' => 'Generate sitemap.xml', 'default' => true],
            'robots_txt' => ['type' => 'code', 'label' => 'robots.txt', 'default' => "User-agent: *\nDisallow: /admin\nDisallow: /cart\nDisallow: /checkout"],
            'noindex' => ['type' => 'boolean', 'label' => 'Discourage search engines', 'default' => false, 'help' => 'Adds a site-wide noindex tag. Useful while building.'],
            'google_analytics_id' => ['type' => 'text', 'label' => 'Google Analytics ID', 'help' => 'Format: G-XXXXXXXXXX'],
            'google_site_verification' => ['type' => 'text', 'label' => 'Google site verification'],
        ],
    ],

    'mail' => [
        'label' => 'Email',
        'icon' => 'envelope',
        'fields' => [
            'mail_driver' => ['type' => 'select', 'label' => 'Mail provider', 'default' => 'smtp', 'options' => [
                'smtp' => 'SMTP',
                'resend' => 'Resend',
                'ses' => 'Amazon SES',
                'postmark' => 'Postmark',
                'log' => 'Log only (testing)',
            ]],
            'mail_from_address' => ['type' => 'email', 'label' => 'From address', 'default' => null],
            'mail_from_name' => ['type' => 'text', 'label' => 'From name', 'default' => 'Radius'],
            'mail_host' => ['type' => 'text', 'label' => 'SMTP host', 'depends' => ['mail_driver' => 'smtp']],
            'mail_port' => ['type' => 'number', 'label' => 'SMTP port', 'default' => 587, 'depends' => ['mail_driver' => 'smtp']],
            'mail_username' => ['type' => 'text', 'label' => 'SMTP username', 'depends' => ['mail_driver' => 'smtp']],
            'mail_password' => ['type' => 'secret', 'label' => 'SMTP password', 'depends' => ['mail_driver' => 'smtp']],
            'mail_encryption' => ['type' => 'select', 'label' => 'Encryption', 'default' => 'tls', 'options' => [
                'tls' => 'TLS',
                'ssl' => 'SSL',
                'none' => 'None',
            ], 'depends' => ['mail_driver' => 'smtp']],
            'mail_env_notice' => ['type' => 'notice', 'label' => 'API-key providers',
                'help' => 'Resend, SES and Postmark read their keys from the .env file (RESEND_KEY, AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY / AWS_DEFAULT_REGION, POSTMARK_TOKEN). API keys are deliberately kept out of the database.'],
        ],
    ],

    'sms' => [
        'label' => 'SMS',
        'icon' => 'chat',
        'module' => 'sms',
        'fields' => [
            'sms_enabled' => ['type' => 'boolean', 'label' => 'Enable SMS', 'default' => false],
            'sms_driver' => ['type' => 'select', 'label' => 'Provider', 'default' => 'msg91', 'options' => [
                'msg91' => 'MSG91',
                'twilio' => 'Twilio',
                'log' => 'Log only (testing)',
            ]],
            'msg91_auth_key' => ['type' => 'secret', 'label' => 'MSG91 auth key', 'depends' => ['sms_driver' => 'msg91']],
            'msg91_sender_id' => ['type' => 'text', 'label' => 'MSG91 sender ID', 'depends' => ['sms_driver' => 'msg91']],
            'msg91_route' => ['type' => 'text', 'label' => 'MSG91 route', 'default' => '4', 'depends' => ['sms_driver' => 'msg91']],
            'msg91_dlt_te_id' => ['type' => 'text', 'label' => 'MSG91 DLT template ID', 'depends' => ['sms_driver' => 'msg91']],
            'twilio_sid' => ['type' => 'text', 'label' => 'Twilio account SID', 'depends' => ['sms_driver' => 'twilio']],
            'twilio_token' => ['type' => 'secret', 'label' => 'Twilio auth token', 'depends' => ['sms_driver' => 'twilio']],
            'twilio_from' => ['type' => 'text', 'label' => 'Twilio from number', 'depends' => ['sms_driver' => 'twilio']],
            'sms_otp_login' => ['type' => 'boolean', 'label' => 'Allow OTP login', 'default' => false],
        ],
    ],

    'firebase' => [
        'label' => 'Firebase',
        'icon' => 'bell',
        'module' => 'firebase',
        'fields' => [
            'firebase_enabled' => ['type' => 'boolean', 'label' => 'Enable Firebase', 'default' => false],
            'firebase_api_key' => ['type' => 'text', 'label' => 'Web API key'],
            'firebase_auth_domain' => ['type' => 'text', 'label' => 'Auth domain'],
            'firebase_project_id' => ['type' => 'text', 'label' => 'Project ID'],
            'firebase_storage_bucket' => ['type' => 'text', 'label' => 'Storage bucket'],
            'firebase_messaging_sender_id' => ['type' => 'text', 'label' => 'Messaging sender ID'],
            'firebase_app_id' => ['type' => 'text', 'label' => 'App ID'],
            'firebase_measurement_id' => ['type' => 'text', 'label' => 'Measurement ID'],
            'firebase_vapid_key' => ['type' => 'text', 'label' => 'Web push VAPID key'],
            'firebase_credentials_notice' => ['type' => 'notice', 'label' => 'Server credentials',
                'help' => 'To send push notifications, upload your service-account JSON to storage/app/firebase/service-account.json, or point FIREBASE_CREDENTIALS in .env at its path.'],
        ],
    ],

    'social' => [
        'label' => 'Social links',
        'icon' => 'share',
        'fields' => [
            'social_facebook' => ['type' => 'url', 'label' => 'Facebook'],
            'social_instagram' => ['type' => 'url', 'label' => 'Instagram'],
            'social_twitter' => ['type' => 'url', 'label' => 'Twitter / X'],
            'social_linkedin' => ['type' => 'url', 'label' => 'LinkedIn'],
            'social_youtube' => ['type' => 'url', 'label' => 'YouTube'],
            'social_whatsapp' => ['type' => 'text', 'label' => 'WhatsApp number'],
        ],
    ],

    'shop' => [
        'label' => 'Shop',
        'icon' => 'shopping-bag',
        'module' => 'shop',
        'fields' => [
            'shop_currency' => ['type' => 'select', 'label' => 'Currency', 'default' => 'USD', 'options' => 'currencies'],
            'shop_currency_symbol' => ['type' => 'text', 'label' => 'Currency symbol', 'default' => '$',
                'help' => 'Filled in for you when you change the currency above. Override it only if you want prices written a different way.'],
            'shop_currency_position' => ['type' => 'select', 'label' => 'Symbol position', 'default' => 'before', 'options' => [
                'before' => 'Before amount',
                'after' => 'After amount',
            ]],
            'shop_tax_enabled' => ['type' => 'boolean', 'label' => 'Charge tax', 'default' => false],
            'shop_tax_rate' => ['type' => 'number', 'label' => 'Tax rate (%)', 'default' => 0, 'rules' => 'numeric|min:0|max:100'],
            'shop_tax_inclusive' => ['type' => 'boolean', 'label' => 'Prices already include tax', 'default' => false],
            'shop_shipping_flat' => ['type' => 'number', 'label' => 'Flat shipping fee', 'default' => 0, 'rules' => 'numeric|min:0'],
            'shop_free_shipping_over' => ['type' => 'number', 'label' => 'Free shipping over', 'default' => 0, 'rules' => 'numeric|min:0', 'help' => 'Set to 0 to disable free shipping.'],
            'shop_selling_scope' => ['type' => 'select', 'label' => 'Sell to', 'default' => 'world', 'options' => [
                'world' => 'The whole world',
                'selected' => 'Only the countries I choose',
            ]],
            'shop_selling_countries' => ['type' => 'multiselect', 'label' => 'Countries you sell to',
                'options' => 'countries', 'default' => [],
                'depends' => ['shop_selling_scope' => 'selected'],
                'help' => 'Checkout only offers these countries, and refuses an order billed or delivered anywhere else. Leave every box unticked to keep selling worldwide.'],

            'shop_guest_checkout' => ['type' => 'boolean', 'label' => 'Allow guest checkout', 'default' => true],
            'shop_save_addresses' => ['type' => 'boolean', 'label' => 'Remember customer addresses', 'default' => true,
                'help' => 'Fills checkout in from the address a signed-in customer used last time, and lets them keep an address book in their account.'],
            'shop_stock_management' => ['type' => 'boolean', 'label' => 'Track stock levels', 'default' => true],
            'shop_low_stock_threshold' => ['type' => 'number', 'label' => 'Low stock threshold', 'default' => 5],
            'shop_order_prefix' => ['type' => 'text', 'label' => 'Order number prefix', 'default' => 'ORD-'],
            'shop_terms_page' => ['type' => 'text', 'label' => 'Terms page slug', 'default' => 'terms'],

            'shop_download_limit' => ['type' => 'number', 'label' => 'Downloads per purchase', 'default' => 5,
                'rules' => 'integer|min:0', 'help' => 'How many times a customer may download a file they bought. Set to 0 for no limit.'],
            'shop_download_days' => ['type' => 'number', 'label' => 'Download access expires after (days)', 'default' => 0,
                'rules' => 'integer|min:0', 'help' => 'Counted from the date the order was paid. Set to 0 to let customers download forever.'],
        ],
    ],

    // Read through App\Cms\Shop\CheckoutFields, which also enforces them on
    // the server - a hidden field is dropped, a required one refused if blank.
    'checkout' => [
        'label' => 'Checkout',
        'icon' => 'credit-card',
        'module' => 'shop',
        'fields' => [
            'checkout_fields_notice' => ['type' => 'notice', 'label' => 'Checkout fields',
                'help' => 'Choose what checkout asks your customers for. Email address and full name are always required. Country stays required while you sell only to chosen countries. If every address field is hidden, the "ship to a different address" option is removed too - useful for a shop that only sells downloads. The bundled themes and the builder Checkout widget follow these settings; a third-party theme must support them before you make a field it does not show required.'],
            'checkout_field_phone' => ['type' => 'select', 'label' => 'Phone', 'default' => 'optional', 'options' => 'checkout_field_modes', 'rules' => 'in:required,optional,hidden'],
            'checkout_field_line1' => ['type' => 'select', 'label' => 'Street address', 'default' => 'required', 'options' => 'checkout_field_modes', 'rules' => 'in:required,optional,hidden'],
            'checkout_field_line2' => ['type' => 'select', 'label' => 'Apartment, suite, unit', 'default' => 'optional', 'options' => 'checkout_field_modes', 'rules' => 'in:required,optional,hidden'],
            'checkout_field_city' => ['type' => 'select', 'label' => 'City', 'default' => 'required', 'options' => 'checkout_field_modes', 'rules' => 'in:required,optional,hidden'],
            'checkout_field_state' => ['type' => 'select', 'label' => 'State / region', 'default' => 'optional', 'options' => 'checkout_field_modes', 'rules' => 'in:required,optional,hidden'],
            'checkout_field_postcode' => ['type' => 'select', 'label' => 'Postcode / ZIP', 'default' => 'optional', 'options' => 'checkout_field_modes', 'rules' => 'in:required,optional,hidden'],
            'checkout_field_country' => ['type' => 'select', 'label' => 'Country', 'default' => 'required', 'options' => 'checkout_field_modes', 'rules' => 'in:required,optional,hidden'],
            'checkout_field_customer_note' => ['type' => 'select', 'label' => 'Order notes', 'default' => 'optional', 'options' => 'checkout_field_modes', 'rules' => 'in:required,optional,hidden'],
        ],
    ],

    'advanced' => [
        'label' => 'Advanced',
        'icon' => 'wrench',
        'fields' => [
            'cache_enabled' => ['type' => 'boolean', 'label' => 'Cache rendered pages', 'default' => false, 'help' => 'Caches public HTML for signed-out visitors. Leave off while developing.'],
            'cache_ttl' => ['type' => 'number', 'label' => 'Cache lifetime (seconds)', 'default' => 600],
            'recaptcha_enabled' => ['type' => 'boolean', 'label' => 'Enable reCAPTCHA v3', 'default' => false],
            'recaptcha_site_key' => ['type' => 'text', 'label' => 'reCAPTCHA site key'],
            'recaptcha_secret_key' => ['type' => 'secret', 'label' => 'reCAPTCHA secret key'],
            'registration_enabled' => ['type' => 'boolean', 'label' => 'Allow public registration', 'default' => true],
            'email_verification' => ['type' => 'boolean', 'label' => 'Require email verification', 'default' => false],
            'force_https' => ['type' => 'boolean', 'label' => 'Force HTTPS', 'default' => false],
            'admin_2fa_required' => ['type' => 'boolean', 'label' => 'Require 2FA for admin accounts', 'default' => false],
            'activity_log_retention_days' => ['type' => 'number', 'label' => 'Delete activity log entries after (days)', 'default' => 45,
                'rules' => 'integer|min:0|max:3650',
                'help' => 'Older entries in System → Activity are removed automatically to save disk space. Set to 0 to keep them forever.'],
        ],
    ],
];
