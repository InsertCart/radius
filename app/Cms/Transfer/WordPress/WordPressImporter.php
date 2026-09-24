<?php

namespace App\Cms\Transfer\WordPress;

use App\Cms\Support\HtmlSanitizer;
use App\Cms\Transfer\ImportContext;
use App\Cms\Transfer\ImportOptions;
use App\Cms\Transfer\ImportReport;
use App\Cms\Transfer\MediaRefused;
use App\Cms\Transfer\MediaSideloader;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Brings a WordPress site in from its own export file.
 *
 * WordPress calls these things posts, pages, attachments and products;
 * underneath, they are all rows in one table told apart by post_type. The
 * mapping to this CMS is mostly obvious and occasionally a judgement call, and
 * every judgement made here is written down where it is made.
 *
 * The file is read in stages, and the order is the whole design. Terms first,
 * so a post has somewhere to be filed. Attachments second, so a post's
 * featured image already exists when the post arrives. Content third. Menus
 * last, because a menu item is a pointer at something the earlier stages were
 * busy creating.
 *
 * What is deliberately not attempted: plugins, widgets, theme settings, users'
 * passwords, and shortcodes. None of those mean anything outside WordPress,
 * and pretending otherwise would produce a site that looks imported and is
 * quietly broken.
 */
class WordPressImporter
{
    /** WordPress post types this importer understands, mapped to our own keys. */
    private const TYPES = [
        'post' => 'posts',
        'page' => 'pages',
        'product' => 'products',
        'attachment' => 'media',
        'nav_menu_item' => 'menus',
    ];

    /** Statuses that mean "not really content": never imported. */
    private const IGNORED_STATUSES = ['trash', 'auto-draft', 'inherit'];

    /** WordPress post id => the record it became here. */
    private array $posts = [];

    private array $pages = [];

    private array $products = [];

    /** WordPress attachment id => media id. */
    private array $attachments = [];

    /** Author login => the details the file gave for them. */
    private array $authors = [];

    /** Child WordPress id => parent WordPress id, for page nesting. */
    private array $parents = [];

    public function __construct(
        private ContentFormatter $formatter,
        private HtmlSanitizer $sanitizer,
        private MediaSideloader $sideloader,
    ) {}

    /**
     * Reads the file without importing, so the screen can say what is in it.
     *
     * @return array{site: array, counts: array, types: array, authors: array, terms: array, comments: int}
     */
    public function inspect(string $path): array
    {
        $reader = new WxrReader($path);
        $reader->validate();

        $counts = [];
        $comments = 0;

        foreach ($reader->items() as $item) {
            if (in_array($item['status'], self::IGNORED_STATUSES, true) && $item['type'] !== 'attachment') {
                continue;
            }

            $counts[$item['type'] ?: 'unknown'] = ($counts[$item['type'] ?: 'unknown'] ?? 0) + 1;
            $comments += count($item['comments']);
        }

        arsort($counts);

        $terms = $reader->terms();

        $types = [];

        foreach (self::TYPES as $wordpress => $key) {
            if (($counts[$wordpress] ?? 0) > 0) {
                $types[$key] = ($types[$key] ?? 0) + $counts[$wordpress];
            }
        }

        if (($terms['categories'] ?? []) !== [] || ($terms['taxonomies']['product_cat'] ?? []) !== []) {
            $types['categories'] = count($terms['categories'] ?? []) + count($terms['taxonomies']['product_cat'] ?? []);
        }

        if (($terms['tags'] ?? []) !== []) {
            $types['tags'] = count($terms['tags']);
        }

        if ($comments > 0) {
            $types['comments'] = $comments;
        }

        return [
            'site' => $reader->site(),
            'counts' => $counts,
            'types' => $types,
            'authors' => $reader->authors(),
            'terms' => [
                'categories' => count($terms['categories'] ?? []),
                'tags' => count($terms['tags'] ?? []),
                'product_categories' => count($terms['taxonomies']['product_cat'] ?? []),
                'menus' => count($terms['taxonomies']['nav_menu'] ?? []),
            ],
            'comments' => $comments,
        ];
    }

