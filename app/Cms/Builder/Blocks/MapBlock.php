<?php

namespace App\Cms\Builder\Blocks;

use App\Cms\Builder\Control;

/**
 * An embedded map.
 *
 * Uses OpenStreetMap by default, which needs no API key and sets no tracking
 * cookies - a better default for a product sold to many buyers than requiring
 * everyone to obtain a Google key before a map will render.
 */
class MapBlock extends Block
{
    public static function type(): string
    {
        return 'map';
    }

    public static function name(): string
    {
        return 'Map';
    }

    public static function icon(): string
    {
        return 'map';
    }

    public static function category(): string
    {
        return 'media';
    }

    public static function order(): int
    {
        return 4;
    }

    public static function keywords(): array
    {
        return ['location', 'address', 'directions'];
    }

    public static function controls(): array
    {
        return [
            Control::text('address', 'Address')
                ->default('London, United Kingdom')
                ->help('Used as the marker label.'),

            Control::number('lat', 'Latitude')->default(51.5072)->step(0.000001),
            Control::number('lng', 'Longitude')->default(-0.1276)->step(0.000001),

            Control::slider('zoom', 'Zoom')
                ->min(1)->max(19)->units([''])
                ->default(['size' => 13, 'unit' => '']),

            Control::slider('height', 'Height')
                ->min(150)->max(800)->units(['px'])
                ->default(['size' => 360, 'unit' => 'px'])
                ->responsive()
                ->selector('{{WRAPPER}} .cb-map', 'height'),

            Control::dimensions('radius', 'Rounded corners')
                ->tab(Control::TAB_STYLE)
                ->selector('{{WRAPPER}} .cb-map', 'border-radius'),
        ];
    }

    public function data(array $settings, array $context = []): array
    {
        $lat = (float) ($settings['lat'] ?? 51.5072);
        $lng = (float) ($settings['lng'] ?? -0.1276);

        $zoom = $settings['zoom'] ?? 13;
        $zoom = is_array($zoom) ? ($zoom['size'] ?? 13) : $zoom;

        // A bounding box roughly proportional to the zoom level.
        $span = 0.08 / max(1, 2 ** (((int) $zoom) - 11));

        return [
            'embedUrl' => 'https://www.openstreetmap.org/export/embed.html?bbox='
                .($lng - $span).','.($lat - $span / 2).','.($lng + $span).','.($lat + $span / 2)
                .'&layer=mapnik&marker='.$lat.','.$lng,
            'linkUrl' => 'https://www.openstreetmap.org/?mlat='.$lat.'&mlon='.$lng.'#map='.((int) $zoom).'/'.$lat.'/'.$lng,
        ];
    }
}
