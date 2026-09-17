<?php

namespace App\Cms\Search;

/**
 * Turns text into the words the index stores and a query looks up.
 *
 * The same function runs on both sides, which is what makes matching work:
 * "Café", "cafe" and "CAFÉ" all become "cafe" whether they are being indexed
 * or typed. Letters in any script survive - Hindi, Arabic and Cyrillic words
 * are indexed as words - and only accents on Latin letters are folded away.
 * There is no stemming: "running" does not find "run", but a prefix search
 * on the last word typed covers most of what a visitor expects.
 */
final class Tokenizer
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 40;

    /**
     * Words in $text, lower-cased and in order, duplicates kept (the index
     * counts them).
     *
     * @return array<int, string>
     */
    public static function words(?string $text): array
    {
        $text = self::normalize($text);

        if ($text === '') {
            return [];
        }

        $words = [];

        foreach (preg_split('/[^\p{L}\p{N}\p{M}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $length = mb_strlen($word);

            if ($length >= self::MIN_LENGTH && $length <= self::MAX_LENGTH) {
                $words[] = $word;
            }
        }

        return $words;
    }

    /**
     * Distinct words of a query, in the order typed.
     *
     * @return array<int, string>
     */
    public static function queryWords(?string $query): array
    {
        return array_values(array_unique(self::words($query)));
    }

    /**
     * The shard a word is stored in: its first two characters, hex encoded so
     * the file name is safe on every filesystem whatever the script.
     */
    public static function shard(string $word): string
    {
        return bin2hex(mb_substr($word, 0, self::MIN_LENGTH));
    }

    private static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // Tags would otherwise glue words together: "<p>one</p><p>two</p>".
        $text = strip_tags(str_replace('<', ' <', $text));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower($text, 'UTF-8');

        // Fold accents on Latin letters only. Combining marks in other
        // scripts carry meaning (Devanagari vowel signs) and are kept.
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($text, \Normalizer::FORM_D);

            if ($decomposed !== false) {
                $text = preg_replace('/(?<=\p{Latin})\p{Mn}+/u', '', $decomposed) ?? $text;
                $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
            }
        }

        return $text;
    }
}
