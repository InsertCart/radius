<?php

namespace Database\Seeders\Demo;

/**
 * Turns a compact outline into the HTML the rich-text editor would have
 * produced, so the demo content arrays stay readable.
 *
 * A plain string becomes a paragraph. An array is a [tag, value] pair, where
 * 'ul' takes a list of items and everything else wraps its value in that tag.
 */
trait BuildsHtmlBody
{
    /**
     * @param  array<int, string|array{0: string, 1: string|array<int, string>}>  $blocks
     */
    protected static function body(array $blocks): string
    {
        $html = [];

        foreach ($blocks as $block) {
            if (is_string($block)) {
                $html[] = '<p>'.$block.'</p>';

                continue;
            }

            [$tag, $value] = $block;

            $html[] = match ($tag) {
                'ul' => '<ul>'.implode('', array_map(fn ($item) => '<li>'.$item.'</li>', (array) $value)).'</ul>',
                'blockquote' => '<blockquote><p>'.$value.'</p></blockquote>',
                default => '<'.$tag.'>'.$value.'</'.$tag.'>',
            };
        }

        return implode("\n", $html);
    }
}
