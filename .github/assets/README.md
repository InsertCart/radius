# Repository images

Images that exist for **GitHub** — the README header, screenshots, diagrams,
social previews. Anything whose only job is to show the product off.

**This folder ships nowhere.** `.github` is in the `package.exclude` list in
[`config/updates.php`](../../config/updates.php), so `php artisan cms:release`
leaves it out of the ZIP buyers download. Screenshots can be as large and as
numerous as you like without adding a byte to the product.

## Add an image

1. Drop the file in this folder.
2. Reference it from `README.md` with a **repo-relative** path:

   ```markdown
   ![Admin dashboard](.github/assets/screenshot-dashboard.png)
   ```

   Use `<img src=".github/assets/..." width="700">` when you need to control the
   size — GitHub renders inline HTML in Markdown, and a full-width 2560px
   screenshot is otherwise unreadable.

Relative paths, not `https://raw.githubusercontent.com/...` URLs: relative ones
keep working on forks and on branches, and they do not break when the repository
is renamed or moved.

## Naming

| Pattern | For |
| --- | --- |
| `radius-logo.png` | The logo, as used in the README header |
| `radius-logo-small.png` | The small logo variant |
| `screenshot-<screen>.png` | UI screenshots — `screenshot-dashboard.png`, `screenshot-builder.png`, `screenshot-checkout.png` |
| `diagram-<topic>.png` | Architecture and flow diagrams |
| `social-preview.png` | The 1280×640 card for **Settings → Social preview** |

Lowercase, hyphenated, no spaces. A space becomes `%20` in Markdown and is a
recurring source of images that render locally and break on GitHub.

## Keep them small

Screenshots are PNGs of flat UI, which compress hard. Run new ones through
`pngquant`, `oxipng` or Squoosh before committing — git keeps every version of
a binary forever, so an unoptimised 4 MB screenshot is 4 MB in the clone
permanently, even after you delete it.

Target under ~500 KB per screenshot. Use JPEG for anything photographic.

## Not to be confused with

| Folder | Contents | Ships to buyers |
| --- | --- | --- |
| `.github/assets/` | README and marketing images | **No** |
| `public/images/` | The default site logo | Yes |
| `public/favicon/` | The default icon set | Yes |

A logo used in both places is committed to both. They are deliberately separate
copies: one is a product asset a buyer may replace, the other is documentation
about the product and should not change when they do.