    public function run(string $path, ImportOptions $options, bool $timed = true): ImportReport
    {
        $reader = new WxrReader($path);
        $reader->validate();

        $report = new ImportReport;
        $context = new ImportContext($options, $report, $this->sideloader);
        $context->sourceUrl = $reader->site()['url'];

        if ($timed) {
            $context->limitTo((int) config('transfer.web_time_limit', 600));
        }

        $this->reset();
        $this->authors = $reader->authors();

        if ($options->importMedia && ! $options->downloadMedia) {
            $report->warn('A WordPress export never contains the image files themselves, only their addresses. Tick "fetch images from the old site" to bring the pictures across.');
        }

        $terms = $reader->terms();

        $this->importTerms($terms, $context);
        $this->importAttachments($reader, $context);
        $this->importContent($reader, $context);
        $this->linkPages($context);
        $this->importMenus($reader, $terms, $context);

        if ($report->stoppedEarly) {
            $report->warn('The import stopped at the time limit. Run it again - what already came across is skipped - or use "php artisan cms:import-wordpress" for a large site.');
        }

        activity('content.imported', 'Imported a WordPress export. '.$report->summary());

        return $report;
    }

    private function reset(): void
    {
        $this->posts = [];
        $this->pages = [];
        $this->products = [];
        $this->attachments = [];
        $this->parents = [];
    }

    // Terms -------------------------------------------------------------------

    /**
     * Categories and tags.
     *
     * WordPress keeps blog categories and WooCommerce product categories in
     * separate taxonomies; this CMS keeps both in one table told apart by
     * type, so each goes to its own side of that and the two cannot collide
     * even when they share a slug.
     */
    private function importTerms(array $terms, ImportContext $context): void
    {
        if ($context->options->wants('categories')) {
            foreach ($terms['categories'] ?? [] as $term) {
                $this->category($term, Category::TYPE_BLOG, $context);
            }

            foreach ($terms['taxonomies']['product_cat'] ?? [] as $term) {
                $this->category($term, Category::TYPE_SHOP, $context);
            }

            // Parents are joined up afterwards, because WordPress writes terms
            // in id order and a child can perfectly well come first.
            $this->linkTerms($terms['categories'] ?? [], Category::TYPE_BLOG);
            $this->linkTerms($terms['taxonomies']['product_cat'] ?? [], Category::TYPE_SHOP);
        }

        if (! $context->options->wants('tags')) {
            return;
        }

        foreach ($terms['tags'] ?? [] as $term) {
            $existing = Tag::where('slug', $term['slug'])->first();

            if ($existing && ! $context->options->updatesExisting()) {
                $context->report->record('tags', ImportReport::SKIPPED);

                continue;
            }

            $tag = $existing ?: new Tag(['slug' => $term['slug']]);
            $tag->fill(['name' => $term['name'], 'description' => $term['description'] ?? null])->save();

            $context->report->record('tags', $existing ? ImportReport::UPDATED : ImportReport::CREATED);
        }
    }

    private function category(array $term, string $type, ImportContext $context): Category
    {
        $existing = Category::where('type', $type)->where('slug', $term['slug'])->first();

        if ($existing && ! $context->options->updatesExisting()) {
            $context->report->record('categories', ImportReport::SKIPPED);

            return $existing;
        }

        $category = $existing ?: new Category(['type' => $type, 'slug' => $term['slug']]);

        $category->fill([
            'type' => $type,
            'name' => $term['name'],
            'description' => $term['description'] ?? null,
        ])->save();

        $context->report->record('categories', $existing ? ImportReport::UPDATED : ImportReport::CREATED);

        return $category;
    }

