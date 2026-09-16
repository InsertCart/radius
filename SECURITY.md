# Security policy

## Reporting a vulnerability

**Please do not open a public issue for a security problem.** A public report
tells attackers before it tells site owners, and every Radius install stays
vulnerable until a fix ships.

Use GitHub's private reporting instead:
<https://github.com/InsertCart/radius/security/advisories/new>

Please include what an attacker can do, the steps to reproduce it, and the
version you tested. A proof of concept helps enormously.

You should get a first response within a few days. Once a fix is ready it goes
out as a release tagged `security`, which every install surfaces immediately
rather than waiting for its daily check.

## What is in scope

The CMS itself: authentication and two-factor, the admin panel, file uploads and
the media library, paid downloads, the visual builder's stored HTML, payment
webhook verification, the theme installer, the theme directory (catalogue
parsing, download verification and install), and the self-updater.

Themes *listed* in the theme directory are reviewed before publication, but a
theme's own code is its author's. Report a problem in a listed theme to us all
the same, so the listing can be pulled while it is fixed.

Third-party themes and plugins are not maintained here - report those to their
authors. Findings that require an account you already control to attack only
itself, or that depend on a server misconfiguration the CMS warns about, are
usually not vulnerabilities in Radius.

## For site owners

Under **System → Security** there is a check that asks your own server whether
files like `.env` are readable over the web. Run it after any hosting change.

If it ever reports a leak, treat the credentials as compromised: fix the server
configuration, change the database password, and run `php artisan key:generate`.
