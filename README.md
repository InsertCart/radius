<div align="center">

<picture>
  <source media="(prefers-color-scheme: dark)" srcset=".github/assets/radius-logo-light.png">
  <img src=".github/assets/radius-logo.png" alt="Radius" width="220">
</picture>

# Radius

**A modular, self-hosted CMS and eCommerce platform built on Laravel 12.**

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP 8.2+](https://img.shields.io/badge/php-8.2%2B-777bb4.svg)](https://www.php.net/)
[![Laravel 12](https://img.shields.io/badge/laravel-12-ff2d20.svg)](https://laravel.com/)

</div>

Open source under the [MIT licence](LICENSE): use it commercially, fork it,
build closed-source themes and plugins on top of it. Security issues go through
[SECURITY.md](SECURITY.md), not the public issue tracker.

Every optional feature is a **module** that can be switched off from the admin
panel. A disabled module registers no routes and runs no queries, so a
blog-only site carries none of the shop's weight.

---

## Screenshots

> Drop image files into [`.github/assets/`](.github/assets/) and reference them
> here. That folder is excluded from the release ZIP, so marketing images never
> ship to buyers. See [`.github/assets/README.md`](.github/assets/README.md).

<!-- Uncomment each row once the image exists.

| Admin dashboard | Visual builder |
| --- | --- |
| <img src=".github/assets/screenshot-dashboard.png" alt="Admin dashboard"> | <img src=".github/assets/screenshot-builder.png" alt="Visual builder"> |

| Storefront | Checkout |
| --- | --- |
| <img src=".github/assets/screenshot-storefront.png" alt="Storefront"> | <img src=".github/assets/screenshot-checkout.png" alt="Checkout"> |

-->

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
   host lets you; if it does not, point it at the project folder and the
   included `.htaccess` serves the site from there.
2. Create an empty MySQL database.
3. Open your site in a browser. The setup wizard starts automatically.
4. Follow the four steps: server check → database → site details → admin account.

Your site then answers on `https://example.com/`, and
`https://example.com/public/...` redirects to it permanently — one address per
page, so search engines never see two copies. Pointing the document root at
`public/` is still better, because nothing outside it is served at all, but it
is not a requirement and on shared hosting it is often not an option.

**Never serve the project folder without its `.htaccess`.** It is what keeps
`.env`, the source code and the uploads folder unreachable when the whole
project sits inside the web root. If your host ignores `.htaccess` files
(`AllowOverride None`), you must point the document root at `public/`.

### On nginx (including CloudPanel)

**nginx does not read `.htaccess` at all**, so every one of those files is
inert. Two things then need stating in your server block: Laravel's routing,
without which every page but the home page returns 404; and the rules that keep
`.env` private and stop an uploaded file being executed.

A complete, commented server block ships with the CMS as
**`nginx.conf.example`**, with notes for CloudPanel at the bottom.

On CloudPanel the short version is:

1. Create the site as a **PHP** site, PHP 8.2 or newer.
2. Set the **Site Root** so it ends in `/public`. This is the step that
   matters - leaving it at the project folder puts `.env` one URL away.
3. Under the site's **Vhost** tab, add the `location ^~ /storage/`,
   `location ^~ /themes/` and dotfile blocks from `nginx.conf.example`.
   CloudPanel reloads nginx for you.
4. Open **System → Security** in the admin and run the check. It asks your own
   server for `.env` and reports what it actually got back.

What does *not* change on nginx: uploads are still checked before they are
stored, SVGs are still sanitised, and paid downloads still live on a disk with
no URL and leave only through a controller that has verified the order. Those
are application-level and do not depend on the web server. The admin panel
says so plainly rather than implying the site is unprotected.

The wizard writes `.env`, runs the migrations, seeds the defaults and creates
your admin account. When it finishes it drops a `storage/installed` lock file,
which permanently closes the wizard so nobody can re-point your site at another
database.

### Installing from the command line instead

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

Work through these in order:

1. **Turn on two-factor authentication** for your account (profile menu → Two-factor auth).
2. **Switch off modules you do not need** under System → Modules.
3. **Set your email provider** under Settings → Email, then send a test message.
4. **Configure a payment gateway** under Shop → Payment gateways, if you are selling.
5. **Move the admin panel** off the default path by setting `CMS_ADMIN_PREFIX` in `.env`.
6. Set `APP_DEBUG=false` and `APP_ENV=production` in `.env` before going live.

System → System shows a checklist of anything still misconfigured.

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

Modules with dependencies are handled for you: switching off **Payments** also
switches off **eCommerce**, because a shop with no way to take money is not a
working shop. Nothing is ever deleted — switching a module back on restores it
exactly as it was.

Every module except the Mobile API arrives switched on, because each is part of
running a website. The API opens the site to programs, which is nobody's
default — see [Mobile API](#mobile-api).

> After toggling modules, clear the caches (System → Maintenance) if you have
> previously run "Optimise for production". Caching routes freezes which ones exist.

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

Credentials are **encrypted at rest** with your `APP_KEY` and are never sent
back to the browser once saved. A gateway refuses to go live until every
credential it needs is filled in.

Gateways talk to their providers over REST rather than through vendor SDKs.
That keeps `vendor/` small, avoids six sets of transitive dependencies fighting
each other, and means a host only needs `curl` and `openssl`.

**Webhooks.** Each gateway's settings screen shows the URL to paste into the
provider's dashboard. Signatures are verified on every call, so an unsigned or
replayed request is rejected. Webhooks are what confirm a payment when the
customer closes the tab before returning to your site.

---

## Mobile API

A JSON API for a mobile app: content, the shop, and customer accounts. It is a
module, and the only one that **ships switched off**. A site that never builds
an app never answers an API request — the routes are not even registered.

Switch it on under **Modules**, then open **Mobile API** in the sidebar.

### Nobody calls it just by knowing the address

Every request must name a registered app and prove it holds that app's secret.
There is no public mode and no endpoint that skips the check — not even the
product list. An unauthenticated request is refused before any route,
controller or model is reached.

```
X-Api-Key: rad_9f3c…           the app's public key
X-Api-Secret: …                its secret, generated in the admin panel
Authorization: Bearer …        the customer's token, for their own data
```

Register an app under **Mobile API → Apps**. The secret is shown **once**: it
is stored encrypted for the server's own use and is never rendered again. If
it leaks, generate a new one — every build carrying the old one stops working
immediately.

**Signed requests.** Turn on *Require signed requests* and the secret stops
travelling at all. Each call carries an HMAC-SHA256 signature over the method,
path, query, body, a timestamp and a nonce, so a captured request is useless
once its window closes and nothing in it can be altered on the way. The exact
string to sign is documented in `app/Cms/Api/RequestSigner.php`.

**No cookies, no CSRF, no session.** API routes run on their own middleware
stack with no session at all. A browser that happens to be signed in to the
website cannot reach the API by accident, because the only thing that
authenticates a caller is a header an app sets deliberately.

### Only the parts you switch on

Under **Mobile API → Endpoints**, each group is a checkbox. Anything not
switched on answers 404 — to everyone, credentials or not.

| Group | Covers | On by default |
| --- | --- | --- |
| Sign in & customer account | Tokens, profile, password, signed-in devices | Yes |
| Customer registration | Sign-up, password reset, verification email | Yes |
| Blog posts | Posts, categories, tags | Yes |
| Post comments | Reading and leaving comments | No |
| Pages | Published pages | Yes |
| Shop | Products, cart, coupons, checkout, orders, addresses | No |
| Product reviews | Reading and leaving reviews | No |
| Search | One search across everything searchable | Yes |
| Contact form | Sending a message to your inbox | No |
| Newsletter | Subscribing an address | No |
| Push notifications | Registering a device token | No |

Groups know what they need. Switching the **Shop** on switches customer
accounts and registration on with it — a shop nobody can sign up to is a shop
nobody buys from twice. A group whose CMS module is off cannot be switched on
at all: there is no shop API on a site with no shop.

### It is the storefront, not the admin panel

There is no admin endpoint. Nothing in this API publishes content, changes a
setting, reads another customer's data or touches an order that is not the
caller's own. Staff accounts are refused at sign-in by default, so a stolen
app credential cannot be pointed at an admin password.

Moderation is unchanged: a comment or review left through the API waits for
approval exactly as it does on the website, and prices are always read from the
database, never from the request.

### Tokens

A sign-in returns a short-lived access token and a long-lived refresh token.
The refresh token rotates every time it is used, so a copy taken off a device
stops working the moment the real device refreshes. Only digests are stored —
a database dump does not let anybody sign in as a customer.

Customers can see their signed-in devices and cut one off; changing a password
signs every other device out. An admin can revoke one session, or all of them,
from **Mobile API → Signed-in devices**.

Two-factor authentication is not skipped for apps. An account with 2FA gets a
challenge back from `/auth/login` and finishes at `/auth/two-factor`.

### Paying from an app

Placing an order returns either instructions (cash on delivery, bank transfer)
or a **signed, expiring link** to open in a browser or web view. That link
drops the customer into the same payment flow the website uses, so the
provider's redirect, signature check and webhook have exactly one
implementation. The app watches the order's status endpoint and closes the web
view once it is paid.

Guests get a handle on the order they just placed, derived from the order
number with your `APP_KEY`. Without it, a guest cannot read an order at all.

### A first call

```bash
curl https://example.com/api/v1/site \
  -H "X-Api-Key: rad_9f3c…" \
  -H "X-Api-Secret: …"
```

`/api/v1/site` is always available while the module is on, and tells the app
which groups it may call — so switching the shop off in the admin panel makes
the shop tab disappear from the app rather than fail in it.

Rate limits, token lifetimes, guest carts and staff sign-in all live on the
same screen. The address can be moved off `/api` with `CMS_API_PREFIX` in
`.env` if the site already serves something there.

---

## Email

SMTP settings live in the admin panel. API-key providers read their keys from
`.env` instead, because those are high-value credentials that should not sit in
a database backup:

| Provider | `.env` keys | Package to install |
| --- | --- | --- |
| SMTP | — | built in |
| Resend | `RESEND_KEY` | `composer require resend/resend-laravel` |
| Amazon SES | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` | `composer require aws/aws-sdk-php` |
| Postmark | `POSTMARK_TOKEN` | `composer require symfony/postmark-mailer` |

The Email settings screen shows which keys are present and which package is
missing. If a provider is not installed, mail falls back to the log rather than
failing silently.

---

## Visual builder

A drag-and-drop editor for designing pages without writing HTML. It is built
into the CMS rather than bolted on, and **it does not replace your theme**.

### How it coexists with a theme

Themes and the builder own different things. A theme keeps owning its chrome
and marks the parts it is willing to hand over:

```blade
@region('header')
    ... the theme's own header markup ...
@endregion
```

With no layout built for that region, the markup inside simply renders exactly
as it always has. Once an admin designs a header in the editor, that layout is
rendered instead. **A theme with no `@region` markers is completely
unaffected** — the builder is then limited to page content, which still works.

Page bodies use the same idea from the other direction. A theme that already
does `{!! $page->content !!}` transparently gets the built layout once one
exists, and the stored HTML when it does not — so no theme changes are needed
at all. Turning the builder off for a page brings its original content straight
back; nothing is lost either way.

Declare regions in `theme.json`:

```json
"regions": {
    "header": "Site header",
    "footer": "Site footer",
    "before_content": "Before page content",
    "after_content": "After page content"
}
```

### The layout model

A layout is a tree of **section → column → widget**. Sections hold columns,
columns hold widgets, and each level has its own settings panel with Content,
Style and Advanced tabs.

Rendering is **server-side Blade**, so pages ship plain HTML with one
stylesheet — fast, indexable, and no JavaScript required to read them.

### Why the editor feels immediate

Controls are split by what they affect:

- **Style controls** (colour, spacing, typography, borders) declare a CSS
  mapping, so the editor rewrites the preview's stylesheet in place. Dragging a
  slider is instant, with no server round trip.
- **Content controls** re-render just the element that changed.
- Structural edits re-render the canvas, which is rare enough not to be felt.

The server recompiles the authoritative stylesheet on publish.

### Widgets

27 built in, grouped by category:

| Category | Widgets |
| --- | --- |
| Basic | Heading, Text, Button, Icon |
| Media | Image, Video, Gallery, Map |
| Layout | Spacer, Divider |
| Content | Icon box, Post grid, Accordion, Tabs, Testimonial, Counter, Pricing table, Contact form, Newsletter, HTML |
| Shop | Product grid |
| Site parts | Site logo, Navigation menu, Cart icon, Search, Social icons, Page title |

Widgets belonging to a disabled module never appear in the palette and render
as nothing on the public site, so switching the shop off does not leave broken
product grids behind.

### Adding a widget

One class and one Blade view. The settings panel is generated from the controls
you declare, so the editor itself never needs touching:

```php
class QuoteBlock extends Block
{
    public static function type(): string { return 'quote'; }
    public static function name(): string { return 'Quote'; }

    public static function controls(): array
    {
        return [
            Control::textarea('text', 'Quote')->default('Something worth saying.'),

            Control::color('color', 'Colour')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-quote', 'color'),
        ];
    }
}
```

Register it in `config/builder.php`, add `resources/views/blocks/quote.blade.php`,
and it appears in the panel. A control with a `->selector()` is applied as CSS
with no re-render; one without it re-renders the element.

### Editing

- **Drafts and publishing.** Work autosaves as a private draft. Nothing reaches
  the public site until you press Publish.
- **History.** Each publish stores a revision; the last 25 are kept and any can
  be restored.
- **Responsive.** Switch between desktop, tablet and mobile in the toolbar. Any
  control marked responsive stores a separate value per breakpoint.
- **Design tokens.** Colours picked from the palette are stored as
  `var(--cb-color-primary)`, so changing a token later updates everything using
  it instead of leaving one-off hex codes behind.
- **Shortcuts.** Ctrl+Z / Ctrl+Shift+Z to undo and redo, Ctrl+S to publish,
  Ctrl+D to duplicate.

### Security

The builder is admin-only, but its output still ends up in a stylesheet and in
markup, so both are constrained:

- Settings written into CSS are stripped of anything that could close a rule or
  start a new one (`}`, `;`, comment markers, `expression()`, `@import`).
- Element ids are validated before they become class names, so a crafted id
  cannot escape its selector.
- `href` values accept only `http`, `https`, `mailto` and `tel`, plus relative
  paths — `javascript:` and `data:` URLs are dropped, including obfuscated
  forms like `java\tscript:`.
- Section wrapper tags come from a fixed allowlist; custom CSS ids and classes
  are stripped to safe characters.
- Incoming trees are capped at 2000 nodes and 6 levels deep, so a crafted
  request cannot make the renderer walk an enormous structure.

The HTML widget is the deliberate exception: outputting raw markup is its
entire purpose, and the editor says so plainly.

---

## Themes

Themes live in `themes/<slug>/` and are uploaded as a `.zip` from
Appearance → Themes.

```
my-theme/
├── theme.json          required: name, version
├── screenshot.png
├── assets/             copied to public/themes/<slug>/
│   ├── css/
│   └── js/
└── views/              required
    ├── layout.blade.php
    ├── home.blade.php
    ├── blog/
    ├── shop/
    ├── pages/
    └── partials/
```

A theme only has to override the views it wants to change. Anything it leaves
out falls back to the bundled default theme, so a partial theme still renders a
complete site.

### Theme security

A theme is executable code, so uploads go through several checks before
anything is written:

- Archive entries are resolved against the target directory; anything escaping
  it is rejected (the "zip slip" traversal bug).
- Only allow-listed extensions are extracted. A `.php`, `.phtml`, `.htaccess`
  or `.sh` file in the archive is dropped, never written to disk.
- Blade templates are scanned for raw `<?php` tags, shell execution, `eval`,
  filesystem writes, superglobal access and PHP `include`/`require`. A theme
  using any of them is refused.
- Entry count and uncompressed size are capped, so a zip bomb cannot fill
  the disk.

Extraction happens in a temporary directory and the theme is only moved into
place once every check has passed.

`@php` blocks are allowed — preparing a few view variables is ordinary template
work — but they are reported as a warning, and anything dangerous inside one is
still caught by the rules above.

### Useful helpers in themes

```blade
{{ setting('site_name') }}          {{-- any admin setting --}}
@seoHead                            {{-- title, meta, Open Graph, JSON-LD --}}
@module('shop') ... @endmodule      {{-- only render when a module is on --}}
@money($product->price)             {{-- format minor units as currency --}}
{{ theme_asset('css/theme.css') }}  {{-- URL to a file in your assets folder --}}
{{ format_date($post->published_at) }}
$siteMenus['header']                {{-- menu items, module-filtered --}}
```

### The theme directory

**Appearance → Browse themes** lists free themes from a catalogue we host, with
search, tags, a details page and one-click install. When a newer version of a
theme installed from there is published, **Appearance → Themes** badges it and
offers an in-place update.

Nothing about installing from the directory is looser than uploading a ZIP by
hand. The archive must be served over `https://` and match the SHA-256 the
catalogue publishes, and it then goes through the very same installer as an
upload — the same extension allowlist, the same template scan, the same size
limits. Installing is an administrator-only action for the same reason.

- **Only themes installed from the directory are updated from it.** A theme you
  uploaded yourself is never replaced, even if the directory lists one with the
  same folder name — it may carry your own edits. Uploading a ZIP over a
  directory theme likewise takes it out of the directory's hands.
- **An archive must install under the name it was listed as.** A listing called
  `aurora` whose ZIP declares a different slug is refused before anything is
  written, so no entry can overwrite some other installed theme.
- **Download addresses on your own network are refused**, checked on every
  redirect as well, so a catalogue cannot point your server at internal services.

**Privacy.** Browsing sends one request for the catalogue, carrying only the
product name and version. No site address, no email, and no list of what is
installed. A site that has never installed from the directory never contacts it
at all — updates are only looked up for themes that came from it. Screenshots
load from the directory's server; set `CMS_MARKETPLACE_REMOTE_IMAGES=false` to
stop that, or `CMS_MARKETPLACE_ENABLED=false` to remove the directory entirely.

Installing a theme from the directory means trusting whoever runs it, in exactly
the way installing an update trusts the release host. The template scan is a
safety net for honest mistakes, not a sandbox.

#### The catalogue file

`CMS_MARKETPLACE_URL` points at one static JSON file, so the directory can be
hosted on anything that serves files over HTTPS — no server-side code is needed.
A fork points it at its own.

```json
{
  "format": 1,
  "generated_at": "2026-09-16T10:00:00Z",
  "items": [
    {
      "slug": "aurora",
      "name": "Aurora",
      "version": "1.2.0",
      "author": "InsertCart",
      "author_url": "https://www.insertcart.com",
      "description": "A calm editorial theme for blogs and small shops.",
      "tags": ["blog", "minimal", "dark"],
      "supports": ["blog", "shop", "pages"],
      "screenshot": "https://www.insertcart.com/marketplace/aurora/screenshot.png",
      "screenshots": ["https://www.insertcart.com/marketplace/aurora/1.png"],
      "preview_url": "https://demo.insertcart.com/aurora",
      "download": "https://www.insertcart.com/marketplace/aurora/aurora-1.2.0.zip",
      "sha256": "9f2c…",
      "size": 482100,
      "requires": "1.1.0",
      "tested": "1.1.8",
      "license": "MIT",
      "updated_at": "2026-09-10"
    }
  ]
}
```

Each entry needs `slug`, `name`, `version`, `download` and `sha256`; the rest is
optional. The same rules as the update manifest apply:

- **`version` must be a string.** An entry whose version is a JSON number is
  skipped, because `1.10` written as a number reads as `1.1`.
- **`slug` must match the theme's own `theme.json`**, or sites refuse the install.
- Unknown keys are ignored, and one malformed entry is skipped rather than
  breaking the whole directory. Raise `format` only if the file's structure
  changes.
- Links are only used when they are `http(s)://`, and images only when they are
  `https://`.
- Entries with `"price"` above zero or `"requires_license": true` are skipped by
  this release, which only installs free themes. That is what lets paid listings
  be added to the same file later without older sites offering a download that
  would fail.

A layout that works:

```
/marketplace/themes.json
/marketplace/<slug>/screenshot.png
/marketplace/<slug>/<slug>-<version>.zip
```

Keep old version ZIPs in place for a while, so a site part-way through an
install does not hit a missing file.

#### Listing a theme

```bash
php artisan cms:marketplace-entry path/to/aurora-1.2.0.zip \
    --url=https://www.insertcart.com/marketplace/aurora
```

prints the entry for that ZIP with its checksum, size, slug and version read
from the archive itself, ready to paste into `items`. It also warns about files
the installer would drop. Add `tags`, `preview_url` and a realistic `requires`.

**Before listing anything, install the ZIP through Appearance → Themes on a test
site.** Every site runs that same scan, so a theme refused locally is refused
everywhere.

---

## Uploads

Everything uploaded — images, PDFs, documents — goes through the media library
(Content → Media), whether it comes from that screen, the rich text editor, a
settings field or the visual builder. Each file is checked before it is saved:

- **The name and the contents must agree.** A file is stored only if its
  extension is on the allowed list *and* its detected contents match that
  extension. It is saved under the checked extension, never the name the
  browser sent.
- **Program code is refused**, including PHP hidden after real image data.
- **Double extensions** such as `photo.php.jpg` are refused.
- **SVGs are cleaned**: scripts, event handlers, external links and embedded
  frames are removed, and SVGs with entity declarations are refused.
- **Photos are re-encoded**, which strips EXIF metadata — including the GPS
  location phones embed — and anything appended after the image data.

Alt text is set per image in the media library, and asked for when an image is
inserted into content. Images without it are flagged on the library screen.

### The uploads folder never runs code

`storage/app/public/.htaccess` (served at `/storage`) refuses every file type
except the media formats the CMS accepts, and never hands anything to PHP.
`public/themes/.htaccess` does the same for theme assets.

**On nginx** `.htaccess` is ignored, so add the equivalent to your server block:

```nginx
location ^~ /storage/ {
    location ~* \.(php\d?|phtml|phar|pht|cgi|pl|py|sh|shtml|html?)$ { deny all; }
    add_header X-Content-Type-Options nosniff;
}

location ^~ /themes/ {
    location ~* \.(php\d?|phtml|phar|pht|cgi|pl|py|sh|shtml|html?)$ { deny all; }
    add_header X-Content-Type-Options nosniff;
}
```

After changing `php.ini`, **restart the web server**, not just PHP on the command
line — they can load separate configurations. System → System reports any
extension the web server is missing.

### Only `public/` belongs on the web

Point your document root at `public/`. Nothing else in the project is meant to
be reachable, and `.env` — the database password, the key that signs every
session cookie, the payment gateway secrets — sits one level above it.

In practice a lot of installs skip this: the ZIP gets extracted into
`public_html/mysite/`, `mysite/public/` opens fine in a browser, and nothing
gets changed. The project root then sits inside the web root, and
`mysite/.env` is a URL anybody can request.

The `.htaccess` in the project root covers exactly that case. It maps every
request into `public/`, which does two jobs at once: the site answers on
`https://example.com/` without `/public/` in the address, and nothing outside
`public/` can be reached by URL at all - not by a list of denied names, but
because no URL resolves anywhere else.

**On nginx**, or on any host where `AllowOverride` is off, that file does
nothing. Set the document root to `public/`, or add:

```nginx
root /path/to/project/public;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ ^/(app|bootstrap|config|database|lang|resources|routes|storage|tests|themes|vendor)/ { deny all; }
location ~ /\. { deny all; }
```

**The CMS checks this for you.** Under **System → Security** there is a button
that makes the server request its own `/.env`, `composer.json`, log file and
install lock, and reports what it actually got back. A green result means those
files are genuinely unreachable on your host - not that the layout looks right.
Re-run it after any hosting change.

If it finds something readable, treat it as a live leak: fix the server
configuration, then **change your database password and run
`php artisan key:generate`**. Assume anything that was readable has been read.

---

## Paid downloads

Files sold as digital products are **not** stored in the media library. That
folder is served straight off the web server, so the payment check would only
be as good as the secrecy of a URL — and the URL appears in the page source of
every order.

Instead they go to `storage/app/private/downloads/`, on a disk with no public
address at all, under a name with 24 random characters in it. The only way to
one is `/account/orders/{order}/download/{item}`, which checks that the order
belongs to the signed-in customer, that it has been paid for, that the line
item belongs to that order, and that the customer is inside the download limit.
Files are always sent as an attachment with an explicit
`application/octet-stream`, so a browser can never render one on this site.

- Upload the file on the product screen with **Type: Digital**. The path is
  never typed or posted, so a tampered form cannot aim a product at another
  file on the server.
- **Source code is accepted.** A ZIP full of PHP is a normal thing to sell, so
  downloads are not scanned for code the way media uploads are — nothing here
  is ever executed or rendered. The name still has to match the contents.
- Shop settings set **how many times** a customer may download a purchase
  (default 5, `0` for unlimited) and **for how long** (default forever).
- Replacing a product's file leaves the old one in place if anyone has bought
  it; each order keeps the exact file it paid for.
- `CMS_DOWNLOADS_DISK` in `.env` moves storage elsewhere. If you point it at
  S3, **keep the bucket private** — the whole arrangement depends on it.

Existing sites are migrated automatically: any digital file still sitting in
the public uploads folder is moved to private storage and deleted from the
public one the first time you run `php artisan migrate`.

---

## Updates

The CMS can install new releases itself. Point `CMS_UPDATE_URL` in `.env` at a
JSON file you host, and the site checks it once a day and offers the update
under **System → Updates**. Installing is always a deliberate click; nothing is
ever applied automatically.

### The manifest

```json
{
  "format": 1,
  "version": "1.1.0",
  "released_at": "2026-09-20",
  "tags": ["security", "breaking"],
  "requires_backup": true,
  "min_version": "1.0.0",
  "min_php": "8.2.0",
  "requires_extensions": ["gd", "zip"],
  "download": "https://www.insertcart.com/releases/myfile.zip",
  "sha256": "9f2c…",
  "size": 48210432,
  "notes": "Shown to the site owner before they install.",
  "changelog_url": "https://www.insertcart.com/changelog"
}
```

Only `version` and `download` are required. The rest have sensible defaults —
`requires_backup` defaults to **true**, on the grounds that silence should not
mean "skip the backup".

**`version` must be a string.** Written as a JSON number, `1.10` parses as
`1.1`, and `1.9` compares as greater than `1.10`, so sites would stop seeing
updates the moment your version numbers reach double digits. Versions are
compared with PHP's `version_compare()`.

**`sha256` is required by default.** The archive becomes program code running on
your buyers' servers, so a download nobody verified is a very large hole.
Produce it with `sha256sum myfile.zip` (or `Get-FileHash` on Windows), and
publish the release over `https://` — plain `http` is refused.

`tags` are free text and only ever displayed, so you can use whatever words you
like. Anything the CMS actually *acts* on has its own field — that way a typo in
a tag can never silently disable a backup.

### Adding fields later

The reader ignores keys it does not recognise, so you can add anything to this
file at any time without breaking sites already in the field. Missing keys fall
back to defaults, `tag` as a plain string works as well as `tags` as an array,
and the release may sit at the top level or inside a `"latest"` object.

`format` is the one field to leave alone: it tells an older install whether it
understands the document at all. If you ever restructure the file, raise
`format`, and installs too old to cope will say so plainly instead of guessing.

Write the JSON as **UTF-8 without a byte-order mark** if you can — although the
CMS strips one if it finds it, since Notepad and PowerShell both add one
invisibly and it would otherwise make a perfectly correct file unreadable.

### Cutting a release

Pushing a `v*` tag builds and publishes the release. Nothing else is needed:

```bash
# 1. Bump the version in config/cms.php  ->  'version' => '1.1.3'
git commit -am "Release 1.1.3"

# 2. Tag it. The message becomes the release description.
git tag -a v1.1.3 -m "Fixes a stored XSS in the media library.

tags: security"

# 3. Push both.
git push && git push --tags
```

GitHub Actions then installs the dependencies, builds the assets, packages the
ZIP, works out its checksum and publishes the release. Watch it under **Actions**.

**The tag and `config/cms.php` must agree**, and the workflow stops if they do
not. That check exists because the updater compares against `config/cms.php`: a
release tagged `v1.1.3` carrying `1.1.2` inside would install and then still
report itself as out of date, forever.

A `tags:` line anywhere in the tag message marks the release - `security`,
`breaking`, whatever you like. Those show as badges, and a security release is
surfaced to site owners immediately instead of waiting for the daily check.
Everything above that line becomes the release description, with a changelog of
the commits since the previous tag appended.

The checksum is generated and inserted automatically. GitHub has no concept of
one, and without it every site refuses the update.

#### Building by hand

`php artisan cms:release` still does the packaging locally - useful for testing
what a buyer receives, or for publishing outside GitHub:

```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan cms:release
composer install                       # put your dev tools back
```

It writes three files to `storage/app/private/releases/`:

| File | What it is |
| --- | --- |
| `radius-1.1.3.zip` | the release itself |
| `notes-1.1.3.md` | release notes, with the checksum already filled in |
| `manifest.json` | only needed if you host your own manifest instead of using GitHub |

Re-running it keeps notes you have written and refreshes the checksum, because
the archive has changed and a stale hash is worse than none - the notes would
look complete and every site would refuse the update.

> **Never publish GitHub's own "Source code (zip)".** It has no `vendor/` and no
> built assets, so it cannot boot. The updater ignores it and picks the uploaded
> asset instead, but a human following a link will not.

### How sites find updates

`CMS_UPDATE_URL` defaults to this project's releases API:

```
CMS_UPDATE_URL=https://api.github.com/repos/InsertCart/radius/releases/latest
```

A fork points it at its own repository, or at a hand-written JSON manifest - the
reader accepts both shapes. GitHub's endpoint already excludes drafts and
pre-releases, so a draft release is never offered to anybody.

Unauthenticated GitHub API calls are rate-limited per IP, which is ample for a
once-a-day check.

### Working on the source

`vendor/` and `public/build/` are build output and are not committed, so a
clone or a source-repository ZIP will not run until they are built:

```bash
composer install
npm install && npm run build
```

Opening a copy that has neither now gives a short page saying exactly that
rather than a PHP fatal about `vendor/autoload.php`.

**To test what a buyer actually receives, build a release and extract that** -
see below. A source download is not a release and never will be; testing with
one only tests the missing pieces.

### Building a release ZIP

```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan cms:release
```

That writes the ZIP and a starter `manifest.json` (with the SHA-256 already
filled in) to `storage/app/private/releases/`. Upload both, set the `download`
address in the manifest to wherever the ZIP now lives, and point
`CMS_UPDATE_URL` at the manifest.

**Do not publish a ZIP downloaded from version control.** It cannot work:

| Missing from a git archive | What the buyer sees |
| --- | --- |
| `vendor/` | A fatal error on `require .../vendor/autoload.php` — Laravel never boots |
| `public/build/` | The site loads with no CSS or JavaScript |

And one thing a git archive can wrongly *include*: `storage/installed`, the lock
file that says setup is finished. Ship it and the buyer's setup wizard never
opens. It is in `.gitignore` for that reason — if it was committed before,
remove it from the index with `git rm --cached storage/installed`.

`cms:release` handles all three, refuses to build when `vendor/` or
`public/build/` is missing, and ships the `.htaccess` files that keep uploads and
paid downloads from being served directly.

The ZIP must contain `config/cms.php` declaring the same version the manifest
promises; if the two disagree the update stops before touching anything.

### What a buyer's first request does

A fresh extract has no `.env`, and Laravel cannot start without an `APP_KEY` —
so the first request would die before reaching the wizard that writes one. The
CMS breaks that loop itself: on the first request it copies `.env.example` to
`.env` and generates an `APP_KEY` unique to that installation.

The key is generated on the buyer's server, never shipped. A key baked into the
download would be identical on every site that bought the product, and anyone
holding it could forge session cookies for all of them.

Sessions and cache default to **files**, not the database: the wizard needs
somewhere to keep a session before there is a database to keep one in.

### What an update does and does not touch

**Replaced**: `app/`, `vendor/`, `resources/`, `routes/`,
`database/migrations/`, `public/build/` and the bundled `themes/default`, plus
root files like `artisan` and `composer.json`. The exact list is in
`config/updates.php`, and it is an allowlist — a path not on it is never
written, however the archive is constructed.

**Never touched**: `.env`, everything under `storage/` (uploads, paid downloads,
logs), `public/storage`, `public/themes/`, and any theme other than the bundled
one. Your database is migrated, never reset.

**Your edits to shipped files are preserved.** The CMS records a checksum of
every file it ships; anything that no longer matches was changed on your site,
and it is skipped and reported rather than overwritten. Tick the override on the
update screen if you would rather take the new version.

### If something goes wrong

Before any file is replaced, the CMS takes a database dump and copies every file
it is about to overwrite. **Roll back** restores both, removes files the release
added, and drops tables its migrations created. The option stays available after
a successful update too, because an update can complete cleanly and still turn
out to have broken something.

Backups live in `storage/app/private/backups/`, which has no public URL. The
five most recent are kept.

On a server with SSH, `php artisan cms:update` does the same thing with no
request timeout to run into — the most reliable route for a large release.

---

## Security notes

- **Two-factor authentication** (TOTP) works with any authenticator app.
  Secrets and recovery codes are encrypted at rest. Authentication is two-step:
  the password check establishes the session but marks it unconfirmed, and every
  request is held at the challenge until a valid code is entered. Recovery codes
  work exactly once each.
- **Money is stored as integers** in the currency's minor unit, so repeated
  addition never accumulates the rounding error floats would.
- **Cart prices are snapshotted** when an item is added, and order totals are
  recalculated server-side at checkout. The browser sees prices but never gets
  to decide them.
- **Stock is decremented inside the order transaction**, so two people racing
  for the last item cannot both succeed.
- **Order numbers are unguessable**, so a customer cannot enumerate other
  people's orders.
- **Paid downloads are never served by URL.** They live outside the web root
  and leave only through a controller that has checked the order — see
  "Paid downloads" above.
- **Release archives are verified before they are trusted.** HTTPS only, a
  SHA-256 that must match, a version inside the archive that must agree with the
  manifest, and an allowlist of paths an update may write — so a release cannot
  reach `.env`, the uploads, or a theme you bought.
- **Directory themes are verified the same way, then scanned like an upload.**
  HTTPS only, a SHA-256 that must match, a slug that must match the listing, no
  downloads from the server's own network, and the same installer and template
  scan a hand-uploaded ZIP goes through.
- **Payment returns are always verified against the provider's API.** A
  redirect back from a gateway proves nothing on its own — the customer
  controls it.
- Login is rate limited per email *and* IP, so one attacker cannot lock out a
  legitimate user.
- `public/robots.txt` is deliberately absent: a static file there would shadow
  the dynamic route the SEO module serves. Do not add one back.

---

## Command line

```bash
php artisan cms:admin              # create or promote an admin account
php artisan cms:sync               # register new modules/gateways/settings after an upgrade
php artisan cms:sync --themes      # re-scan the themes folder
php artisan cms:demo               # install sample posts, pages and products
php artisan cms:demo --remove      # delete that sample content again
php artisan cms:marketplace-entry <zip> --url=<folder>  # print a theme directory listing for a ZIP
php artisan optimize:clear         # clear all caches
```

### Demo content

`cms:demo` fills an install with something to look at and test against: 12
blog posts, 9 pages and 15 products, plus the categories, tags, comments,
reviews, variants and placeholder images that go with them. The images are
drawn at run time rather than shipped, so nothing is added to the repository.

The catalogue is chosen to cover the cases a real one has - a product on sale
inside a date window, one out of stock, one on backorder, one digital, one
still in draft, a scheduled post, an unapproved comment - because a set of
fifteen identical in-stock products tests nothing.

It is not part of `db:seed`, so a normal install never gets it. Re-running the
command restores the demo records to their original state, which makes it a
quick way to reset a database you have been experimenting with. `--remove`
deletes exactly what the seeder created, keyed on slug, and leaves content you
wrote yourself alone.

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

Radius ships with its own logo and icon set so a fresh install looks finished
before anyone has uploaded anything. They are defaults, not fixtures: the
moment a site owner uploads their own under **Settings → General**, the
uploaded file wins everywhere.

Every mark comes in two inks. **`-light` describes the artwork, not the
background** — the light files are white, and they are the ones that go *on* a
dark surface.

| File | Ink | Used for |
| --- | --- | --- |
| `public/images/radius-logo.png` | dark | Default site logo (1271×1051) |
| `public/images/radius-logo-small.png` | dark | Same mark at 444×357 |
| `public/images/radius-logo-light.png` | white | Dark backgrounds |
| `public/images/radius-logo-small-light.png` | white | Dark backgrounds, small |
| `public/favicon.ico` | dark | The bare `/favicon.ico` browsers ask for unprompted |
| `public/favicon/` | dark | Full icon set + `site.webmanifest` |
| `public/favicon-light/` | white | Same set for browsers in dark mode |

### Placing a logo

Views do not read `setting('site_logo')` and do not pick an ink themselves.
They use one component, which names the **background** — the thing whoever
writes the tag can actually see:

```blade
<x-site-logo class="h-10 w-auto" />             {{-- follows the colour scheme --}}
<x-site-logo on="light" class="h-10 w-auto" />  {{-- surface is always pale --}}
<x-site-logo on="dark" small class="h-8" />     {{-- surface is always dark --}}
```

`on="auto"` (the default) resolves through the **Color scheme** setting. On
*Always light* or *Always dark* the choice is made server-side; on the default
*Follow visitor system setting* the component emits a `<picture>` with a
`prefers-color-scheme` source, because the server never learns what the
visitor's browser prefers.

Where the bundled views land:

| Surface | Background | Ink |
| --- | --- | --- |
| Admin sidebar | `bg-slate-900`, always | `on="dark"` |
| Default theme header | `bg-white/95`, always | `on="light"` |
| Storefront header and footer | `--sf-accent` yellow, always | `on="light"` |
| Sign-in and setup wizard | `bg-slate-100` | `on="light"` |
| Builder logo block | wherever it is dropped | editor's choice, defaults to auto |

The bundled themes are light-only, so they ask for `on="light"` explicitly
rather than `auto` — a white logo on a bar that never darkens is just an
invisible logo. A theme that does implement dark mode should use `auto`.

### Helpers

```php
site_logo_url($small = false)        // dark ink: upload, else shipped
site_logo_light_url($small = false)  // white ink: light upload, else upload, else shipped
site_favicon_url()                   // upload, else shipped
site_favicon_light_url()             // the dark-mode favicon
brand_asset('favicon_png_light')     // a shipped file, never an upload
site_color_scheme()                  // 'light' | 'dark' | 'system'
site_logo_is_custom()
```

`site_logo_light_url()` falls back to the owner's *ordinary* logo before it
falls back to ours. A site that uploaded one logo and never made a white
version is better served by their own mark at poor contrast than by somebody
else's brand turning up in their admin panel.

Icon tags live in one partial, `resources/views/partials/favicon.blade.php`,
included by both themes, the admin panel, the installer, the auth pages and the
error pages.

### Rebranding the product

For a fork or a white-label build, no view needs editing. Replace the files
above in place, or repoint the `brand` block in `config/cms.php`:

```php
'brand' => [
    'logo' => 'images/radius-logo.png',
    'logo_light' => 'images/radius-logo-light.png',
    'favicon' => 'favicon/favicon.ico',
    'favicon_light' => 'favicon-light/favicon.ico',
    // ...
],
```

Paths are relative to the web root and resolved through `asset()`, so they work
whether the document root points at `public/` or at the project folder.
`public/images/`, `public/favicon/` and `public/favicon-light/` are all in the
updater's `track_edits` list, so a logo you replaced in place survives an update
instead of being overwritten.

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
public/
├── images/                 default logos, dark ink and white
├── favicon/                default icon set and the web manifest
└── favicon-light/          the same icons for browsers in dark mode
config/
├── cms.php                 modules, themes, media, security, brand assets
├── settings.php            every admin setting, declared once
├── payments.php            gateway definitions and endpoints
└── builder.php             registered widgets, breakpoints, design tokens
themes/default/             the bundled starter theme
```

Adding a setting means one array entry in `config/settings.php` — the admin
form is generated from it. Adding a payment gateway means one driver class and
one entry in `config/payments.php`. Adding a builder widget means one block
class, one Blade view, and one entry in `config/builder.php`.
