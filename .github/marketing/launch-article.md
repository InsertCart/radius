# You Don't Need a Subscription to Build a Website. You Need an Afternoon.

**If you have been googling "how to make a website for my business," you have probably noticed that every answer costs $29 a month forever. Here is a fourth option — one you install once and then own.**

---

## "I just want a website. Where do I start?"

Ask the internet that question and you get three answers, all of them a little bit of a trap.

**Road one: the hosted website builder.** Sign up, drag some boxes, and you are live before lunch. Then the invoice starts. Want a store? That is the higher tier. Want to accept cards? They take a slice of every order. Want to move your site somewhere cheaper in two years? You cannot — you were never given the files. You were renting the whole time.

**Road two: WordPress and twenty plugins.** Free, until it isn't. One plugin for the page builder, one for SEO, one for forms, one for the shop, one for caching because the first four made it slow. Five vendors, five update schedules, five chances that a Tuesday morning update takes your homepage down.

**Road three: hire someone.** Excellent sites come out of this. So do quotes with a comma in them, and a three-week wait every time you want to change a phone number.

**Road four is the one nobody advertises:** install a complete, self-hosted platform on ordinary cheap hosting, once, and stop paying rent on your own website.

That is Radius.

---

## What Radius actually is

A modular content management system and online store, built on Laravel 12, **open source under the MIT licence.** You download it, you install it on your own hosting, and it is yours — commercially, permanently. No seats, no monthly fee, no revenue share, no "contact sales."

It runs on PHP 8.2 and MySQL. That means it runs on the cheap shared hosting plan you can buy in the next five minutes. No Docker, no build step on the server, no DevOps hobby required.

---

## How to build a website with it in one afternoon

1. **Upload the files** to your host and point your domain at them.
2. **Create an empty MySQL database.** Your host's control panel does this in two clicks.
3. **Open your site in a browser.** The setup wizard starts on its own.
4. **Answer four screens:** server check, database, site details, admin account.

The wizard verifies your server can actually run the thing *before* it starts, writes the configuration, builds the database, and creates your login. Then it locks itself shut, so nobody can ever re-point your live site at a different database.

Want something to look at immediately instead of an empty dashboard? One command fills the site with a real demo: 12 blog posts, 9 pages, 15 products, with the categories, reviews, variants and images that go with them — including the awkward cases a genuine catalogue has, like an item out of stock, one on backorder, one on sale inside a date window, one digital. One more command deletes all of it when you are ready for your own content.

---

## Drag and drop, without the JavaScript tax

Every modern website builder promises drag-and-drop. The interesting question is what it leaves behind in the page.

Most visual builders ship a small application to every visitor: a stack of scripts that assembles your homepage in the reader's browser. It works. It also costs you loading time, and loading time is something Google measures.

**The Radius builder renders on the server.** You drag, you drop, you publish — and what goes out to the world is plain HTML with one stylesheet. No JavaScript is required to read your page. Fast by construction, indexable by default, and still perfectly readable on a bad phone connection in a lift.

Inside the editor you get the things that actually matter day to day:

- **27 widgets** — headings, buttons, galleries, maps, accordions, tabs, pricing tables, testimonials, counters, contact forms, post grids, product grids, menus, cart icon, social icons.
- **Instant styling.** Colour, spacing and type controls rewrite the preview's stylesheet as you drag the slider. No waiting on the server between two shades of blue.
- **Drafts that stay private.** Work autosaves as a draft; nothing appears on the public site until you press Publish.
- **History.** Every publish is a revision. The last 25 are kept, and any one of them can be restored — so "put it back how it was" is a click, not a phone call.
- **Real responsive control.** Switch to tablet or mobile in the toolbar and set a different value just for that size.
- **Design tokens.** Pick a colour from your palette and it is stored as a token, not a stray hex code. Change your brand colour once next year and every element that used it follows.

And here is the part page builders usually get wrong: **it does not hijack your theme.** A theme keeps owning its own header and footer unless you explicitly decide to design one yourself. Turn the builder off for a page and the original content comes straight back. Nothing here is a one-way door.

---

## How to open an online store without giving away a cut of it

Search "how to start an online store" and every result wants a percentage.

The shop in Radius is not a plugin bolted onto a blog engine — it is a module built into the same codebase, and there is no platform sitting between you and your customer's money. **Eight ways to get paid:** Stripe, PayPal, Razorpay, PayU, Cashfree, Wise bank transfer, cash on delivery, and manual bank transfer. Card fees are whatever you negotiate with your processor. Nobody takes a second bite.

Underneath, the boring parts are done properly — which you only appreciate the first time a cheaper platform gets them wrong:

- **Money is stored as whole units of currency**, never as decimals that drift. Add a thousand line items and the total is still exactly right.
- **Prices are snapshotted** when an item goes into the cart, and every total is recalculated on the server at checkout. The browser gets to display prices. It never gets to decide them.
- **Stock comes down inside the order transaction**, so two people racing for your last unit cannot both win.
- **Order numbers are unguessable**, so no customer can go fishing through other people's orders by changing a number in the address bar.
- **Selling files?** Paid downloads never live at a URL. They sit outside the web root entirely and leave only through a check that the order exists, has been paid for, and belongs to whoever is asking. Each buyer keeps the exact file they paid for, even after you upload a newer version.
- **Gateway credentials are encrypted at rest** and are never sent back to the browser once saved.

---

## Will Google actually find it?

The SEO module is built in, not sold separately:

- Per-page titles, descriptions and social share images
- Automatic `sitemap.xml` covering pages, posts and products
- `robots.txt`, canonical URLs, and `noindex` where you want it
- schema.org structured data and breadcrumbs
- Redirect management, so changing a URL does not cost you the ranking you built

Combine that with server-rendered HTML and you are handing search engines the version of your site they actually prefer.

---

## The part other website builders leave off the pricing page

If you are going to own your website, security stops being someone else's department. So it is built into the defaults rather than sold as an add-on:

- **Two-factor authentication** with any authenticator app, with encrypted secrets and one-time recovery codes.
- **Uploads are inspected, not trusted.** A file's contents must match its extension. Code disguised as an image is refused. `photo.php.jpg` is refused. SVGs are stripped of scripts. Photos are re-encoded, which quietly removes the GPS coordinates your phone buried in them.
- **The uploads folder cannot run code**, by server configuration rather than by hope.
- **Logins are rate limited** by email *and* by IP, so an attacker hammering your address cannot lock you out of your own site.
- **A button that checks your host for you.** Under System → Security, the CMS makes your own server request its `.env`, its config files and its logs, then reports what actually came back. Not a diagram of how it *should* be — proof of what a stranger can really reach.
- **Updates are verified before they are trusted.** HTTPS only, a checksum that must match, and a strict list of paths an update is allowed to write — so a release can never overwrite your configuration, your uploads, or a theme you paid for. And nothing installs itself: it waits for your click.

---

## You only carry what you switch on

Radius ships as **12 modules** — blog, pages, shop, payments, SMS, push notifications, SEO, media, themes, users, contact forms, newsletter — and you toggle them from the admin panel.

A switched-off module is not merely hidden. It registers no routes, runs no queries, adds no menu entries, and its widgets disappear from the builder palette. **A blog-only site carries none of the shop's weight.** Turn it back on later and everything is exactly where you left it — nothing is ever deleted.

This is the opposite of the plugin model, where every feature you tried in 2023 is still loading on every page today.

---

## An honest comparison

| | Hosted builder | WordPress + plugins | Radius |
|---|---|---|---|
| Monthly fee | Forever | Hosting + premium plugins | Hosting only |
| Cut of your sales | Often | Depends on the stack | **None** |
| Who owns the files | They do | You do | **You do** |
| Store included | Higher tier | Another plugin | **Built in** |
| Page builder | Yes, JavaScript-heavy | Another plugin | **Built in, server-rendered** |
| SEO tools | Basic | Another plugin | **Built in** |
| Vendors to trust | 1 | 5–20 | **1, and you can read the code** |
| Can you leave? | Not really | Yes, messily | **It is MIT — fork it** |

---

## Who this is not for

Fair is fair. If you want somebody else to handle the hosting, the backups and the SSL certificate, and you are happy paying monthly for that peace of mind, a hosted builder is a reasonable purchase and you should buy one.

Radius is for the other kind of person: the one who would rather spend one afternoon and own the result. Small business owners tired of the monthly bill. Agencies who need to hand a client a finished site without also handing them a subscription. Developers who want a real Laravel codebase underneath instead of a decade of somebody else's backward compatibility.

---

## Frequently asked

**Do I need to know how to code?**
No. Install, then drag. The code is there if you ever want it — that is the point of owning it — but building pages, writing posts and running a shop never requires it.

**Can I use it on cheap shared hosting?**
Yes. PHP 8.2+ and MySQL is the whole requirement list, and the wizard checks your server before it installs anything.

**Is it really free?**
The software is MIT licensed: free to use, free commercially, free to fork, free to build closed-source themes on top of. You pay for hosting, like every website that has ever existed.

**Can I move an existing site to it?**
Your content, yes. Your theme will be rebuilt — templates are Blade, not PHP sprinkled through a functions file.

**Can I sell digital products?**
Yes, with real access control: private storage, per-order permissions, download limits and expiry.

**Can I make it look like my own product?**
Yes. Logos, favicons and the product name are configuration values, not hardcoded markup. Agencies can rebrand the admin panel without editing a single view.

---

## Try it once

Install it on a spare subdomain. Run the demo content command. Spend twenty minutes in the builder.

Worst case, you have lost an afternoon. Best case, you never pay for a website again.

**[Download Radius]** · **[Read the documentation]** · **[See it running]**