    private function linkTerms(array $terms, string $type): void
    {
        foreach ($terms as $term) {
            if (blank($term['parent'] ?? null)) {
                continue;
            }

            $child = Category::where('type', $type)->where('slug', $term['slug'])->first();
            $parent = Category::where('type', $type)->where('slug', $term['parent'])->first();

            if ($child && $parent && ! $child->is($parent)) {
                $child->parent_id = $parent->id;
                $child->save();
            }
        }
    }

    // Attachments ---------------------------------------------------------------

    /**
     * The media library.
     *
     * A WXR file names its images; it does not carry them. So unless the
     * person asked for the files to be fetched, all this can do is match an
     * address against something already in this library - which is exactly
     * what a second run of the same import wants.
     */
    private function importAttachments(WxrReader $reader, ImportContext $context): void
    {
        if (! $context->options->wants('media') || ! $context->options->importMedia) {
            return;
        }

        foreach ($reader->items(['attachment']) as $item) {
            if ($context->outOfTime()) {
                $context->report->stoppedEarly = true;

                return;
            }

            $url = $item['attachment_url'];

            if (blank($url)) {
                continue;
            }

            // Already here from a previous run, matched on the filename this
            // library stored it under.
            if ($media = $this->findByFilename($url)) {
                $this->attachments[$item['id']] = $media->id;
                $context->mapMedia($url, $media);
                $context->report->record('media', ImportReport::SKIPPED);

                continue;
            }

            if (! $context->options->downloadMedia) {
                continue;
            }

            try {
                $media = $this->sideloader->fromUrl($url, $context->options->authorId);
            } catch (MediaRefused $e) {
                $context->report->fail('media', basename($url).' - '.$e->getMessage());

                continue;
            }

            if (! $media) {
                $context->report->fail('media', basename($url).' could not be fetched.');

                continue;
            }

            if (filled($item['title'])) {
                $media->fill(['name' => Str::limit($item['title'], 190, ''), 'alt' => Str::limit($item['title'], 190, '')])->save();
            }

            $this->attachments[$item['id']] = $media->id;
            $context->mapMedia($url, $media);

            // Scaled copies are referenced inside post bodies by their own
            // addresses, so those have to point at this file too or half the
            // images in an imported archive stay pointing at the old server.
            $context->mapMediaVariants($url, $media);

            $context->report->record('media', ImportReport::CREATED);
        }
    }

    /**
     * A file this library already holds under the same name.
     *
     * Media filenames here carry a random suffix, so the match is on the
     * original stem: "beach-holiday" finds "beach-holiday-Xy7Kp2Qa.jpg".
     */
    private function findByFilename(string $url): ?Media
    {
        $name = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
        $stem = Str::limit(Str::slug($name), 60, '');

        if ($stem === '') {
            return null;
        }

        return Media::where('file_name', 'like', $stem.'-%')->first();
    }

    // Content --------------------------------------------------------------------

    private function importContent(WxrReader $reader, ImportContext $context): void
    {
        $wanted = array_values(array_filter(
            ['post', 'page', 'product'],
            fn (string $type) => $context->options->wants(self::TYPES[$type])
        ));

        if ($wanted === [] || $context->report->stoppedEarly) {
            return;
        }

        foreach ($reader->items($wanted) as $item) {
            if ($context->outOfTime()) {
                $context->report->stoppedEarly = true;

                return;
            }

            if (in_array($item['status'], self::IGNORED_STATUSES, true)) {
                continue;
            }

            $key = self::TYPES[$item['type']];

            try {
                $outcome = match ($item['type']) {
                    'post' => $this->importPost($item, $context),
                    'page' => $this->importPage($item, $context),
                    'product' => $this->importProduct($item, $context),
                    default => ImportReport::SKIPPED,
                };

                $context->report->record($key, $outcome);
            } catch (\Throwable $e) {
                $context->report->fail($key, ($item['title'] ?: 'untitled').': '.$e->getMessage());
            }
        }
    }

