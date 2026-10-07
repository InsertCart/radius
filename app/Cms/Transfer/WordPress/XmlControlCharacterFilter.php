<?php

namespace App\Cms\Transfer\WordPress;

use php_user_filter;

/**
 * Stream filter that drops the C0 control characters XML 1.0 forbids.
 *
 * WordPress exports post content byte for byte, so a stray ETX or form feed
 * pasted in from Word or a PDF lands in the WXR file as-is - and libxml then
 * refuses the whole document at that point. Tab, newline and carriage return
 * are legal and kept.
 *
 * Working chunk by chunk is safe: these are single bytes below 0x20, which
 * never occur inside a multi-byte UTF-8 sequence, so a chunk boundary can not
 * split one.
 */
class XmlControlCharacterFilter extends php_user_filter
{
    public const NAME = 'radius.xml-control-characters';

    public static function register(): void
    {
        if (! in_array(self::NAME, stream_get_filters(), true)) {
            stream_filter_register(self::NAME, self::class);
        }
    }

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $bucket->data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $bucket->data);
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
