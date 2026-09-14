<?php

/*
|--------------------------------------------------------------------------
| Payment gateways
|--------------------------------------------------------------------------
| Each gateway declares the credentials it needs. The admin screen for
| payments is generated from this list, and credentials marked 'secret' are
| encrypted before they touch the database.
|
| Gateways talk to their providers over REST using Laravel's HTTP client
| rather than vendor SDKs. That keeps vendor/ small, avoids six sets of
| transitive dependency conflicts, and means a buyer on shared hosting only
| needs curl and openssl.
|
| 'flow' describes how the customer reaches the provider:
|   redirect - we create a remote session and send the browser to it
|   form     - we POST a signed form to the provider
|   checkout - the provider renders a modal over our page via its JS widget
|   manual   - no live redirect; the order is marked awaiting payment
*/

return [

    'gateways' => [

        'stripe' => [
            'name' => 'Stripe',
            'flow' => 'redirect',
            'supports_refund' => true,
            'supports_webhook' => true,
            'driver' => \App\Cms\Payments\Drivers\StripeGateway::class,
            'currencies' => ['USD', 'EUR', 'GBP', 'INR', 'AUD', 'CAD', 'SGD', 'AED'],
            'fields' => [
                'publishable_key' => ['label' => 'Publishable key', 'type' => 'text'],
                'secret_key' => ['label' => 'Secret key', 'type' => 'secret'],
                'webhook_secret' => ['label' => 'Webhook signing secret', 'type' => 'secret', 'help' => 'From the Stripe dashboard webhook endpoint.'],
            ],
        ],

        'paypal' => [
            'name' => 'PayPal',
            'flow' => 'redirect',
            'supports_refund' => true,
            'supports_webhook' => true,
            'driver' => \App\Cms\Payments\Drivers\PayPalGateway::class,
            'currencies' => ['USD', 'EUR', 'GBP', 'AUD', 'CAD', 'SGD'],
            'fields' => [
                'client_id' => ['label' => 'Client ID', 'type' => 'text'],
                'client_secret' => ['label' => 'Client secret', 'type' => 'secret'],
                'webhook_id' => ['label' => 'Webhook ID', 'type' => 'text'],
            ],
        ],

        'razorpay' => [
            'name' => 'Razorpay',
            'flow' => 'checkout',
            'supports_refund' => true,
            'supports_webhook' => true,
            'driver' => \App\Cms\Payments\Drivers\RazorpayGateway::class,
            'currencies' => ['INR', 'USD'],
            'fields' => [
                'key_id' => ['label' => 'Key ID', 'type' => 'text'],
                'key_secret' => ['label' => 'Key secret', 'type' => 'secret'],
                'webhook_secret' => ['label' => 'Webhook secret', 'type' => 'secret'],
            ],
        ],

        'payu' => [
            'name' => 'PayU',
            'flow' => 'form',
            'supports_refund' => true,
            'supports_webhook' => false,
            'driver' => \App\Cms\Payments\Drivers\PayUGateway::class,
            'currencies' => ['INR'],
            'fields' => [
                'merchant_key' => ['label' => 'Merchant key', 'type' => 'text'],
                'merchant_salt' => ['label' => 'Merchant salt', 'type' => 'secret'],
            ],
        ],

        'cashfree' => [
            'name' => 'Cashfree',
            'flow' => 'redirect',
            'supports_refund' => true,
            'supports_webhook' => true,
            'driver' => \App\Cms\Payments\Drivers\CashfreeGateway::class,
            'currencies' => ['INR'],
            'fields' => [
                'app_id' => ['label' => 'App ID', 'type' => 'text'],
                'secret_key' => ['label' => 'Secret key', 'type' => 'secret'],
                'webhook_secret' => ['label' => 'Webhook secret', 'type' => 'secret'],
            ],
        ],

        'wise' => [
            'name' => 'Wise (bank transfer)',
            'flow' => 'manual',
            'supports_refund' => false,
            'supports_webhook' => false,
            'driver' => \App\Cms\Payments\Drivers\WiseGateway::class,
            'currencies' => ['USD', 'EUR', 'GBP', 'INR', 'AUD', 'CAD', 'SGD', 'AED'],
            'fields' => [
                'api_token' => ['label' => 'API token', 'type' => 'secret', 'help' => 'Optional. Used to verify incoming transfers against your Wise account.'],
                'profile_id' => ['label' => 'Profile ID', 'type' => 'text'],
                'account_holder' => ['label' => 'Account holder name', 'type' => 'text'],
                'account_details' => ['label' => 'Bank details shown to the customer', 'type' => 'textarea',
                    'help' => 'IBAN / account number / sort code. Displayed on the order confirmation so the customer can pay you.'],
            ],
        ],

        'cod' => [
            'name' => 'Cash on delivery',
            'flow' => 'manual',
            'supports_refund' => false,
            'supports_webhook' => false,
            'driver' => \App\Cms\Payments\Drivers\CashOnDeliveryGateway::class,
            'currencies' => ['*'],
            'fields' => [
                'instructions' => ['label' => 'Instructions', 'type' => 'textarea', 'default' => 'Pay in cash when your order is delivered.'],
                'extra_fee' => ['label' => 'Extra fee', 'type' => 'text', 'default' => '0'],
            ],
        ],

        'bank' => [
            'name' => 'Direct bank transfer',
            'flow' => 'manual',
            'supports_refund' => false,
            'supports_webhook' => false,
            'driver' => \App\Cms\Payments\Drivers\BankTransferGateway::class,
            'currencies' => ['*'],
            'fields' => [
                'instructions' => ['label' => 'Bank details', 'type' => 'textarea',
                    'default' => "Bank name:\nAccount name:\nAccount number:\nIFSC / SWIFT:"],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API endpoints
    |--------------------------------------------------------------------------
    | Kept here so a buyer can point a gateway at a sandbox without editing
    | driver code.
    */

    'endpoints' => [
        'stripe' => 'https://api.stripe.com/v1',
        'paypal' => ['live' => 'https://api-m.paypal.com', 'test' => 'https://api-m.sandbox.paypal.com'],
        'razorpay' => 'https://api.razorpay.com/v1',
        'payu' => ['live' => 'https://secure.payu.in/_payment', 'test' => 'https://test.payu.in/_payment'],
        'payu_verify' => ['live' => 'https://info.payu.in/merchant/postservice.php?form=2', 'test' => 'https://test.payu.in/merchant/postservice.php?form=2'],
        'cashfree' => ['live' => 'https://api.cashfree.com/pg', 'test' => 'https://sandbox.cashfree.com/pg'],
        'wise' => ['live' => 'https://api.transferwise.com', 'test' => 'https://api.sandbox.transferwise.tech'],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP behaviour
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => 30,
        'retries' => 2,
        'retry_delay_ms' => 400,
    ],
];