    private function importPost(array $item, ImportContext $context): string
    {
        $slug = $this->slug($item);
        $existing = Post::where('slug', $slug)->first();

        if ($existing && ! $context->options->updatesExisting()) {
            $this->posts[$item['id']] = $existing->id;

            return ImportReport::SKIPPED;
        }

        $post = $existing ?: new Post(['slug' => $slug]);
        $status = $this->status($item, ['draft', 'published', 'scheduled'], $context);

        $post->fill([
            'title' => $item['title'] ?: $slug,
            'excerpt' => $this->formatter->toExcerpt($item['excerpt']),
            'content' => $this->body($item, $context),
            'featured_image' => $this->featuredImage($item, $context),
            'status' => $status,
            'published_at' => $status === 'draft' ? null : $this->date($item['date']),
            'allow_comments' => true,
            'category_id' => $this->postCategory($item),
        ]);

        $post->author_id = $this->author($item, $context);
        $post->created_at = $this->date($item['date']) ?? now();
        $post->save();

        $tags = $this->termSlugs($item, 'post_tag');

        if ($tags !== []) {
            $post->tags()->sync($this->tagIds($tags));
        }

        $this->posts[$item['id']] = $post->id;

        $this->importComments($item, $post, $context);

        return $existing ? ImportReport::UPDATED : ImportReport::CREATED;
    }

    private function importPage(array $item, ImportContext $context): string
    {
        $slug = $this->slug($item);
        $existing = Page::where('slug', $slug)->first();

        if ($existing && ! $context->options->updatesExisting()) {
            $this->pages[$item['id']] = $existing->id;

            return ImportReport::SKIPPED;
        }

        $page = $existing ?: new Page(['slug' => $slug]);

        $page->fill([
            'title' => $item['title'] ?: $slug,
            'content' => $this->body($item, $context),
            'featured_image' => $this->featuredImage($item, $context),
            'status' => $this->status($item, ['draft', 'published'], $context),
            'sort_order' => (int) $item['menu_order'],
        ]);

        $page->created_at = $this->date($item['date']) ?? now();
        $page->save();

        $this->pages[$item['id']] = $page->id;

        if ($item['parent'] > 0) {
            $this->parents[$item['id']] = $item['parent'];
        }

        return $existing ? ImportReport::UPDATED : ImportReport::CREATED;
    }

    /**
     * A WooCommerce product.
     *
     * Prices are the delicate part. WooCommerce stores them as decimal strings
     * in whatever currency that shop was in; this CMS stores integer minor
     * units. The conversion is arithmetic, not exchange - a shop that was in
     * euros and a site that is in rupees will import 19.99 as 1999, and that
     * is the site owner's to fix. Guessing a rate would be worse.
     */
    private function importProduct(array $item, ImportContext $context): string
    {
        $slug = $this->slug($item);
        $existing = Product::where('slug', $slug)->first();

        if ($existing && ! $context->options->updatesExisting()) {
            $this->products[$item['id']] = $existing->id;

            return ImportReport::SKIPPED;
        }

        $product = $existing ?: new Product(['slug' => $slug]);
        $meta = $item['meta'];

        $regular = $this->price($meta['_regular_price'] ?? $meta['_price'] ?? null);
        $sale = $this->price($meta['_sale_price'] ?? null);

        $downloadable = ($meta['_downloadable'] ?? 'no') === 'yes';
        $virtual = ($meta['_virtual'] ?? 'no') === 'yes';

        $product->fill([
            'name' => $item['title'] ?: $slug,
            'sku' => $this->sku($meta['_sku'] ?? null, $product),
            'short_description' => $this->formatter->toExcerpt($item['excerpt'], 400),
            'description' => $this->body($item, $context),
            'featured_image' => $this->featuredImage($item, $context),
            'price' => $regular ?? 0,
            'sale_price' => $sale,
            'type' => $downloadable ? 'digital' : 'simple',
            'manage_stock' => ($meta['_manage_stock'] ?? 'no') === 'yes',
            'stock' => (int) ($meta['_stock'] ?? 0),
            'allow_backorder' => ($meta['_backorders'] ?? 'no') !== 'no',
            'weight' => filled($meta['_weight'] ?? null) ? (float) $meta['_weight'] : null,
            'requires_shipping' => ! $virtual && ! $downloadable,
            // A downloadable product arrives with no file - WooCommerce keeps
            // those outside the export - so it is never published on import.
            'status' => $downloadable ? 'draft' : $this->status($item, ['draft', 'published'], $context),
            'is_featured' => ($meta['_featured'] ?? 'no') === 'yes',
            'sort_order' => (int) $item['menu_order'],
        ]);

        $product->created_at = $this->date($item['date']) ?? now();
        $product->save();

        if ($downloadable) {
            $context->report->note('products', '"'.$product->name.'" is a downloadable product. WooCommerce does not export the file, so it was imported as a draft.');
        }

        $categories = $this->termSlugs($item, 'product_cat');

        if ($categories !== []) {
            $product->categories()->sync($this->categoryIds($categories, $item));
        }

        $this->gallery($product, $meta['_product_image_gallery'] ?? null);

        $this->products[$item['id']] = $product->id;

        return $existing ? ImportReport::UPDATED : ImportReport::CREATED;
    }

