<?php

namespace App\Cms\Builder;

/**
 * The built-in icon set, drawn as inline SVG.
 *
 * Inlining avoids loading an icon font or a sprite sheet for what is usually a
 * handful of glyphs, and it means an icon inherits colour and size from CSS
 * like any other text - which is what lets the editor's colour and size
 * controls affect it without special handling.
 *
 * Paths are stroke-based on a 24x24 grid unless marked as filled.
 */
class IconLibrary
{
    /** Icons drawn with fill rather than stroke. */
    private const FILLED = ['star', 'heart', 'facebook', 'instagram', 'twitter',
        'linkedin', 'youtube', 'whatsapp', 'tiktok', 'pinterest', 'github', 'play'];

    private const PATHS = [
        // General
        'star' => '<path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z"/>',
        'heart' => '<path d="M12 21s-7.5-4.6-9.5-9A5.3 5.3 0 0 1 12 6.7 5.3 5.3 0 0 1 21.5 12c-2 4.4-9.5 9-9.5 9z"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
        'close' => '<path d="M18 6L6 18M6 6l12 12"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
        'alert' => '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        'question' => '<circle cx="12" cy="12" r="9"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>',
        'lightbulb' => '<path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/>',
        'sparkles' => '<path d="M12 3l1.9 4.1L18 9l-4.1 1.9L12 15l-1.9-4.1L6 9l4.1-1.9z"/><path d="M18 15l.9 2.1L21 18l-2.1.9L18 21l-.9-2.1L15 18l2.1-.9z"/>',
        'fire' => '<path d="M12 22a7 7 0 0 0 7-7c0-5-4-6-4-10 0 0-3 1.5-3 5 0 1.5-1 2-1.5 1.5S9 9 9 9s-4 2.5-4 6a7 7 0 0 0 7 7z"/>',
        'bolt' => '<path d="M13 2L4.5 13.5H11l-1 8.5 8.5-11.5H12z"/>',
        'gift' => '<path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>',
        'tag' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0l-8-8A2 2 0 0 1 2 11.2V4a2 2 0 0 1 2-2h7.2a2 2 0 0 1 1.4.6l8 8a2 2 0 0 1 0 2.8z"/><path d="M7 7h.01"/>',
        'award' => '<circle cx="12" cy="9" r="6"/><path d="M8.2 14.3L7 22l5-3 5 3-1.2-7.7"/>',

        // Arrows
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'arrow-down' => '<path d="M12 5v14M6 13l6 6 6-6"/>',
        'chevron-right' => '<path d="M9 6l6 6-6 6"/>',
        'chevron-left' => '<path d="M15 6l-6 6 6 6"/>',
        'chevron-up' => '<path d="M18 15l-6-6-6 6"/>',
        'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
        'external' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/>',
        'undo' => '<path d="M9 14L4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
        'redo' => '<path d="M15 14l5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/>',

        // Business
        'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'rocket' => '<path d="M5 13c-1.5 1.5-2 5-2 5s3.5-.5 5-2M12 15l-3-3a12 12 0 0 1 8-9 12 12 0 0 1-2 11l-3 1z"/><circle cx="14.5" cy="9.5" r="1.5"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'document' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'clipboard' => '<rect x="8" y="3" width="8" height="4" rx="1"/><path d="M16 5h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2"/>',
        'wallet' => '<path d="M20 8V6a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h15a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6"/><path d="M17 13h.01"/>',

        // Contact
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 6 10-6"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
        'map-pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z"/>',
        'chat' => '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.2a8.4 8.4 0 0 1-.9-3.8 8.4 8.4 0 0 1 8.4-9 8.4 8.4 0 0 1 8.6 8.5z"/>',
        'send' => '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v1"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a5 5 0 0 1 4 5v2"/>',

        // Shop
        'cart' => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.4 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21 7H6"/>',
        'bag' => '<path d="M5 7h14l1 14H4z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>',
        'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'truck' => '<path d="M3 6h11v11H3z"/><path d="M14 9h4l3 3v5h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'package' => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/>',
        'refresh' => '<path d="M21 12a9 9 0 0 1-15.5 6.2L3 16M3 12a9 9 0 0 1 15.5-6.2L21 8"/><path d="M21 3v5h-5M3 21v-5h5"/>',
        'percent' => '<path d="M19 5L5 19"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/>',

        // Media
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
        'video' => '<rect x="2" y="5" width="14" height="14" rx="2"/><path d="M22 8l-6 4 6 4z"/>',
        'camera' => '<path d="M3 7h3l2-3h8l2 3h3a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="4"/>',
        'music' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'play' => '<path d="M6 3l15 9-15 9z"/>',
        'headphones' => '<path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1v-6h3zM3 19a2 2 0 0 0 2 2h1v-6H3z"/>',
        'mic' => '<rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v4"/>',

        // Editor UI
        'heading' => '<path d="M6 4v16M18 4v16M6 12h12"/>',
        'text' => '<path d="M4 6h16M4 12h16M4 18h10"/>',
        'button' => '<rect x="3" y="8" width="18" height="8" rx="4"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        'gallery' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 15l4-4 4 4 3-3 7 7"/>',
        'spacer' => '<path d="M12 3v18M8 7l4-4 4 4M8 17l4 4 4-4"/>',
        'divider' => '<path d="M3 12h18"/>',
        'code' => '<path d="M16 18l6-6-6-6M8 6l-6 6 6 6"/>',
        'accordion' => '<rect x="3" y="4" width="18" height="5" rx="1"/><rect x="3" y="12" width="18" height="8" rx="1"/>',
        'tabs' => '<path d="M3 9h6V4h12v16H3z"/><path d="M3 9h6"/>',
        'quote' => '<path d="M7 15a4 4 0 1 1 0-8c0-2 1-3 3-4-4 4-3 5-3 6M17 15a4 4 0 1 1 0-8c0-2 1-3 3-4-4 4-3 5-3 6"/>',
        'counter' => '<path d="M4 20V10M10 20V4M16 20v-8M22 20V7"/>',
        'pricing' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h4"/>',
        'form' => '<rect x="3" y="4" width="18" height="4" rx="1"/><rect x="3" y="12" width="18" height="8" rx="1"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'logo' => '<circle cx="12" cy="12" r="9"/><path d="M8 14l3-5 2 3 3-4"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'share' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/>',
        'map' => '<path d="M1 6l7-3 8 3 7-3v15l-7 3-8-3-7 3z"/><path d="M8 3v15M16 6v15"/>',
        'square' => '<rect x="4" y="4" width="16" height="16" rx="2"/>',
        'desktop' => '<rect x="2" y="4" width="20" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'tablet' => '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>',
        'mobile' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M12 18h.01"/>',

        // Social
        'facebook' => '<path d="M14 9V7c0-1 .3-1.5 1.7-1.5H18V2h-3c-3 0-4.5 1.8-4.5 4.6V9H8v4h2.5v9H14v-9h3l.5-4z"/>',
        'instagram' => '<path d="M12 2.2c3.2 0 3.6 0 4.9.1 3.3.1 4.8 1.7 5 5 0 1.2.1 1.6.1 4.7s0 3.5-.1 4.7c-.2 3.3-1.7 4.9-5 5-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-3.3-.1-4.8-1.7-5-5C2 15.5 2 15.1 2 12s0-3.5.1-4.7c.2-3.3 1.7-4.9 5-5C8.4 2.2 8.8 2.2 12 2.2zm0 4.9a4.9 4.9 0 1 0 0 9.8 4.9 4.9 0 0 0 0-9.8zm0 8a3.1 3.1 0 1 1 0-6.2 3.1 3.1 0 0 1 0 6.2zm5.1-8.2a1.1 1.1 0 1 1 0-2.3 1.1 1.1 0 0 1 0 2.3z"/>',
        'twitter' => '<path d="M18.2 2H21l-6.4 7.3L22 22h-5.8l-4.6-6-5.2 6H3.6l6.9-7.9L2.5 2h6l4.1 5.4zm-1 18h1.6L7.9 3.7H6.2z"/>',
        'linkedin' => '<path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9h4v12H3zM10 9h3.8v1.7c.5-1 1.8-2 3.7-2 4 0 4.5 2.5 4.5 5.8V21h-4v-5.7c0-1.4 0-3.2-2-3.2s-2.2 1.5-2.2 3.1V21h-4z"/>',
        'youtube' => '<path d="M23 12s0-3.3-.4-4.9a2.5 2.5 0 0 0-1.8-1.8C19.2 5 12 5 12 5s-7.2 0-8.8.4a2.5 2.5 0 0 0-1.8 1.8C1 8.7 1 12 1 12s0 3.3.4 4.9a2.5 2.5 0 0 0 1.8 1.8c1.6.3 8.8.3 8.8.3s7.2 0 8.8-.4a2.5 2.5 0 0 0 1.8-1.8c.4-1.5.4-4.8.4-4.8zM9.8 15.2V8.8l6 3.2z"/>',
        'whatsapp' => '<path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2zm5.8 14.2c-.2.7-1.4 1.3-2 1.4-.5.1-1.2.1-1.9-.1-.4-.1-1-.3-1.8-.6-3.1-1.3-5.1-4.4-5.3-4.6-.1-.2-1.2-1.6-1.2-3s.7-2.1 1-2.4c.3-.3.6-.4.8-.4h.6c.2 0 .4 0 .7.5l.9 2.2c.1.2.1.4 0 .6l-.4.5-.3.3c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.1 1 2 1.3 2.3 1.4.3.1.5.1.7-.1l.9-1c.2-.2.4-.2.6-.1l2.1 1c.3.1.5.2.5.3.1.2.1.6-.1 1.2z"/>',
        'tiktok' => '<path d="M16.5 2h-3v13.2a2.6 2.6 0 1 1-2.6-2.6c.3 0 .5 0 .7.1v-3a5.6 5.6 0 1 0 4.9 5.5V9a6.8 6.8 0 0 0 4 1.3v-3a3.8 3.8 0 0 1-4-3.7z"/>',
        'pinterest' => '<path d="M12 2a10 10 0 0 0-3.6 19.3c-.1-.8-.2-2 0-2.9l1.2-5s-.3-.6-.3-1.5c0-1.4.8-2.5 1.8-2.5.9 0 1.3.6 1.3 1.4 0 .9-.5 2.2-.8 3.4-.2.9.5 1.7 1.4 1.7 1.7 0 3-1.8 3-4.4 0-2.3-1.6-3.9-4-3.9-2.7 0-4.3 2-4.3 4.1 0 .8.3 1.7.7 2.2l.1.3-.2.9c0 .2-.1.2-.3.1-1.2-.5-1.9-2.2-1.9-3.6 0-2.9 2.1-5.6 6.1-5.6 3.2 0 5.7 2.3 5.7 5.3 0 3.2-2 5.8-4.8 5.8-.9 0-1.8-.5-2.1-1.1l-.6 2.2c-.2.8-.8 1.9-1.2 2.5A10 10 0 1 0 12 2z"/>',
        'github' => '<path d="M12 2a10 10 0 0 0-3.2 19.5c.5.1.7-.2.7-.5v-1.8c-2.8.6-3.4-1.3-3.4-1.3-.4-1.2-1.1-1.5-1.1-1.5-.9-.6.1-.6.1-.6 1 .1 1.5 1 1.5 1 .9 1.5 2.3 1.1 2.9.8.1-.6.3-1.1.6-1.3-2.2-.3-4.6-1.1-4.6-5 0-1.1.4-2 1-2.7-.1-.3-.4-1.3.1-2.7 0 0 .8-.3 2.7 1a9.4 9.4 0 0 1 5 0c1.9-1.3 2.7-1 2.7-1 .5 1.4.2 2.4.1 2.7.6.7 1 1.6 1 2.7 0 3.9-2.4 4.7-4.6 5 .3.3.7 1 .7 2v2.9c0 .3.2.6.7.5A10 10 0 0 0 12 2z"/>',
    ];

