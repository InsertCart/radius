<?php

/*
|--------------------------------------------------------------------------
| Media storage and CDN providers
|--------------------------------------------------------------------------
| Where the media library keeps its files, and which address visitors fetch
| them from. The admin screen under System -> Media storage is generated from
| this list, so adding a provider means one array entry - no controller, route
| or view changes.
|
| Every provider declares a 'kind', which decides what actually happens:
|
|   proxy - the files stay on this server. Only the address changes: a pull
|           CDN (Cloudflare, CloudFront, BunnyCDN, KeyCDN, Fastly) fetches
|           from this site the first time and serves its own copy afterwards.
|           Nothing is uploaded, nothing can be lost, and turning it off is
|           instant. This is the right choice for most sites.
|
|   s3    - files are uploaded to an S3-compatible bucket and served from it.
|           Requests are signed with AWS Signature V4 over plain HTTPS, so no
|           SDK is needed: the same signer talks to AWS, DigitalOcean Spaces,
|           Cloudflare R2, Google Cloud Storage, Wasabi, Backblaze and MinIO.
|
|   ftp   - files are uploaded to a directory on another server over FTP or
|           FTPS, which that server publishes under a domain you supply. The
|           fallback for hosts that offer nothing but disk space.
|
| Credentials are stored encrypted, exactly as payment gateway keys are.
| Field types: text, secret, url, select.
|
| Deliberately no vendor SDKs. Radius ships as a ZIP to buyers who often have
| no shell and no Composer, and aws/aws-sdk-php alone is larger than this
| entire application. Signing an S3 request is sixty lines of hash_hmac.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Where uploads are written before anything is offloaded
    |--------------------------------------------------------------------------
    | Files are always written here first, thumbnailed here, and only then
    | pushed to the provider. Image processing needs a real local path, and an
    | upload that fails halfway to a bucket should not lose the original.
    */

    'origin_disk' => env('CMS_MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Batch size for the sync screen
    |--------------------------------------------------------------------------
    | How many files one click of "Upload existing files" moves. Kept modest so
    | the request finishes inside a shared host's time limit; the button simply
    | reports what is left and is pressed again.
    */

    'batch_size' => 25,

    /*
    |--------------------------------------------------------------------------
    | Network timeout, in seconds, for a single remote operation
    |--------------------------------------------------------------------------
    */

    'timeout' => 30,

    'providers' => [

        'proxy' => [
            'name' => 'CDN in front of this server',
            'kind' => 'proxy',
            'tagline' => 'Cloudflare, CloudFront, BunnyCDN, KeyCDN, Fastly',
            'description' => 'Files stay where they are. You point a CDN at this site and give it a hostname; the CDN fetches each file once and serves every later request from its own edge. Nothing is uploaded and nothing can go missing.',
            'setup' => 'Create a pull zone (or an orange-clouded DNS record on Cloudflare) whose origin is this site, then paste the hostname it gives you below.',
            'fields' => [
                'delivery_url' => [
                    'label' => 'CDN address',
                    'type' => 'url',
                    'help' => 'For example https://cdn.example.com. Media addresses are rewritten to this host and the path is left alone, which is what a pull CDN expects.',
                ],
            ],
        ],

        's3' => [
            'name' => 'Amazon S3',
            'kind' => 's3',
            'tagline' => 'AWS, any region',
            'description' => 'Uploads go to an S3 bucket and are served from it, or from a CloudFront distribution in front of it.',
            'setup' => 'Create a bucket and allow public reads on it, then create an IAM user with PutObject, GetObject and DeleteObject on that bucket and use its access key here.',
            'endpoint' => 'https://s3.{region}.amazonaws.com',
            'path_style' => false,
            'public_url' => 'https://{bucket}.s3.{region}.amazonaws.com',
            'fields' => [
                'key' => ['label' => 'Access key ID', 'type' => 'text'],
                'secret' => ['label' => 'Secret access key', 'type' => 'secret'],
                'region' => ['label' => 'Region', 'type' => 'text', 'help' => 'For example us-east-1 or ap-south-1.'],
                'bucket' => ['label' => 'Bucket name', 'type' => 'text'],
                'delivery_url' => [
                    'label' => 'CloudFront or custom domain',
                    'type' => 'url',
                    'optional' => true,
                    'help' => 'Optional. Leave empty to serve straight from the bucket address.',
                ],
            ],
        ],

        'spaces' => [
            'name' => 'DigitalOcean Spaces',
            'kind' => 's3',
            'tagline' => 'Spaces, with the built-in CDN',
            'description' => 'DigitalOcean object storage. Its own CDN sits in front of every Space, so enabling that and pasting the edge address below is usually all you want.',
            'setup' => 'Create a Space, set File Listing to Public, enable its CDN, then generate a key under API -> Spaces Keys.',
            'endpoint' => 'https://{region}.digitaloceanspaces.com',
            'path_style' => false,
            'public_url' => 'https://{bucket}.{region}.digitaloceanspaces.com',
            'fields' => [
                'key' => ['label' => 'Spaces access key', 'type' => 'text'],
                'secret' => ['label' => 'Spaces secret key', 'type' => 'secret'],
                'region' => ['label' => 'Region', 'type' => 'text', 'help' => 'The datacentre slug, for example nyc3, fra1 or blr1.'],
                'bucket' => ['label' => 'Space name', 'type' => 'text'],
                'delivery_url' => [
                    'label' => 'CDN endpoint',
                    'type' => 'url',
                    'optional' => true,
                    'help' => 'Optional but recommended: the .cdn.digitaloceanspaces.com address, or your own domain.',
                ],
            ],
        ],

        'r2' => [
            'name' => 'Cloudflare R2',
            'kind' => 's3',
            'tagline' => 'Cloudflare object storage, no egress fees',
            'description' => 'Cloudflare storage with an S3 API. R2 buckets have no public address of their own, so a custom domain or the r2.dev address is required.',
            'setup' => 'Create an R2 bucket, connect a custom domain to it under Settings -> Public access, then create an API token with Object Read and Write and use its access key pair.',
            'endpoint' => 'https://{account_id}.r2.cloudflarestorage.com',
            'path_style' => true,
            'region' => 'auto',
            'fields' => [
                'account_id' => ['label' => 'Account ID', 'type' => 'text', 'help' => 'The long hex string in your R2 endpoint address.'],
                'key' => ['label' => 'Access key ID', 'type' => 'text'],
                'secret' => ['label' => 'Secret access key', 'type' => 'secret'],
                'bucket' => ['label' => 'Bucket name', 'type' => 'text'],
                'delivery_url' => [
                    'label' => 'Public address',
                    'type' => 'url',
                    'help' => 'Required. Your connected custom domain, or the pub-....r2.dev address.',
                ],
            ],
        ],

        'gcs' => [
            'name' => 'Google Cloud Storage',
            'kind' => 's3',
            'tagline' => 'Google Cloud, via interoperability keys',
            'description' => 'Google Cloud Storage over its S3-compatible XML API, which uses an HMAC key rather than a service-account JSON file.',
            'setup' => 'Create a bucket and grant allUsers the Storage Object Viewer role, then under Cloud Storage -> Settings -> Interoperability create an access key for a service account.',
            'endpoint' => 'https://storage.googleapis.com',
            'path_style' => true,
            'region' => 'auto',
            'public_url' => 'https://storage.googleapis.com/{bucket}',
            'fields' => [
                'key' => ['label' => 'HMAC access key', 'type' => 'text', 'help' => 'Starts with GOOG.'],
                'secret' => ['label' => 'HMAC secret', 'type' => 'secret'],
                'bucket' => ['label' => 'Bucket name', 'type' => 'text'],
                'delivery_url' => [
                    'label' => 'Cloud CDN or custom domain',
                    'type' => 'url',
                    'optional' => true,
                    'help' => 'Optional. Leave empty to serve from storage.googleapis.com.',
                ],
            ],
        ],

        'wasabi' => [
            'name' => 'Wasabi',
            'kind' => 's3',
            'tagline' => 'Flat-rate S3-compatible storage',
            'endpoint' => 'https://s3.{region}.wasabisys.com',
            'path_style' => false,
            'public_url' => 'https://{bucket}.s3.{region}.wasabisys.com',
            'fields' => [
                'key' => ['label' => 'Access key', 'type' => 'text'],
                'secret' => ['label' => 'Secret key', 'type' => 'secret'],
                'region' => ['label' => 'Region', 'type' => 'text', 'help' => 'For example us-east-1 or eu-central-1.'],
                'bucket' => ['label' => 'Bucket name', 'type' => 'text'],
                'delivery_url' => ['label' => 'Custom domain', 'type' => 'url', 'optional' => true],
            ],
        ],

        'b2' => [
            'name' => 'Backblaze B2',
            'kind' => 's3',
            'tagline' => 'B2, through its S3-compatible endpoint',
            'setup' => 'Make the bucket public, then create an application key scoped to it. The key ID goes in the access key field.',
            'endpoint' => 'https://s3.{region}.backblazeb2.com',
            'path_style' => false,
            'public_url' => 'https://{bucket}.s3.{region}.backblazeb2.com',
            'fields' => [
                'key' => ['label' => 'Key ID', 'type' => 'text'],
                'secret' => ['label' => 'Application key', 'type' => 'secret'],
                'region' => ['label' => 'Region', 'type' => 'text', 'help' => 'The part of your endpoint between s3. and .backblazeb2.com, for example us-west-004.'],
                'bucket' => ['label' => 'Bucket name', 'type' => 'text'],
                'delivery_url' => ['label' => 'Custom domain', 'type' => 'url', 'optional' => true],
            ],
        ],

        's3_compatible' => [
            'name' => 'Other S3-compatible storage',
            'kind' => 's3',
            'tagline' => 'MinIO, Linode, Vultr, Scaleway, Storj, Hetzner',
            'description' => 'Anything that speaks the S3 API. You supply the endpoint yourself.',
            'fields' => [
                'endpoint' => ['label' => 'Endpoint', 'type' => 'url', 'help' => 'The full base address, for example https://minio.example.com or https://us-east-1.linodeobjects.com.'],
                'key' => ['label' => 'Access key', 'type' => 'text'],
                'secret' => ['label' => 'Secret key', 'type' => 'secret'],
                'region' => ['label' => 'Region', 'type' => 'text', 'optional' => true, 'help' => 'Leave empty if your provider does not use one; us-east-1 is assumed.'],
                'bucket' => ['label' => 'Bucket name', 'type' => 'text'],
                'path_style' => [
                    'label' => 'Address style',
                    'type' => 'select',
                    'options' => [
                        'yes' => 'Path style - endpoint/bucket/file (MinIO and most self-hosted)',
                        'no' => 'Virtual host - bucket.endpoint/file (most hosted providers)',
                    ],
                    'help' => 'If uploads fail with a 404 or a NoSuchBucket error, try the other one.',
                ],
                'delivery_url' => ['label' => 'Public address', 'type' => 'url', 'optional' => true, 'help' => 'Leave empty to serve from the endpoint above.'],
            ],
        ],

        'ftp' => [
            'name' => 'Custom FTP / FTPS',
            'kind' => 'ftp',
            'tagline' => 'Any server you can upload to',
            'description' => 'Uploads files to a directory on another server. That server has to publish the directory on the web itself - FTP moves the bytes, it does not serve them.',
            'setup' => 'Point the root at the directory your other host publishes, and the address below at the domain that serves it. Use FTPS wherever your host supports it: plain FTP sends the password in the clear.',
            'fields' => [
                'host' => ['label' => 'Host', 'type' => 'text', 'help' => 'For example ftp.example.com, with no ftp:// prefix.'],
                'port' => ['label' => 'Port', 'type' => 'text', 'optional' => true, 'help' => 'Defaults to 21.'],
                'username' => ['label' => 'Username', 'type' => 'text'],
                'password' => ['label' => 'Password', 'type' => 'secret'],
                'root' => ['label' => 'Remote directory', 'type' => 'text', 'optional' => true, 'help' => 'For example /public_html/media. Leave empty for the login directory.'],
                'ssl' => [
                    'label' => 'Encryption',
                    'type' => 'select',
                    'options' => ['yes' => 'FTPS - explicit TLS (recommended)', 'no' => 'Plain FTP - not encrypted'],
                ],
                'passive' => [
                    'label' => 'Transfer mode',
                    'type' => 'select',
                    'options' => ['yes' => 'Passive (recommended)', 'no' => 'Active'],
                ],
                'delivery_url' => [
                    'label' => 'Public address',
                    'type' => 'url',
                    'help' => 'Required. The address that serves the remote directory, for example https://media.example.com.',
                ],
            ],
        ],

    ],
];
