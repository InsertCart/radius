<?php

namespace App\Cms\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Cleans HTML coming from the rich text editor before it is stored.
 *
 * Editor content is rendered unescaped, and an editor is not necessarily an
 * administrator, so the browser cannot be the only thing standing between a
 * pasted payload and every visitor's session. Everything is checked again here.
 *
 * The approach is an allowlist: parse the markup, walk it, and keep only the
 * tags and attributes that are named below. Anything unrecognised is unwrapped
 * rather than deleted, so a stray <span> loses the tag but keeps its words.
 */
class HtmlSanitizer
{
    /** Tags that may appear, and which attributes each may carry. */
    private const ALLOWED = [
        'p' => ['class', 'style'],
        'br' => [],
        'hr' => [],
        'strong' => [], 'b' => [],
        'em' => [], 'i' => [],
        'u' => [], 's' => [], 'del' => [], 'ins' => [],
        'sub' => [], 'sup' => [],
        'mark' => [],
        'h1' => ['class', 'style'], 'h2' => ['class', 'style'], 'h3' => ['class', 'style'],
        'h4' => ['class', 'style'], 'h5' => ['class', 'style'], 'h6' => ['class', 'style'],
        'ul' => ['class'], 'ol' => ['class', 'start'], 'li' => ['class'],
        'blockquote' => ['class', 'cite'],
        'pre' => ['class'], 'code' => ['class'],
        'a' => ['href', 'title', 'target', 'rel', 'class'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'class', 'loading', 'style'],
        'figure' => ['class'], 'figcaption' => ['class'],
        'table' => ['class'], 'thead' => [], 'tbody' => [], 'tfoot' => [],
        'tr' => [], 'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
        'div' => ['class', 'style'],
        'span' => ['class', 'style'],
        'iframe' => ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'loading', 'frameborder'],
    ];

    /**
     * CSS properties an author may set inline.
     *
     * Deliberately narrow: these cover alignment and simple emphasis, which is
     * what the toolbar produces. Anything else - position, behaviour, urls -
     * is dropped, because inline CSS is a classic way to smuggle behaviour or
     * to cover the page with an invisible overlay.
     */
    private const ALLOWED_STYLES = [
        'text-align', 'font-weight', 'font-style', 'text-decoration',
        'color', 'background-color', 'width', 'height', 'margin', 'margin-left',
        'margin-right', 'float', 'max-width',
    ];

    /** URL schemes a link or image may use. */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** Hosts an iframe may embed from, so an embed cannot be pointed anywhere. */
    private const ALLOWED_FRAME_HOSTS = [
        'youtube.com', 'www.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com',
        'player.vimeo.com', 'www.google.com', 'maps.google.com',
        'www.openstreetmap.org', 'open.spotify.com', 'w.soundcloud.com',
    ];

    public function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument;

        // Content is stored as UTF-8; without the meta hint DOMDocument
        // assumes ISO-8859-1 and mangles anything non-ASCII.
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="cms-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $document->getElementById('cms-root');

        if (! $root) {
            return '';
        }

        // Comments can hide markup from a casual read of the source and serve
        // no purpose in stored content.
        $xpath = new DOMXPath($document);

        foreach (iterator_to_array($xpath->query('//comment()')) as $comment) {
            $comment->parentNode?->removeChild($comment);
        }

        $this->cleanChildren($root);

        $result = '';

        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    private function cleanChildren(DOMNode $node): void
    {
        // Snapshotted, because the loop reparents and removes as it goes.
        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->cleanNode($child);
        }
    }

    private function cleanNode(DOMNode $node): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return;
        }

        if (! $node instanceof DOMElement) {
            $node->parentNode?->removeChild($node);

            return;
        }

        $tag = strtolower($node->nodeName);

        // Script and style carry no content worth keeping, so they go whole
        // rather than being unwrapped into loose text.
        if (in_array($tag, ['script', 'style', 'iframe'], true) && ! $this->isAllowedFrame($node, $tag)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if (! array_key_exists($tag, self::ALLOWED)) {
            $this->cleanChildren($node);
            $this->unwrap($node);

            return;
        }

        $this->cleanAttributes($node, $tag);
        $this->cleanChildren($node);
    }

    /** Replace an element with its children, keeping the words. */
    private function unwrap(DOMElement $node): void
    {
        $parent = $node->parentNode;

        if (! $parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    private function cleanAttributes(DOMElement $node, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];

        foreach (iterator_to_array($node->attributes) as $attribute) {
            /** @var DOMAttr $attribute */
            $name = strtolower($attribute->nodeName);

            // Every on* handler, whether or not the tag is otherwise allowed.
            if (! in_array($name, $allowed, true) || str_starts_with($name, 'on')) {
                $node->removeAttribute($attribute->nodeName);

                continue;
            }

            if (in_array($name, ['href', 'src'], true)) {
                $url = $this->cleanUrl($attribute->nodeValue, $tag);

                if ($url === '') {
                    $node->removeAttribute($attribute->nodeName);
                } else {
                    $node->setAttribute($attribute->nodeName, $url);
                }

                continue;
            }

            if ($name === 'style') {
                $style = $this->cleanStyle($attribute->nodeValue);

                if ($style === '') {
                    $node->removeAttribute('style');
                } else {
                    $node->setAttribute('style', $style);
                }

                continue;
            }

            if ($name === 'class') {
                $node->setAttribute('class', $this->cleanClasses($attribute->nodeValue));
            }
        }

        // A link opening in a new tab must not hand the opener window over.
        if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
            $rel = trim($node->getAttribute('rel').' noopener noreferrer');
            $node->setAttribute('rel', implode(' ', array_unique(explode(' ', $rel))));
        }

        if ($tag === 'img' && ! $node->hasAttribute('loading')) {
            $node->setAttribute('loading', 'lazy');
        }
    }

    /**
     * Only navigable schemes survive. Escaping is not enough on its own:
     * `javascript:alert(1)` contains nothing HTML-special and would pass
     * straight through an escaper intact.
     */
    private function cleanUrl(?string $url, string $tag): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        // Whitespace and control characters are how a scheme gets split up to
        // slip past a naive prefix check ("java\tscript:").
        $normalised = strtolower(preg_replace('/[\s\x00-\x1F\x7F]/', '', $url));

        // Inline images from the editor's own paste handling are fine.
        if ($tag === 'img' && preg_match('#^data:image/(png|jpe?g|gif|webp|avif);base64,#i', $normalised)) {
            return $url;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/', $normalised, $matches)) {
            if (! in_array($matches[1], self::ALLOWED_SCHEMES, true)) {
                return '';
            }
        }

        return $url;
    }

    private function cleanStyle(?string $style): string
    {
        $declarations = [];

        foreach (explode(';', (string) $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);

            if (! in_array($property, self::ALLOWED_STYLES, true)) {
                continue;
            }

            // url() and expression() are the two ways a style rule reaches
            // outside itself; neither is needed for alignment or colour.
            if (preg_match('/url\s*\(|expression\s*\(|javascript:|@import/i', $value)) {
                continue;
            }

            $value = str_replace(['{', '}', '<', '>', '"', "'"], '', $value);

            if ($value !== '') {
                $declarations[] = $property.': '.$value;
            }
        }

        return implode('; ', $declarations);
    }

    private function cleanClasses(?string $classes): string
    {
        $clean = [];

        foreach (preg_split('/\s+/', (string) $classes) as $class) {
            $class = preg_replace('/[^A-Za-z0-9\-_]/', '', $class);

            if ($class !== '') {
                $clean[] = $class;
            }
        }

        return implode(' ', array_slice($clean, 0, 12));
    }

    /** An iframe survives only when it points at a known embed host. */
    private function isAllowedFrame(DOMElement $node, string $tag): bool
    {
        if ($tag !== 'iframe') {
            return false;
        }

        $src = $this->cleanUrl($node->getAttribute('src'), 'iframe');

        if ($src === '') {
            return false;
        }

        $host = strtolower((string) parse_url($src, PHP_URL_HOST));

        return in_array($host, self::ALLOWED_FRAME_HOSTS, true);
    }

    /** Plain text, for excerpts and meta descriptions. */
    public function toText(?string $html, int $limit = 0): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));
        $text = preg_replace('/\s+/', ' ', $text);

        return $limit > 0 ? \Illuminate\Support\Str::limit($text, $limit) : $text;
    }
}
