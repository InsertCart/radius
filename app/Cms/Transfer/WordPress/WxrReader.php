<?php

namespace App\Cms\Transfer\WordPress;

use App\Cms\Transfer\TransferException;
use SimpleXMLElement;
use XMLReader;

/**
 * Reads a WordPress eXtended RSS file.
 *
 * WXR is RSS with a WordPress namespace bolted on, and a ten-year-old blog
 * exports to a file of a few hundred megabytes. So it is read with XMLReader
 * and only one <item> is turned into an object at a time: simplexml_load_file()
 * on the same file would need the whole document in memory several times over,
 * and on the shared hosting this CMS is usually installed on that is simply
 * not available.
 *
 * The file is walked more than once - terms, then attachments, then posts -
 * because a post refers to its featured image by an id that may appear later
 * in the file. Reading from disk three times is cheap; holding an index of
 * everything in memory is not.
 *
 * Nothing in the file is trusted. A document type declaration is refused
 * outright rather than parsed, because that is the door every XML entity
 * attack walks through, and the parser is told never to fetch anything over
 * the network.
 */
class WxrReader
{
    /** Namespace prefixes found in the document: prefix => URI. */
    private array $namespaces = [];

    private array $site = [];

    public function __construct(private string $path) {}

    /**
     * Checks this is a WXR file before anything reads it in earnest.
     *
     * @throws TransferException
     */
    public function validate(): void
    {
        if (! is_file($this->path) || filesize($this->path) === 0) {
            throw new TransferException('That file could not be read.');
        }

        $head = (string) file_get_contents($this->path, false, null, 0, 65536);

        if (stripos($head, '<!DOCTYPE') !== false) {
            throw new TransferException('That XML file carries a document type declaration, which this importer will not parse. Re-export it from WordPress.');
        }

        if (stripos($head, '<rss') === false || stripos($head, 'wordpress.org/export') === false) {
            throw new TransferException('That does not look like a WordPress export. Use Tools > Export in WordPress and upload the .xml file it gives you.');
        }

        preg_match_all('/xmlns:([a-zA-Z0-9_-]+)\s*=\s*"([^"]+)"/', $head, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $this->namespaces[$match[1]] = $match[2];
        }

        // Older exports declare 1.0 or 1.1. The element names this reader uses
        // are the same in all three, so the URI is taken from the file rather
        // than assumed, and only defaulted when the file has not said.
        $this->namespaces['wp'] ??= 'http://wordpress.org/export/1.2/';
        $this->namespaces['content'] ??= 'http://purl.org/rss/1.0/modules/content/';
        $this->namespaces['excerpt'] ??= 'http://wordpress.org/export/1.2/excerpt/';
        $this->namespaces['dc'] ??= 'http://purl.org/dc/elements/1.1/';
    }

    /** @return array{title: ?string, url: ?string} */
    public function site(): array
    {
        if ($this->site !== []) {
            return $this->site;
        }

        $reader = $this->open();
        $title = null;
        $url = null;

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            // Everything wanted here sits before the first item, so there is
            // no reason to read the other 400 MB.
            if ($reader->name === 'item') {
                break;
            }

            if ($reader->name === 'title' && $title === null) {
                $title = $reader->readString();
            }

            if ($reader->name === 'wp:base_blog_url' || ($reader->name === 'link' && $url === null)) {
                $url = trim((string) $reader->readString()) ?: $url;
            }
        }

        $reader->close();