    private function gallery(Product $product, ?string $ids): void
    {
        if (blank($ids)) {
            return;
        }

        $sync = [];
        $order = 0;

        foreach (explode(',', $ids) as $id) {
            $mediaId = $this->attachments[(int) trim($id)] ?? null;

            if ($mediaId) {
                $sync[$mediaId] = ['collection' => 'gallery', 'sort_order' => $order++];
            }
        }

        if ($sync !== []) {
            $product->gallery()->sync($sync);
        }
    }

    private function importComments(array $item, Post $post, ImportContext $context): void
    {
        if (! $context->options->wants('comments') || $item['comments'] === []) {
            return;
        }

        $map = [];

        foreach ($item['comments'] as $comment) {
            // Pingbacks and trackbacks are link notifications, not writing.
            if (in_array($comment['type'], ['pingback', 'trackback'], true) || blank($comment['content'])) {
                continue;
            }

            $body = trim($comment['content']);

            $existing = Comment::where('post_id', $post->id)
                ->where('body', $body)
                ->where('author_email', $comment['email'])
                ->first();

            if ($existing) {
                $map[$comment['id']] = $existing->id;
                $context->report->record('comments', ImportReport::SKIPPED);

                continue;
            }

            $created = Comment::create([
                'post_id' => $post->id,
                'parent_id' => $map[$comment['parent']] ?? null,
                'author_name' => $comment['author'] ?: null,
                'author_email' => $comment['email'],
                'body' => $body,
                'status' => match ($comment['approved']) {
                    '1' => 'approved',
                    'spam' => 'spam',
                    default => 'pending',
                },
            ]);

            if ($date = $this->date($comment['date'])) {
                $created->created_at = $date;
                $created->save();
            }

            $map[$comment['id']] = $created->id;
            $context->report->record('comments', ImportReport::CREATED);
        }
    }

    /** Restores the parent-child nesting WordPress keeps as post_parent. */
    private function linkPages(ImportContext $context): void
    {
        foreach ($this->parents as $childWpId => $parentWpId) {
            $childId = $this->pages[$childWpId] ?? null;
            $parentId = $this->pages[$parentWpId] ?? null;

            if (! $childId || ! $parentId || $childId === $parentId) {
                continue;
            }

            Page::whereKey($childId)->update(['parent_id' => $parentId]);
        }
    }

    // Menus ------------------------------------------------------------------------