    public static function has(string $name): bool
    {
        return isset(self::PATHS[$name]);
    }

    /** @return string[] */
    public static function names(): array
    {
        return array_keys(self::PATHS);
    }

    /**
     * Inline SVG for an icon, or an empty string when the name is unknown.
     *
     * The name is looked up in a fixed table rather than interpolated, so a
     * stored setting can never inject arbitrary markup here.
     */
    public static function svg(string $name, array $attributes = []): string
    {
        $path = self::PATHS[$name] ?? null;

        if ($path === null) {
            return '';
        }

        $filled = in_array($name, self::FILLED, true);

        $defaults = [
            'viewBox' => '0 0 24 24',
            'width' => '1em',
            'height' => '1em',
            'fill' => $filled ? 'currentColor' : 'none',
            'aria-hidden' => 'true',
            'focusable' => 'false',
        ];

        if (! $filled) {
            $defaults['stroke'] = 'currentColor';
            $defaults['stroke-width'] = '1.7';
            $defaults['stroke-linecap'] = 'round';
            $defaults['stroke-linejoin'] = 'round';
        }

        $merged = array_merge($defaults, $attributes);
        $rendered = '';

        foreach ($merged as $key => $value) {
            $rendered .= ' '.$key.'="'.e($value).'"';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg"'.$rendered.'>'.$path.'</svg>';
    }

    /** Grouped names for the editor's icon picker. */
    public static function grouped(): array
    {
        $groups = [];

        foreach (config('builder.icons', []) as $group => $names) {
            $groups[$group] = array_values(array_filter($names, fn ($name) => self::has($name)));
        }

        return $groups;
    }
}
