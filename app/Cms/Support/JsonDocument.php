<?php

namespace App\Cms\Support;

/**
 * Decodes JSON that a person wrote and uploaded by hand - the update manifest,
 * the theme marketplace catalogue - forgiving the ways such files arrive.
 */
class JsonDocument
{
    /**
     * The byte-order mark is the one that matters: Notepad, PowerShell's
     * Set-Content and several Windows editors all add one by default, it is
     * invisible in every editor, and json_decode rejects the file outright
     * because of it. Leaving that unhandled would mean a publisher's perfectly
     * correct file silently never working, with nothing on screen to explain
     * why.
     */
    public static function decode(string $body): mixed
    {
        $body = ltrim($body, "\xEF\xBB\xBF \t\n\r\0\x0B");

        // Some editors save UTF-16 when told to "save as Unicode".
        if (str_starts_with($body, "\xFF\xFE") || str_starts_with($body, "\xFE\xFF")) {
            $converted = @mb_convert_encoding($body, 'UTF-8', 'UTF-16');

            if (is_string($converted)) {
                $body = ltrim($converted, "\xEF\xBB\xBF \t\n\r\0\x0B");
            }
        }

        return json_decode($body, true);
    }
}
