<?php

namespace App\Cms\Themes;

/**
 * Every theme section shares one widget type, so each section's settings are
 * stored under a prefix ("rail__title") to keep two sections' fields apart.
 */
final class ThemeSectionKeys
{
    public static function prefixed(string $section, string $key): string
    {
        return $section.'__'.$key;
    }

    /** The settings belonging to one section, with the prefix removed. */
    public static function extract(string $section, array $settings): array
    {
        $prefix = $section.'__';
        $own = [];

        foreach ($settings as $key => $value) {
            if (str_starts_with((string) $key, $prefix)) {
                $own[substr($key, strlen($prefix))] = $value;
            }
        }

        return $own;
    }
}
