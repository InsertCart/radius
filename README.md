<div align="center">

<picture>
  <source media="(prefers-color-scheme: dark)" srcset=".github/assets/radius-logo-light.png">
  <img src=".github/assets/radius-logo.png" alt="Radius" width="220">
</picture>

# Radius

**A modular, self-hosted CMS and eCommerce platform built on Laravel 12.**

[Documentation](https://radiusdoc.insertcart.com/) | [Themes](https://www.insertcart.com/radius-themes/) | [Issues](https://github.com/InsertCart/radius/issues) | [Contact](https://www.insertcart.com/contact-us/)

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP 8.2+](https://img.shields.io/badge/php-8.2%2B-777bb4.svg)](https://www.php.net/)
[![Laravel 12](https://img.shields.io/badge/laravel-12-ff2d20.svg)](https://laravel.com/)

</div>

Open source under the [MIT licence](LICENSE): use it commercially, fork it, build
closed-source themes and plugins on top of it. Security issues go through
[SECURITY.md](SECURITY.md), not the public issue tracker.

Every optional feature is a **module** you can switch off from the admin panel —
a disabled module registers no routes and runs no queries.

---

## Requirements

| Requirement | Minimum |
| --- | --- |
| PHP | 8.2 or newer |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `json`, `curl`, `fileinfo`, `zip`, `gd`, `xml`, `ctype` |
| Writable | `storage/`, `bootstrap/cache/`, `themes/`, `public/`, `.env` |

The setup wizard checks all of this for you before it will continue.

---

## Installation

1. Upload the files. Point your domain at the **`public/`** directory if your
   host allows it; otherwise point it at the project folder and the included
   `.htaccess` serves the site from there (never serve the folder without it).
2. Create an empty MySQL database.
3. Open your site in a browser — the setup wizard starts automatically.
4. Follow the four steps: server check → database → site details → admin account.

On **nginx** (including CloudPanel), `.htaccess` is not read at all. Use the
included `nginx.conf.example`, or set the document root to `public/`. Full
notes are in the [documentation](https://radiusdoc.insertcart.com/).

### From the command line

```bash
cp .env.example .env          # then fill in your DB_ credentials
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan cms:admin         # creates your admin account
php artisan storage:link
echo "{}" > storage/installed
```

---

## After installing

1. Turn on **two-factor authentication** for your account.
2. Switch off modules you don't need under **System → Modules**.
3. Set your email provider under **Settings → Email**.
4. Configure a payment gateway under **Shop → Payment gateways**, if selling.
5. Set `APP_DEBUG=false` and `APP_ENV=production` in `.env` before going live.

**System → System** shows a checklist of anything still misconfigured.

---

## Modules

| Module | What it adds | Required |
| --- | --- | --- |
| Pages | Static pages, templates, homepage selection | Yes |
| Media | Uploads, thumbnails, file manager | Yes |
| Themes | Upload and activate front-end templates | Yes |
| Users | Accounts, roles, two-factor auth | Yes |
| Blog | Posts, categories, tags, comments | No |
| eCommerce | Products, cart, checkout, orders, coupons | No |
| Payments | PayPal, Stripe, Razorpay, PayU, Cashfree, Wise, COD, bank transfer | No |
| SMS | Transactional SMS and OTP via MSG91 or Twilio | No |
| Firebase | Web push notifications | No |
| SEO | Meta tags, sitemap.xml, robots.txt, schema.org, redirects | No |
| Contact forms | Front-end form and submission inbox | No |
| Newsletter | Subscriber capture and CSV export | No |
| Mobile API | JSON API for a mobile app — **ships switched off** | No |

Dependencies are handled for you (e.g. switching off Payments also switches off
eCommerce), and nothing is ever deleted when a module is turned off.

---

## Payment gateways

| Gateway | Flow | Refunds from admin | Webhooks |
| --- | --- | --- | --- |
| Stripe | Hosted Checkout redirect | Yes | Yes |
| PayPal | Orders v2 redirect | Yes | Yes |
| Razorpay | Checkout modal | Yes | Yes |
| PayU | Signed form POST | Yes | No |
| Cashfree | Hosted checkout | Yes | Yes |
| Wise | Bank transfer, with optional transfer matching | No | No |
| Cash on delivery | Offline | No | No |
| Bank transfer | Offline | No | No |

Credentials are encrypted at rest and never sent back to the browser. Gateways
talk to their providers over REST, so no vendor SDKs are pulled into `vendor/`.

---

## Mobile API

A JSON API for a mobile app — content, shop and customer accounts. It's the
only module that **ships switched off**. Every request must carry a
registered app's key/secret; there is no public or unauthenticated mode.

```
X-Api-Key: rad_9f3c…           the app's public key
X-Api-Secret: …                its secret, generated in the admin panel
Authorization: Bearer …        the customer's token, for their own data
```

Endpoint groups (sign-in, shop, blog, reviews, search, newsletter, push, etc.)
are switched on individually under **Mobile API → Endpoints** — anything not
enabled answers 404. Optional HMAC request signing, rotating refresh tokens,
device management and 2FA are all supported. Paying from an app hands off to a
signed link that uses the same web checkout flow as the browser.

See the [documentation](https://radiusdoc.insertcart.com/) for the full API
reference.

---

## Email

SMTP settings live in the admin panel. API-key providers read their keys from
`.env` instead:

| Provider | `.env` keys | Package to install |
| --- | --- | --- |
| SMTP | — | built in |
| Resend | `RESEND_KEY` | `composer require resend/resend-laravel` |
| Amazon SES | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` | `composer require aws/aws-sdk-php` |
| Postmark | `POSTMARK_TOKEN` | `composer require symfony/postmark-mailer` |

---

## Visual builder

A drag-and-drop editor for designing pages without writing HTML — built into
the CMS, not bolted on. Pages are a tree of **section → column → widget**,
rendered server-side as plain Blade/HTML.

- Themes opt in per region with `@region('header') ... @endregion`; a theme
  with no regions is unaffected and the builder still works on page content.
- 27 built-in widgets across Basic, Media, Layout, Content, Shop and Site parts.
- Style edits patch the live stylesheet instantly; content edits re-render just
  that element. The server recompiles the real stylesheet on publish.
- Drafts autosave, publishing keeps the last 25 revisions, and editing is
  responsive per breakpoint with design tokens for colours.
- Builder-authored CSS and markup are sanitised (no `javascript:`/`data:`
  URLs, no rule-breaking characters, capped tree size).

Adding a widget is one PHP class plus one Blade view — see the
[documentation](https://radiusdoc.insertcart.com/) for a walkthrough.

---

## Themes

Themes live in `themes/<slug>/` and install as a `.zip` from
**Appearance → Themes**, or straight from **Appearance → Browse themes**
(a hosted, free directory with search and one-click install/update).

```
my-theme/
├── theme.json          required: name, version
├── screenshot.png
├── assets/             copied to public/themes/<slug>/
└── views/              required — anything omitted falls back to the default theme
```

Every upload is scanned before anything is written to disk: zip-slip and
zip-bomb protection, an extension allowlist (no `.php`, `.htaccess`, `.sh`),
and a Blade scan that rejects raw PHP, `eval`, shell exec or filesystem writes.
Directory installs go through the same scan, over HTTPS, with a checksum and
slug match required.

---

## Uploads

Every upload (media library, editor, builder) is checked before it's saved:
extension and file contents must agree, double extensions and embedded PHP are
rejected, SVGs are sanitised, and photos are re-encoded to strip EXIF/GPS data.
The uploads and theme-assets folders never execute PHP, on Apache or nginx.

**Only `public/` should be on the web.** The included `.htaccess` keeps the
project root private even if the whole project sits inside the web root; on
nginx, set the document root to `public/` or deny the app folders explicitly.
**System → Security** can probe your live site for `.env` and other files that
should not be reachable.

---

## Paid downloads

Digital products are stored outside the media library, in
`storage/app/private/downloads/` with no public URL. Downloads are only served
through `/account/orders/{order}/download/{item}`, which verifies the order,
payment status and download limit before streaming the file as an attachment.
`CMS_DOWNLOADS_DISK` can move storage to S3 or elsewhere (keep it private).

---

## Updates

The CMS can update itself. Point `CMS_UPDATE_URL` at a JSON manifest (it
defaults to this repo's GitHub releases), and updates are offered under
**System → Updates** — installing is always a manual click.

```bash
# 1. Bump the version in config/cms.php
git commit -am "Release 1.1.3"

# 2. Tag it — the message becomes the release description
git tag -a v1.1.3 -m "Fixes a stored XSS in the media library.

tags: security"

# 3. Push both
git push && git push --tags
```

GitHub Actions builds, packages and checksums the release automatically. Every
release is verified before install (HTTPS, SHA-256, version match) and only
files on an allowlist are ever written — `.env`, uploads and other themes are
never touched. A backup is taken before every update, with one-click rollback.

See the [documentation](https://radiusdoc.insertcart.com/) for manifest format
and self-hosting a release feed.

---

## Security notes

- Two-factor authentication (TOTP) with encrypted secrets and one-time recovery codes.
- Money stored as integers (minor units); order totals always recalculated server-side.
- Stock decremented inside the order transaction to prevent overselling.
- Order numbers are unguessable; paid downloads are never served by URL.
- Release archives and theme installs are checksum- and HTTPS-verified.
- Payment returns are always re-verified against the provider's API.
- Login is rate-limited per email and IP.

Full details are in [SECURITY.md](SECURITY.md) and the
[documentation](https://radiusdoc.insertcart.com/).

---

## Command line

```bash
php artisan cms:admin              # create or promote an admin account
php artisan cms:sync               # register new modules/gateways/settings after an upgrade
php artisan cms:sync --themes      # re-scan the themes folder
php artisan cms:demo               # install sample posts, pages and products
php artisan cms:demo --remove      # delete that sample content again
php artisan cms:marketplace-entry <zip> --url=<folder>  # print a theme directory listing for a ZIP
php artisan cms:release            # build a distributable release ZIP
php artisan optimize:clear         # clear all caches
```

---

## Development

```bash
composer install
npm install
npm run dev      # Vite dev server with hot reload
npm run build    # compile assets for production
```

Compiled assets are committed under `public/build/`, so a buyer never needs
Node installed to run the site.

---

## Branding

Radius ships with its own logo and favicon set; uploading your own under
**Settings → General** overrides it everywhere. For a white-label fork,
repoint the `brand` block in `config/cms.php` instead of editing views:

```php
'brand' => [
    'logo' => 'images/radius-logo.png',
    'logo_light' => 'images/radius-logo-light.png',
    'favicon' => 'favicon/favicon.ico',
    'favicon_light' => 'favicon-light/favicon.ico',
],
```

Use the `<x-site-logo>` component in views — it resolves light/dark ink from
the site's colour scheme automatically.

---

## Project layout

```
app/
├── Cms/                    the CMS itself, kept apart from Laravel's skeleton
│   ├── Settings/           database-backed settings with caching
│   ├── Modules/            the module on/off switchboard
│   ├── Themes/             theme discovery, activation and the ZIP installer
│   ├── Marketplace/        the theme directory: catalogue, install, updates
│   ├── Payments/           gateway contract, result object and drivers
│   ├── Sms/                SMS drivers and the one-time-code flow
│   ├── Firebase/           FCM push and client config
│   ├── Seo/                meta, schema.org and sitemap generation
│   ├── Shop/               cart and order services
│   ├── Builder/            the visual editor's engine
│   │   └── Blocks/         one class per widget
│   ├── Media/              uploads and thumbnails
│   └── Support/            helpers, 2FA, admin navigation
├── Http/Controllers/
│   ├── Admin/              the admin panel
│   ├── Front/              the storefront
│   ├── Auth/               sign-in, registration, two-factor
│   └── Installer/          the setup wizard
└── Models/
config/
├── cms.php                 modules, themes, media, security, brand assets
├── settings.php            every admin setting, declared once
├── payments.php            gateway definitions and endpoints
└── builder.php             registered widgets, breakpoints, design tokens
themes/default/             the bundled starter theme
```

Adding a setting is one array entry in `config/settings.php`. Adding a payment
gateway is one driver class plus one entry in `config/payments.php`. Adding a
builder widget is one block class, one Blade view, and one entry in
`config/builder.php`.