    /**
     * Navigation menus.
     *
     * WordPress stores each menu entry as a post of type nav_menu_item whose
     * meta says what it points at, and the menu it belongs to is a term on
     * that post. So the pieces are gathered first and the tree is built
     * afterwards, once every entry's new id is known.
     */
    private function importMenus(WxrReader $reader, array $terms, ImportContext $context): void
    {
        if (! $context->options->wants('menus') || $context->report->stoppedEarly) {
            return;
        }

        $definitions = $terms['taxonomies']['nav_menu'] ?? [];
        $entries = [];

        foreach ($reader->items(['nav_menu_item']) as $item) {
            if (in_array($item['status'], self::IGNORED_STATUSES, true)) {
                continue;
            }

            $menuSlug = null;

            foreach ($item['terms'] as $term) {
                if ($term['domain'] === 'nav_menu') {
                    $menuSlug = $term['slug'];
                    $definitions[$menuSlug] ??= ['slug' => $menuSlug, 'name' => $term['name'] ?: $menuSlug];
                }
            }

            if ($menuSlug === null) {
                continue;
            }

            $entries[$menuSlug][] = $item;
        }

        foreach ($entries as $slug => $items) {
            $definition = $definitions[$slug] ?? ['slug' => $slug, 'name' => Str::headline($slug)];

            $existing = Menu::where('slug', $slug)->first();

            if ($existing && ! $context->options->updatesExisting()) {
                $context->report->record('menus', ImportReport::SKIPPED);

                continue;
            }

            $menu = $existing ?: new Menu(['slug' => $slug]);
            $menu->fill(['slug' => $slug, 'name' => $definition['name']])->save();

            if ($existing) {
                $menu->items()->delete();
            }

            $this->buildMenu($menu, $items, $context);

            $context->report->record('menus', $existing ? ImportReport::UPDATED : ImportReport::CREATED);
        }
    }

    private function buildMenu(Menu $menu, array $items, ImportContext $context): void
    {
        usort($items, fn ($a, $b) => $a['menu_order'] <=> $b['menu_order']);

        $created = [];

        // Two sweeps rather than recursion: a child may come before its parent
        // in the file, so every entry is created flat and then re-parented.
        foreach ($items as $item) {
            $meta = $item['meta'];

            [$type, $referenceId, $url] = $this->menuTarget($meta);

            $label = $item['title'] !== '' ? $item['title'] : ($meta['_menu_item_title'] ?? 'Link');

            $created[$item['id']] = $menu->items()->create([
                'label' => Str::limit($label, 190, ''),
                'type' => $type,
                'url' => $url,
                'reference_id' => $referenceId,
                'target' => ($meta['_menu_item_target'] ?? '') === '_blank' ? '_blank' : '_self',
                'sort_order' => (int) $item['menu_order'],
                'is_active' => true,
            ]);
        }

        foreach ($items as $item) {
            $parentWpId = (int) ($item['meta']['_menu_item_menu_item_parent'] ?? 0);

            if ($parentWpId === 0 || ! isset($created[$parentWpId], $created[$item['id']])) {
                continue;
            }

            $created[$item['id']]->update(['parent_id' => $created[$parentWpId]->id]);
        }
    }

    /**
     * What a menu entry points at on this site.
     *
     * @return array{0: string, 1: ?int, 2: ?string}
     */
    private function menuTarget(array $meta): array
    {
        $kind = $meta['_menu_item_type'] ?? 'custom';
        $object = $meta['_menu_item_object'] ?? '';
        $objectId = (int) ($meta['_menu_item_object_id'] ?? 0);

        if ($kind === 'post_type') {
            $id = match ($object) {
                'page' => $this->pages[$objectId] ?? null,
                'post' => $this->posts[$objectId] ?? null,
                'product' => $this->products[$objectId] ?? null,
                default => null,
            };

            if ($id) {
                return [$object === 'product' ? 'product' : $object, $id, null];
            }
        }

        // Taxonomy entries point at a term, and the id in the file is
        // WordPress's. The slug is the only thing both sites agree on, so the
        // category is looked up by the address the menu entry carried.
        if ($kind === 'taxonomy' && filled($meta['_menu_item_url'] ?? null)) {
            return ['custom', null, $meta['_menu_item_url']];
        }

        return ['custom', null, $meta['_menu_item_url'] ?? null];
    }

    // Field helpers --------------------------------------------------------------------

