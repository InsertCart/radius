# Zenith — Luxury Editorial & Commerce Theme

Zenith is a modern, high-end theme for Radius CMS. Designed for curated boutiques, luxury ateliers, design studios, and editorial publications.

It pairs deep obsidian dark mode with warm porcelain light mode, featuring a glassmorphic sticky masthead, interactive product rails, brand value pillars, category bento showcases, and modular sections built for the Radius Visual Builder.

---

## Highlights

- **Dual-Mode System**: Supports system preference as well as an instant visitor dark/light mode toggle with persistent `localStorage` memory.
- **Pure CSS Design System**: Ships standalone `assets/css/theme.css` and `assets/js/theme.js`. Zero Node.js or Vite build step required on the server.
- **Visual Builder Integration**: Comes with pre-configured `starters.json` layouts and 9 customizable widget sections for any page or region.
- **Client-Side Wishlist**: Built-in saved items drawer using browser storage with live header counter badges.
- **Live Search Support**: Integrated with Radius CMS autocomplete search via `--radius-search-*` skinning.
- **Editorial Experience**: Rich story templates with estimated reading times, author metadata, and category filters.

---

## Installation

### Method 1: Upload ZIP Archive (Recommended)
1. In your Radius Admin Panel, navigate to **Appearance → Themes**.
2. Click **Upload Theme** and select `zenith-1.0.0.zip`.
3. Once uploaded, click **Activate** on the Zenith card.

### Method 2: Manual Directory Placement
1. Copy the `zenith/` folder directly into your CMS's `themes/` directory.
2. In the Admin Panel, visit **Appearance → Themes** (this automatically re-scans installed themes).
3. Click **Activate** on Zenith.

---

## Customizing Design Tokens

Colours, radii, typography, and spacing are defined as CSS custom properties at the top of `assets/css/theme.css`:

```css
/* Light Mode */
html.light {
    --zn-bg: #f8fafc;
    --zn-card: #ffffff;
    --zn-accent: #2563eb;
    --zn-text: #0f172a;
}

/* Dark Mode */
html.dark {
    --zn-bg: #07090e;
    --zn-card: #111622;
    --zn-accent: #38bdf8;
    --zn-text: #f8fafc;
}
```

---

## Declared Navigation Menus

Under **Appearance → Menus**, you can assign items to the following locations:

| Menu Location | Description |
|---|---|
| `header` | Primary masthead navigation. Supports multi-level dropdowns. Falls back to shop categories if empty. |
| `footer` | First navigation column in the footer. |
| `footer_collections` | Second navigation column in the footer for product collections. |
| `footer_legal` | Bottom footer links (Privacy Policy, Terms, etc.). |

---

## Declared Visual Builder Regions

Zenith exposes the following regions for the visual page builder:

| Region Slug | Description |
|---|---|
| `announcement` | Top notification / promotional bar |
| `header` | Site header and masthead |
| `home` | Whole homepage container |
| `hero` | Homepage hero banner |
| `home_bottom` | Homepage area below rails and features |
| `footer` | Site footer |
| `before_content` | Injected before single page content |
| `after_content` | Injected after single page content |
| `shop_index` | Shop catalog page layout |
| `cart` | Shopping bag / cart view |
| `checkout` | Order acquisition checkout view |

---

## Visual Builder Section Widgets

Zenith registers 9 custom widgets ready to drop into any region:
1. `hero` — Luxury headline, eyebrow tag, action buttons, and live trust metrics.
2. `features` — 4-pillar brand guarantees (Master Craftsmanship, Responsible Sourcing, Climate-Neutral, Private Concierge).
3. `categories` — Category bento showcase with imagery and piece counters.
4. `rail` — Product showcase grid/rail (latest, featured, on-sale, or category-filtered).
5. `journal` — Latest editorial essays and stories.
6. `newsletter` — High-conversion VIP invitation capture.
7. `announcement` — Promotional notification banner.
8. `header` — Zenith glassmorphic header.
9. `footer` — Zenith multi-column footer.

---

## License

Released under the MIT license, compatible with Radius CMS.
