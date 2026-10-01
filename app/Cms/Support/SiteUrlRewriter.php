<?php

namespace App\Cms\Support;

use InvalidArgumentException;

/**
 * Swaps one site address for another inside stored content.
 *
 * What a move from staging to live needs, and what a plain search-and-replace
 * gets wrong. Three things in particular:
 *
 * - The old address is matched whatever scheme it was saved with. A post
 *   written before the certificate went on says "http://", a newer one says
 *   "https://", and a theme may have written "//". All of them move.
 * - It stops at a boundary. Replacing "https://baztro.com" must not touch
 *   "https://baztro.com.au", nor "https://baztro.community".
 * - JSON is walked, not searched. A builder layout stores "https:\/\/..." with
 *   its slashes escaped, which a text replace never matches; decoding it,
 *   changing the strings and encoding it again does.
 */
class SiteUrlRewriter
{
    private string $pattern;

    private string $to;

    public function __construct(string $from, string $to)
    {
        $from = self::normalise($from);
        $to = self::normalise($to);

        if ($from === null || $to === null) {
            throw new InvalidArgumentException('Both addresses must be full URLs, such as https://www.example.com.');
        }

        // Host and any subfolder, without the scheme: the scheme is what this
        // class deliberately does not care about on the way in.
        $bare = preg_replace('#^https?://#i', '', $from);

        // Scheme-relative "//host" only where it starts an address, not inside
        // "https://other.com//host". And a trailing full stop ends a sentence,
        // while ".au" or ":8080" makes it a different site.
        $this->pattern = '#(?:https?:|(?<![\w:/]))//'.preg_quote($bare, '#').'(?![\w\-]|\.\w|:\d)#i';
        $this->to = $to;
    }

    /**
     * "https://Example.com/blog/" becomes "https://example.com/blog". Anything
     * that is not an http(s) address with a host gives null.
     */
    public static function normalise(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || blank($parts['host'] ?? null)) {
            return null;
        }

        return strtolower($parts['scheme']).'://'.strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .rtrim($parts['path'] ?? '', '/');
    }

    /** Whether two addresses name the same site, scheme aside. */
    public static function sameSite(?string $a, ?string $b): bool
    {
        $a = self::normalise($a);
        $b = self::normalise($b);

        return $a !== null && $b !== null
            && preg_replace('#^https?://#', '', $a) === preg_replace('#^https?://#', '', $b);
    }

    /** @param  int  $count  Incremented by the number of addresses swapped. */
    public function replace(?string $text, int &$count = 0): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        // Counted by hand: on an http-to-https move, an address that already
        // says https matches too, and rewriting it to itself is not a change.
        $result = preg_replace_callback($this->pattern, function (array $match) use (&$count) {
            if ($match[0] !== $this->to) {
                $count++;
            }

            return $this->to;
        }, $text);

        return $result ?? $text;
    }

    /** Walks an array, swapping inside every string value. Keys are left alone. */
    public function replaceInArray(mixed $value, int &$count = 0): mixed
    {
        if (is_string($value)) {
            return $this->replace($value, $count);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->replaceInArray($item, $count);
            }
        }

        return $value;
    }

    /**
     * A stored column, JSON or not.
     *
     * Returns the value unchanged - byte for byte - when nothing matched, so a
     * caller can compare and skip the write.
     */
    public function replaceStored(?string $value, int &$count = 0): ?string
    {
        if ($value === null || $value === '' || ! preg_match($this->pattern, str_replace('\/', '/', $value))) {
            return $value;
        }

        $first = $value[0];

        if ($first === '{' || $first === '[') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $found = 0;
                $changed = $this->replaceInArray($decoded, $found);

                if ($found === 0) {
                    return $value;
                }

                $count += $found;

                return json_encode($changed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return $this->replace($value, $count);
    }
}