        return $this->site = ['title' => $title, 'url' => $url ? rtrim($url, '/') : null];
    }

    /**
     * Everybody who wrote something, keyed by their WordPress login.
     *
     * @return array<string, array{login: string, email: ?string, name: ?string}>
     */
    public function authors(): array
    {
        $authors = [];

        foreach ($this->elements(['wp:author']) as $node) {
            $wp = $node->children($this->namespaces['wp']);
            $login = trim((string) $wp->author_login);

            if ($login === '') {
                continue;
            }

            $authors[$login] = [
                'login' => $login,
                'email' => trim((string) $wp->author_email) ?: null,
                'name' => trim((string) $wp->author_display_name) ?: $login,
            ];
        }

        return $authors;
    }

    /**
     * Categories, tags and any other taxonomy the export carries.
     *
     * @return array{categories: array, tags: array, taxonomies: array}
     */
    public function terms(): array
    {
        $categories = [];
        $tags = [];
        $taxonomies = [];

        foreach ($this->elements(['wp:category', 'wp:tag', 'wp:term']) as $name => $node) {
            $wp = $node->children($this->namespaces['wp']);

            if ($name === 'wp:category') {
                $slug = trim((string) $wp->category_nicename);

                if ($slug !== '') {
                    $categories[$slug] = [
                        'slug' => $slug,
                        'name' => trim((string) $wp->cat_name) ?: $slug,
                        'parent' => trim((string) $wp->category_parent) ?: null,
                        'description' => trim((string) $wp->category_description) ?: null,
                        'term_id' => (int) $wp->term_id,
                    ];
                }

                continue;
            }

            if ($name === 'wp:tag') {
                $slug = trim((string) $wp->tag_slug);

                if ($slug !== '') {
                    $tags[$slug] = [
                        'slug' => $slug,
                        'name' => trim((string) $wp->tag_name) ?: $slug,
                        'description' => trim((string) $wp->tag_description) ?: null,
                        'term_id' => (int) $wp->term_id,
                    ];
                }

                continue;
            }

            // wp:term is the generic one: product categories, menus, and
            // whatever else a plugin registered.
            $taxonomy = trim((string) $wp->term_taxonomy);
            $slug = trim((string) $wp->term_slug);

            if ($taxonomy === '' || $slug === '') {
                continue;
            }

            $taxonomies[$taxonomy][$slug] = [
                'slug' => $slug,
                'name' => trim((string) $wp->term_name) ?: $slug,
                'parent' => trim((string) $wp->term_parent) ?: null,
                'description' => trim((string) $wp->term_description) ?: null,
                'term_id' => (int) $wp->term_id,
            ];
        }

        return ['categories' => $categories, 'tags' => $tags, 'taxonomies' => $taxonomies];
    }

    /** @return array<string, int> post type => how many items of it the file holds */
    public function counts(): array
    {
        $counts = [];

        foreach ($this->items() as $item) {
            $type = $item['type'] ?: 'unknown';
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }

    /**
     * Every <item>, normalised into a plain array.
     *
     * @param  string[]|null  $types  Post types to yield; null means all of them.
     * @return \Generator<array>
     */
    public function items(?array $types = null): \Generator
    {
        foreach ($this->elements(['item']) as $node) {
            $item = $this->normalise($node);

            if ($types === null || in_array($item['type'], $types, true)) {
                yield $item;
            }
        }
    }

    // Internals -------------------------------------------------------------

    private function normalise(SimpleXMLElement $node): array
    {
        $wp = $node->children($this->namespaces['wp']);
        $content = $node->children($this->namespaces['content']);
        $excerpt = $node->children($this->namespaces['excerpt']);
        $dc = $node->children($this->namespaces['dc']);

        $terms = [];

        foreach ($node->category as $category) {
            $domain = (string) ($category['domain'] ?? '');
            $slug = (string) ($category['nicename'] ?? '');

            if ($domain === '') {
                continue;
            }

            $terms[] = [
                'domain' => $domain,
                'slug' => $slug !== '' ? $slug : str((string) $category)->slug()->value(),
                'name' => trim((string) $category),
            ];
        }

        $meta = [];

        foreach ($wp->postmeta as $postmeta) {
            $key = trim((string) $postmeta->children($this->namespaces['wp'])->meta_key);

            if ($key !== '') {
                $meta[$key] = (string) $postmeta->children($this->namespaces['wp'])->meta_value;
            }
        }

        $comments = [];

        foreach ($wp->comment as $comment) {
            $c = $comment->children($this->namespaces['wp']);

            $comments[] = [
                'id' => (int) $c->comment_id,
                'parent' => (int) $c->comment_parent,
                'author' => trim((string) $c->comment_author),
                'email' => trim((string) $c->comment_author_email) ?: null,
                'date' => trim((string) $c->comment_date) ?: null,
                'content' => (string) $c->comment_content,
                'approved' => trim((string) $c->comment_approved),
                'type' => trim((string) $c->comment_type),
            ];
        }

        return [
            'id' => (int) $wp->post_id,
            'type' => trim((string) $wp->post_type),
            'status' => trim((string) $wp->status),
            'title' => trim((string) $node->title),
            'slug' => trim((string) $wp->post_name),
            'link' => trim((string) $node->link),
            'date' => trim((string) $wp->post_date) ?: trim((string) $wp->post_date_gmt),
            'content' => (string) $content->encoded,
            'excerpt' => (string) $excerpt->encoded,
            'creator' => trim((string) $dc->creator),
            'parent' => (int) $wp->post_parent,
            'menu_order' => (int) $wp->menu_order,
            'password' => trim((string) $wp->post_password),
            'attachment_url' => trim((string) $wp->attachment_url) ?: null,
            'terms' => $terms,
            'meta' => $meta,
            'comments' => $comments,
        ];
    }

    /**
     * Walks the document yielding the named elements, one at a time.
     *
     * @param  string[]  $names
     * @return \Generator<string, SimpleXMLElement>
     */
    private function elements(array $names): \Generator
    {
        $reader = $this->open();

        // Expanding into a document of our own rather than the reader's, which
        // is what lets SimpleXML take ownership of the subtree.
        $document = new \DOMDocument;

        try {
            if (! $reader->read()) {
                return;
            }

            // read() and next() are not interchangeable here. next() already
            // leaves the cursor on the following node, so calling read() after
            // it would step over that node without ever looking at it - which
            // is how a naive loop silently imports every other post.
            while ($reader->nodeType !== XMLReader::NONE) {
                if ($reader->nodeType === XMLReader::ELEMENT && in_array($reader->name, $names, true)) {
                    $name = $reader->name;
                    $node = $reader->expand($document);

                    if ($node !== false) {
                        $element = simplexml_import_dom($node);

                        if ($element instanceof SimpleXMLElement) {
                            yield $name => $element;
                        }
                    }

                    if (! $reader->next()) {
                        break;
                    }

                    continue;
                }

                if (! $reader->read()) {
                    break;
                }
            }
        } finally {
            $reader->close();
        }
    }

    /** @throws TransferException */
    private function open(): XMLReader
    {
        if ($this->namespaces === []) {
            $this->validate();
        }

        $reader = new XMLReader;

        // LIBXML_NONET: the parser must never make a network request, whatever
        // the document asks for.
        if (! $reader->open($this->path, 'UTF-8', LIBXML_NONET)) {
            throw new TransferException('That XML file could not be opened.');
        }

        // Errors are collected rather than printed. A WordPress export with
        // one stray ampersand in a 2011 post should not put a warning in the
        // middle of an admin page.
        libxml_use_internal_errors(true);

        return $reader;
    }
}
