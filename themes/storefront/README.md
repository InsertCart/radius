# Storefront

A retail template for catalogues with a lot of products — the kind of layout a
bookshop, toy shop or department store uses: a sticky yellow masthead with a
category mega-menu and a search field, horizontally scrolling product rails on
the homepage, and a product page with a thumbnail gallery, offer callouts and
collapsible detail panels.

It sits alongside the bundled `default` theme rather than replacing it. Switch
between them under **Appearance → Themes**.

---

## Installing

The folder is already in `themes/`. Open **Appearance → Themes** in the admin
panel and press **Activate** on Storefront. Activating copies `assets/` to
`public/themes/storefront/`, which is where the browser loads the stylesheet
and script from.

If the theme does not appear in the list, the theme table needs a re-scan —
visiting the Themes screen does that on its own.

### No asset build required

The theme ships its own hand-written `assets/css/theme.css` and does not depend
on Tailwind classes being compiled. You do **not** need to run `npm run build`
after activating it, and it renders correctly on a shared host with no Node
installed.

The layout still loads the CMS bundle, because that supplies the `prose-content`
styles for editor output and the stylesheet for visual-builder blocks.

---

## What each page looks like

| View | What it renders |
| --- | --- |
| `home` | Hero, featured rail, department circles, a rail per top category, promo tiles, blog row |
| `shop/index`, `shop/category` | Breadcrumb, filter sidebar, sort toolbar, product grid |
| `shop/show` | Thumbnail gallery, buy box, Add to bag + Buy now, trust row, Description / Product details accordions, reviews, related rail |
| `shop/cart` | Line items with quantity steppers, coupon field, sticky summary |
| `shop/checkout` | Contact, billing, optional delivery address, payment method, sticky order panel |
| `blog/*` | Post grid with a topic sidebar, article page with comments |
| `account/*` | Sidebar navigation, orders table, order detail with downloads, profile forms |

---

## Changing the look

**Colours, spacing and radii** are CSS custom properties at the top of
`assets/css/theme.css`:

```css
:root {
    --sf-accent: #ffd400;   /* masthead, footer, primary buttons */
    --sf-ink: #141416;      /* body text and dark buttons */
    --sf-green: #0a7a4b;    /* savings chips */
    --sf-wrap: 1280px;      /* page width */
}
```

Edit them and re-activate the theme (or re-save it under Appearance → Themes) so
`assets/` is copied to `public/themes/storefront/` again. During development you
can edit `public/themes/storefront/css/theme.css` directly and copy the result
back when you are happy.

**The announcement bar** text lives in
`views/partials/announcement.blade.php`. It also reads a
`storefront_announcement` setting, so if you add that key to
`config/settings.php` it becomes editable from the admin panel without touching
the theme.

**Icons** are all in `views/partials/icon.blade.php`. Add a `@case` and it is
available everywhere as
`@include('theme::partials.icon', ['name' => 'your-icon'])`.

---

## Menus

Declared in `theme.json` and assigned under **Appearance → Menus**:

| Slug | Where it shows |
| --- | --- |
| `header` | Main navigation. Items with children get a mega-menu panel. |
| `footer` | First footer column |
| `footer_help` | Second footer column ("Useful links") |
| `footer_about` | Third footer column ("About us") |
| `footer_support` | Fourth footer column ("Store & support") |

**With no `header` menu built**, the navigation falls back to your top-level
shop categories that have "show in menu" ticked, with their children as the
mega-menu columns. For most shops that is the better option: the menu maintains
itself as the catalogue grows.

---

## Builder regions

Every region below can be designed in the visual builder, and each falls back to
the theme's own markup until you build one:

`announcement`, `header`, `footer`, `hero` (homepage), `home_bottom`,
`before_content`, `after_content`, `shop_index`, `cart`, `checkout`.

---

## Things worth knowing

**Saved items are browser-only.** The CMS has no wishlist table, so the heart
button stores a short list in `localStorage` and the drawer reads it back.
Nothing is sent to the server and nothing follows the customer to another
device. Delete the `[data-fav]` button from
`views/partials/product-card.blade.php` and the saved-items tool from
`views/partials/header.blade.php` if you would rather not offer it.

**"Buy now"** adds the item to the bag and then sends the customer to checkout.
It is an ordinary submit button, so with JavaScript disabled it simply adds to
the bag — nobody gets a dead control.

**The "by" line on product cards** shows the product's first category, because
the catalogue has no separate brand or author field. It only renders where the
`categories` relation is already loaded, which keeps listing pages to one query
instead of one per card.

**The homepage runs three extra queries** — one per category rail — to build the
multi-row layout. They are capped at three rails of ten products. If your
homepage feels slow with a very large catalogue, trim the `limit(3)` in
`views/home.blade.php`.

**Dark mode.** The theme is deliberately light-only, like most retail sites.
Setting Appearance → Colour scheme to dark will not change it.

---

## Overriding just a few views

You do not have to copy this whole folder to build on it. A theme only needs the
views it wants to change — anything missing falls back to the `default` theme.
Copying `storefront/` and deleting everything except, say, `views/home.blade.php`
gives you the default theme with this homepage.
