<?php

namespace App\Cms\Transfer\WordPress;

/**
 * Turns what WordPress stores into what a theme can render.
 *
 * WordPress does not store finished HTML. A classic-editor post is stored with
 * bare line breaks and turned into paragraphs at render time by wpautop(), and
 * a block-editor post is stored as HTML wrapped in comment markers that its own
 * renderer strips. Imported verbatim, the first arrives as one enormous
 * paragraph and the second as HTML littered with block comments.
 *
 * So both are dealt with here, on the way in, once - rather than asking every
 * theme to carry a WordPress compatibility layer forever.
 *
 * Shortcodes are the part that cannot be solved honestly. [contact-form-7 id=…]
 * means nothing outside WordPress and no amount of cleverness will make it mean
 * something, so the default is to drop the marker and keep any words inside it.
 */
class ContentFormatter
{
    /** Tags wpautop treats as blocks: never wrapped in a paragraph of their own. */
    private const BLOCKS = 'table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary';

    public function toHtml(?string $raw, bool $stripShortcodes = true): string
    {
        $content = (string) $raw;

        if (trim($content) === '') {
            return '';
        }

        $isBlocks = str_contains($content, '<!-- wp:');

        $content = $this->stripBlockMarkers($content);

        if ($stripShortcodes) {
            $content = $this->stripShortcodes($content);
        }

        // Block editor content is already marked up as HTML; running it
        // through wpautop would wrap the whitespace between blocks in empty
        // paragraphs.
        if (! $isBlocks) {
            $content = $this->autoParagraph($content);
        }

        return trim($content);
    }

    /** The plain-text summary WordPress keeps separately. */
    public function toExcerpt(?string $raw, int $limit = 500): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($this->stripShortcodes($this->stripBlockMarkers((string) $raw)))));

        return $text === '' ? null : mb_substr($text, 0, $limit);
    }

    /**
     * Removes the <!-- wp:paragraph --> markers and keeps what is between them.
     *
     * Self-closing block comments - <!-- wp:spacer /--> and friends - carry no
     * content at all and simply go.
     */
    private function stripBlockMarkers(string $content): string
    {
        return (string) preg_replace('/<!--\s*\/?wp:.*?-->/s', '', $content);
    }

    /**
     * Drops shortcode markers, keeping whatever they wrapped.
     *
     * [caption]<img>A cat[/caption] becomes the image and the words. A
     * self-closing [gallery ids="1,2"] has nothing to keep and disappears.
     */
    private function stripShortcodes(string $content): string
    {
        // Enclosing shortcodes first, so the closing tag of a pair is never
        // mistaken for a standalone one.
        $content = (string) preg_replace('/\[([a-zA-Z0-9_-]+)(?:[^\]]*)?\](.*?)\[\/\1\]/s', '$2', $content);

        return (string) preg_replace('/\[\/?[a-zA-Z0-9_-]+(?:[^\]]*)?\]/', '', $content);
    }

    /**
     * A port of WordPress's wpautop(): blank lines become paragraphs, single
     * newlines become line breaks, and block-level tags are left alone.
     */
    private function autoParagraph(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/\n\n+/', "\n\n", $content);

        // Give every block tag its own blank line so the split below treats it
        // as a block rather than part of the paragraph beside it.
        $content = preg_replace('!(<(?:'.self::BLOCKS.')(?:\s[^>]*)?>)!i', "\n\n$1", (string) $content);
        $content = preg_replace('!(</(?:'.self::BLOCKS.')>)!i', "$1\n\n", (string) $content);

        // <pre> is the one place where the original whitespace is the content,
        // so it is pulled out and put back untouched.
        $pres = [];
        $content = preg_replace_callback('!<pre(\s[^>]*)?>.*?</pre>!is', function (array $match) use (&$pres) {
            $pres[] = $match[0];

            return '<!--radius-pre-'.(count($pres) - 1).'-->';
        }, (string) $content);

        $output = '';

        foreach (preg_split('/\n\s*\n/', (string) $content, -1, PREG_SPLIT_NO_EMPTY) as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '') {
                continue;
            }

            // A chunk that is already a block element stands as it is.
            $output .= preg_match('!^</?(?:'.self::BLOCKS.')(?:\s|>|/>)!i', $chunk)
                ? $chunk."\n"
                : '<p>'.str_replace("\n", "<br />\n", $chunk)."</p>\n";
        }

        foreach ($pres as $index => $pre) {
            $output = str_replace('<!--radius-pre-'.$index.'-->', $pre, $output);
        }

        // A paragraph holding nothing but a block that slipped through.
        return (string) preg_replace('!<p>\s*(</?(?:'.self::BLOCKS.')(?:\s[^>]*)?/?>)\s*</p>!i', '$1', $output);
    }

    /**
     * Every image address in a body, so they can be fetched and re-pointed.
     *
     * @return string[]
     */
    public function imageUrls(string $content): array
    {
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches);

        return array_values(array_unique(array_filter($matches[1] ?? [])));
    }
}
