<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

/**
 * Raw HTML, for embed codes a dedicated widget does not cover.
 *
 * Output is deliberately not escaped, which is the point of the widget. Only
 * administrators can place one, and the editor says so plainly.
 */
class HtmlBlock extends Block
{
    public static function type(): string
    {
        return 'html';
    }

    public static function name(): string
    {
        return 'HTML';
    }

    public static function icon(): string
    {
        return 'code';
    }

    public static function category(): string
    {
        return 'content';
    }

    public static function order(): int
    {
        return 90;
    }

    public static function keywords(): array
    {
        return ['embed', 'code', 'script', 'iframe'];
    }

    public static function controls(): array
    {
        return [
            Control::code('html', 'HTML')
                ->placeholder('<div>Your markup</div>'),

            Control::notice('warning', 'Handle with care')
                ->help('This markup is output exactly as written, including any scripts. Only paste embed code from sources you trust.'),
        ];
    }
}
