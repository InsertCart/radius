<?php

namespace App\Cms\Embeds;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Turns a link an author pasted on its own line into the thing it points at.
 *
 * Writing a post should not mean knowing what an iframe is. Paste a YouTube
 * address into the editor, leave it on a line of its own, and the visitor gets
 * the player; the same goes for an Instagram post, a tweet, a Spotify album and
 * everything else EmbedRegistry recognises.
 *
 * The swap happens at render time, not on save. What is stored stays the link
 * the author typed, which matters more than it sounds:
 *
 *  - the editor keeps showing a link, so the post can be edited by anyone,
 *    not only by someone comfortable reading embed markup;
 *  - a provider that changes its embed URL is fixed by updating the registry,
 *    without rewriting every post ever published;
 *  - turning embeds off, or turning one provider off, takes effect everywhere
 *    at once and leaves a plain link behind rather than a broken frame.
 *
 * A link with words around it is left completely alone. "Standing on its own"
 * is the signal, exactly as it is in WordPress, because it is the only way to
 * tell "here is a video" from "here is where I read that".
 */
class EmbedManager
{
    /** Wrapping tags that can hold nothing but a link. */
    private const CANDIDATES = ['p', 'div', 'figure'];

    /** Scripts already emitted this request, so widgets.js is not sent twice. */
    private array $scripts = [];

    public function __construct(private EmbedRegistry $registry) {}

    public function enabled(): bool
    {
        return (bool) setting('embeds_enabled', true);
    }

    /**
     * Whether the privacy-preserving host is used where a provider offers one.
     * On by default: a CMS sold to many owners should not opt their visitors
     * into tracking without being asked.
     */
    public function privacy(): bool
    {
        return (bool) setting('embeds_privacy', true);
    }

    /**
     * Providers an owner has turned on, or null for all of them.
     *
     * An empty list means "no restriction" rather than "none", so a site that
     * has never visited the settings screen embeds everything.
     *
     * @return string[]|null
     */
    public function allowed(): ?array
    {
        $chosen = setting('embeds_providers', []);

        if (! is_array($chosen) || $chosen === []) {
            return null;
        }

        return array_values(array_intersect($chosen, array_keys(EmbedRegistry::providers())));
    }

    /** What a single link resolves to, honouring the site's settings. */
    public function resolve(string $url): ?Embed
    {
        if (! $this->enabled()) {
            return null;
        }

        return $this->registry->resolve($url, $this->allowed(), $this->privacy());
    }

    /** Whether a link would become an embed. Used by the editor's preview. */
    public function recognises(string $url): bool
    {
        return $this->resolve($url) !== null;
    }

    /**
     * Editor content with every standalone link swapped for its embed.
     *
     * Safe to call on anything: content with no links in it is handed straight
     * back without being parsed, and a link nothing recognises is left as it
     * was found.
     */
    public function rewrite(?string $html): string
    {
        $html = (string) $html;

        if (! $this->enabled() || ! str_contains($html, 'http')) {
            return $html;
        }

        $document = new DOMDocument;

        // Content is stored as UTF-8; without the hint DOMDocument assumes
        // ISO-8859-1 and mangles anything outside ASCII.
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="cms-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $document->getElementById('cms-root');

        if (! $root) {
            return $html;
        }

        // The generated markup is put in as a marker and swapped back in at the
        // end. Building it as DOM nodes instead would mean hand-assembling
        // every element, and injecting it as a fragment would mean the markup
        // had to be valid XML - an ampersand in a query string is enough to
        // break that.
        $embeds = [];

        // The marker carries a random token, so a comment that happens to be
        // in the content cannot be mistaken for one of ours.
        $token = bin2hex(random_bytes(6));

        $xpath = new DOMXPath($document);
        $query = implode(' | ', array_map(fn (string $tag) => '//'.$tag, self::CANDIDATES));

        // Document order, so a wrapper is considered before what it wraps: an
        // imported <figure><div>link</div></figure> is replaced whole rather
        // than leaving the wrapper behind.
        foreach (iterator_to_array($xpath->query($query)) as $node) {
            /** @var DOMElement $node */

            // The wrapper this content was parsed inside is a <div> like any
            // other as far as XPath is concerned; replacing it would throw the
            // document away. Content that is nothing but a link is handled
            // below instead.
            if ($node === $root || ! $node->parentNode) {
                continue;
            }

            $url = $this->standaloneUrl($node);

            if ($url === null || ! $embed = $this->resolve($url)) {
                continue;
            }

            $embeds[] = $this->html($embed);
            $node->parentNode->replaceChild(
                $document->createComment('radius-embed-'.$token.'-'.(count($embeds) - 1)),
                $node
            );
        }

        // Content that is nothing but a link, with no wrapper around it at all.
        // Only when there is no markup whatsoever: a wrapper that was looked at
        // and passed over above has already had its answer.
        if ($embeds === []) {
            if ($this->isBare($root) && $url = $this->standaloneUrl($root)) {
                if ($embed = $this->resolve($url)) {
                    return $this->html($embed);
                }
            }

            return $html;
        }

        $result = '';

        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        foreach ($embeds as $index => $markup) {
            $result = str_replace('<!--radius-embed-'.$token.'-'.$index.'-->', $markup, $result);
        }

        return trim($result);
    }

