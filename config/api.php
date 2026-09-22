<?php

/*
|--------------------------------------------------------------------------
| Mobile API
|--------------------------------------------------------------------------
| The JSON API a mobile app talks to. It belongs to the 'api' module, which
| ships switched off: a site that never builds an app never answers a single
| API request, and the routes are not even registered.
|
| Everything below describes what the API *can* expose. What it *does* expose
| is the site owner's decision, made under Admin -> Mobile API, one toggle per
| group of endpoints. That screen is generated from the catalogue in this
| file, so adding a group of endpoints means adding one entry here.
|
| Nothing in this API reaches the admin panel. There is no endpoint that
| publishes content, changes a setting, reads another customer's data or
| touches an order that does not belong to the caller. It is the storefront,
| in JSON, and nothing else.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Address
    |--------------------------------------------------------------------------
    | Every endpoint lives under /<prefix>/<version>. The prefix can be moved
    | in .env for a site that already serves something at /api.
    */

    'prefix' => env('CMS_API_PREFIX', 'api'),
    'version' => 'v1',

    /*
    |--------------------------------------------------------------------------
    | Defaults for the security options
    |--------------------------------------------------------------------------
    | Each of these is stored as a setting and edited under Admin -> Mobile API.
    | The values here are what a fresh install starts with.
    */

    'security' => [

        // Require every request to be signed with the app's secret
        // (HMAC-SHA256) rather than simply to carry it. Signing keeps the
        // secret off the wire and makes a captured request useless a few
        // minutes later. Off by default because it is work for whoever writes
        // the app; on is better.
        'signature_required' => false,

        // How long a signed request stays valid, in seconds. Also how long a
        // nonce is remembered, which is what stops the same signed request
        // being replayed.
        'signature_window' => 300,

        // Access tokens are the short-lived half and are refreshed; refresh
        // tokens are the long-lived half and rotate on every use.
        'token_days' => 30,
        'refresh_days' => 90,

        // Requests per minute, per app per IP address.
        'rate_limit' => 60,

        // Sign-in and sign-up attempts per minute, per IP address.
        // Deliberately mean.
        'auth_rate_limit' => 10,

        // Whether an admin or editor account may sign in through the API.
        // Off: the API serves customers. A staff password should not be usable
        // from a device that cannot show the admin panel, and a stolen app
        // credential should not get anywhere near a staff account.
        'allow_staff' => false,

        // Whether somebody who has not signed in may keep a cart. Off means
        // the app must ask for an account before the first "add to basket".
        'guest_cart' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Endpoint groups
    |--------------------------------------------------------------------------
    | 'module'   - the CMS module this group belongs to. With that module off
    |              the group cannot be switched on at all.
    | 'default'  - whether it is on the first time the API is switched on.
    | 'requires' - groups this one cannot work without. Switching this one on
    |              switches those on too; switching one of those off switches
    |              this one off.
    | 'enables'  - groups switched on alongside this one, but not switched off
    |              with it. This is how the shop brings customer accounts and
    |              registration along with it.
    | 'auth'     - 'required' when every endpoint in the group needs a signed-in
    |              customer, 'optional' when some do, 'none' when none do.
    |
    | The endpoint lists are documentation: they are printed on the admin
    | screen so an owner can see exactly what a group opens up. The routes
    | themselves are in routes/api.php.
    */

    'features' => [

        'auth' => [
            'name' => 'Sign in & customer account',
            'description' => 'Lets a customer sign in from the app and manage their own profile. The tokens issued here are what every other signed-in endpoint uses.',
            'module' => 'users',
            'default' => true,
            'auth' => 'optional',
            'endpoints' => [
                'POST /auth/login' => 'Exchange an email and password for an access token.',
                'POST /auth/two-factor' => 'Finish a sign-in that needs a 2FA code.',
                'POST /auth/refresh' => 'Swap a refresh token for a new access token.',
                'POST /auth/logout' => 'Revoke the token this device is holding.',
                'GET /auth/me' => 'The signed-in customer.',
                'PATCH /auth/me' => 'Change their own name or phone number.',
                'PUT /auth/password' => 'Change their own password.',
                'GET /auth/devices' => 'Every device signed in to this account.',
                'DELETE /auth/devices/{id}' => 'Sign one of those devices out.',
            ],
        ],

        'registration' => [
            'name' => 'Customer registration',
            'description' => 'New customers can create an account from the app, reset a forgotten password and ask for another verification email. Switched on with the shop: a shop nobody can sign up to is a shop nobody buys from twice.',
            'module' => 'users',
            'default' => true,
            'requires' => ['auth'],
            'auth' => 'none',
            'endpoints' => [
                'POST /auth/register' => 'Create a customer account.',
                'POST /auth/forgot-password' => 'Email a password reset link.',
                'POST /auth/resend-verification' => 'Send the verification email again.',
            ],
        ],

        'posts' => [
            'name' => 'Blog posts',
            'description' => 'Published posts with their categories and tags. Read-only.',
            'module' => 'blog',
            'default' => true,
            'auth' => 'none',
            'endpoints' => [
                'GET /posts' => 'Published posts, newest first, paginated.',
                'GET /posts/{slug}' => 'One post with its body, author and tags.',
                'GET /post-categories' => 'Blog categories.',
                'GET /post-tags' => 'Tags in use.',
            ],
        ],

        'comments' => [
            'name' => 'Post comments',
            'description' => 'Readers can comment from the app. Comments arrive exactly as they do on the site: held until you approve them.',
            'module' => 'blog',
            'default' => false,
            'requires' => ['posts'],
            'auth' => 'optional',
            'endpoints' => [
                'GET /posts/{slug}/comments' => 'Approved comments on a post.',
                'POST /posts/{slug}/comments' => 'Leave a comment.',
            ],
        ],

        'pages' => [
            'name' => 'Pages',
            'description' => 'Published pages - about, terms, privacy - so the app can show them without opening a browser.',
            'module' => 'pages',
            'default' => true,
            'auth' => 'none',
            'endpoints' => [
                'GET /pages' => 'Published pages.',
                'GET /pages/{slug}' => 'One page with its content.',
            ],
        ],

        'shop' => [
            'name' => 'Shop',
            'description' => 'The whole storefront: products, categories, cart, coupons, checkout, the payment hand-off, and the customer\'s own orders and addresses.',
            'module' => 'shop',
            'default' => false,
            'enables' => ['auth', 'registration'],
            'auth' => 'optional',
            'endpoints' => [
                'GET /products' => 'Published products, with search, filters and sorting.',
                'GET /products/{slug}' => 'One product with its variants and gallery.',
                'GET /product-categories' => 'Shop categories.',
                'GET /cart' => 'The current cart and its totals.',
                'POST /cart/items' => 'Add a product to the cart.',
                'PATCH /cart/items/{id}' => 'Change a line quantity.',
                'DELETE /cart/items/{id}' => 'Remove a line.',
                'POST /cart/coupon' => 'Apply a coupon code.',
                'DELETE /cart/coupon' => 'Remove the coupon.',
                'GET /checkout' => 'What checkout needs: totals, payment methods, countries.',
                'POST /checkout' => 'Place the order and get back where to pay.',
                'GET /orders' => 'The signed-in customer\'s orders.',
                'GET /orders/{number}' => 'One of their orders, with its payment state.',
                'GET /addresses' => 'Their address book.',
                'POST /addresses' => 'Add an address.',
                'PATCH /addresses/{id}' => 'Change one of their addresses.',
                'DELETE /addresses/{id}' => 'Delete one of their addresses.',
            ],
        ],

        'reviews' => [
            'name' => 'Product reviews',
            'description' => 'Customers can rate and review a product from the app. Reviews wait for approval, exactly as they do on the site.',
            'module' => 'shop',
            'default' => false,
            'requires' => ['shop'],
            'auth' => 'required',
            'endpoints' => [
                'GET /products/{slug}/reviews' => 'Approved reviews for a product.',
                'POST /products/{slug}/reviews' => 'Leave a review.',
            ],
        ],

        'search' => [
            'name' => 'Search',
            'description' => 'One search across whatever Settings -> Search has switched on.',
            'default' => true,
            'auth' => 'none',
            'endpoints' => [
                'GET /search' => 'Search posts, pages and products.',
            ],
        ],

        'contact' => [
            'name' => 'Contact form',
            'description' => 'The app can send a message to the site\'s inbox.',
            'module' => 'contact',
            'default' => false,
            'auth' => 'none',
            'endpoints' => [
                'POST /contact' => 'Send a contact message.',
            ],
        ],

        'newsletter' => [
            'name' => 'Newsletter',
            'description' => 'Subscribe an email address to the list.',
            'module' => 'newsletter',
            'default' => false,
            'auth' => 'none',
            'endpoints' => [
                'POST /newsletter' => 'Subscribe an address.',
            ],
        ],

        'push' => [
            'name' => 'Push notifications',
            'description' => 'The app registers its Firebase device token, so the notifications you send from this site reach it.',
            'module' => 'firebase',
            'default' => false,
            'auth' => 'optional',
            'endpoints' => [
                'POST /devices' => 'Register or update this device\'s push token.',
                'DELETE /devices' => 'Forget this device.',
            ],
        ],
    ],
];