    private function body(array $item, ImportContext $context): string
    {
        $html = $this->formatter->toHtml($item['content']);

        // Addresses are swapped for this site's copies before the sanitiser
        // runs, so what is stored is already pointing at the right place.
        return $this->sanitizer->clean($context->rewrite($html));
    }

    private function slug(array $item): string
    {
        $slug = $item['slug'] !== '' ? rawurldecode($item['slug']) : '';

        // A post written in a non-Latin script exports with a percent-encoded
        // slug, and Str::slug() of that is an empty string - hence the title,
        // and the id as the last resort.
        return Str::slug($slug) ?: (Str::slug($item['title']) ?: 'wp-'.$item['id']);
    }

    /**
     * @param  string[]  $allowed  Statuses this model actually accepts.
     */
    private function status(array $item, array $allowed, ImportContext $context): string
    {
        if ($context->options->status) {
            return in_array($context->options->status, $allowed, true) ? $context->options->status : 'draft';
        }

        // A password-protected post is not published content: it becomes a
        // draft rather than quietly losing its protection.
        if (filled($item['password'])) {
            return 'draft';
        }

        $status = match ($item['status']) {
            'publish' => 'published',
            'future' => 'scheduled',
            default => 'draft',
        };

        return in_array($status, $allowed, true) ? $status : 'draft';
    }

    private function date(?string $value): ?Carbon
    {
        if (blank($value) || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function author(array $item, ImportContext $context): ?int
    {
        $login = $item['creator'] ?? '';

        return $context->author($this->authors[$login] ?? null);
    }

    private function featuredImage(array $item, ImportContext $context): ?string
    {
        $id = (int) ($item['meta']['_thumbnail_id'] ?? 0);
        $mediaId = $this->attachments[$id] ?? null;

        return $mediaId ? Media::find($mediaId)?->path : null;
    }

    /** @return string[] */
    private function termSlugs(array $item, string $domain): array
    {
        $slugs = [];

        foreach ($item['terms'] as $term) {
            if ($term['domain'] === $domain && $term['slug'] !== '') {
                $slugs[$term['slug']] = $term['name'] ?: $term['slug'];
            }
        }

        return $slugs;
    }

    /** WordPress allows several categories per post; this CMS files a post under one. */
    private function postCategory(array $item): ?int
    {
        $categories = $this->termSlugs($item, 'category');

        foreach ($categories as $slug => $name) {
            // "Uncategorized" is WordPress's placeholder, so anything else is
            // preferred - but it is still better than nothing.
            if ($slug === 'uncategorized' && count($categories) > 1) {
                continue;
            }

            return Category::firstOrCreate(
                ['type' => Category::TYPE_BLOG, 'slug' => $slug],
                ['name' => $name]
            )->id;
        }

        return null;
    }

    /** @return int[] */
    private function tagIds(array $slugs): array
    {
        $ids = [];

        foreach ($slugs as $slug => $name) {
            $ids[] = Tag::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
        }

        return $ids;
    }

    /** @return int[] */
    private function categoryIds(array $slugs, array $item): array
    {
        $ids = [];

        foreach ($slugs as $slug => $name) {
            $ids[] = Category::firstOrCreate(
                ['type' => Category::TYPE_SHOP, 'slug' => $slug],
                ['name' => $name]
            )->id;
        }

        return $ids;
    }

    /** A WooCommerce decimal string as the integer minor units this shop stores. */
    private function price(?string $value): ?int
    {
        if (blank($value) || ! is_numeric(trim($value))) {
            return null;
        }

        return to_minor_units(trim($value));
    }

    private function sku(?string $sku, Product $product): ?string
    {
        $sku = trim((string) $sku);

        if ($sku === '') {
            return null;
        }

        $taken = Product::where('sku', $sku)
            ->when($product->exists, fn ($query) => $query->whereKeyNot($product->getKey()))
            ->withTrashed()
            ->exists();

        return $taken ? null : Str::limit($sku, 80, '');
    }
}