    /**
     * The one link an element holds, or null if it holds anything else.
     *
     * A paragraph counts when its text is a single address and it contains no
     * markup beyond the link itself - the editor autolinks what was pasted, so
     * both the bare text and the anchor form arrive here.
     */
    private function standaloneUrl(DOMNode $node): ?string
    {
        $text = trim((string) $node->textContent);

        if ($text === '' || ! preg_match('#^https?://\S+$#i', $text)) {
            return null;
        }

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                return null;
            }

            $tag = strtolower($child->nodeName);

            // A <br> is the blank line around the link. A nested wrapper is
            // handled on its own pass. Anything else - a bold run, an image -
            // means this is not just a link.
            if (! in_array($tag, ['br', 'a', ...self::CANDIDATES], true)) {
                return null;
            }

            // An anchor pointing somewhere other than its own words is a real
            // link an author wrote deliberately, not a pasted address.
            if ($tag === 'a' && trim($child->getAttribute('href')) !== $text) {
                return null;
            }
        }

        return $text;
    }

    /** Text and line breaks only: no element that could carry a meaning. */
    private function isBare(DOMNode $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== XML_TEXT_NODE && strtolower($child->nodeName) !== 'br') {
                return false;
            }
        }

        return true;
    }

    // Markup ---------------------------------------------------------------

    /** The finished embed, ready to drop into a page. */
    public function html(Embed $embed): string
    {
        $style = $embed->maxWidth > 0 ? ' style="max-width:'.$embed->maxWidth.'px"' : '';

        $attributes = ' class="cms-embed cms-embed--'.e($embed->provider).' cms-embed--'.e($embed->kind).'"'
            .' data-embed="'.e($embed->provider).'"'.$style;

        return '<figure'.$attributes.'>'.$this->inner($embed).'</figure>';
    }

    private function inner(Embed $embed): string
    {
        return match ($embed->kind) {
            Embed::MEDIA => $this->media($embed),
            Embed::SCRIPT => $this->script($embed),
            Embed::CARD => $this->frame($embed, ' style="height:'.max(120, $embed->height).'px"'),
            default => '<div class="cms-embed__ratio cms-embed__ratio--'.e($embed->ratio).'">'
                .$this->frame($embed).'</div>',
        };
    }

    private function frame(Embed $embed, string $extra = ''): string
    {
        // Lazily loaded, so a post with several embeds does not pull megabytes
        // of somebody else's player before a visitor has scrolled to it.
        return '<iframe src="'.e((string) $embed->src).'"'
            .' title="'.e((string) ($embed->title ?? $embed->label)).'"'
            .' loading="lazy" frameborder="0" referrerpolicy="strict-origin-when-cross-origin"'
            .' allow="'.EmbedRegistry::ALLOW.'" allowfullscreen'.$extra.'></iframe>';
    }

    private function media(Embed $embed): string
    {
        return '<'.$embed->tag.' src="'.e((string) $embed->src).'" controls preload="metadata"></'.$embed->tag.'>';
    }

    /**
     * Markup a provider's own script turns into an embed.
     *
     * The script is emitted beside the first embed that needs it rather than in
     * the theme's head, so a site whose posts contain no tweets never loads
     * X's widget code - and no theme has to know about any of this.
     */
    private function script(Embed $embed): string
    {
        $markup = (string) $embed->markup;

        if ($embed->script && ! in_array($embed->script, $this->scripts, true)) {
            $this->scripts[] = $embed->script;
            $markup .= '<script async defer src="'.e($embed->script).'" charset="utf-8"></script>';
        }

        return $markup;
    }
}
