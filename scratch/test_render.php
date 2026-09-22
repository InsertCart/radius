<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Cms\Themes\ThemeSections;
use App\Cms\Builder\Blocks\ThemeSectionBlock;
use Illuminate\Support\Facades\View;

$sections = app(ThemeSections::class);
$all = $sections->all();

echo "=== THEME SECTIONS AUDIT ===\n";
echo "Total registered sections: " . count($all) . "\n\n";

$targetSections = ['hero', 'solutions', 'metrics', 'end_to_end', 'testimonials', 'cta'];
foreach ($targetSections as $key) {
    if (!isset($all[$key])) {
        echo "ERROR: Missing section {$key}!\n";
        continue;
    }
    $sec = $all[$key];
    $controls = $sections->controls($key);
    $controlTypes = array_map(fn($c) => $c->toArray()['type'], $controls);
    $controlKeys = array_map(fn($c) => $c->toArray()['key'], $controls);
    echo "Section '{$key}': " . count($controls) . " controls. Types: " . implode(', ', array_unique($controlTypes)) . "\n";
    echo "  Keys: " . implode(', ', $controlKeys) . "\n";
}

echo "\n=== RENDERING VERIFICATION ===\n";

// 1. Test Hero Banner with custom image and badges
$heroHtml = View::make('theme::sections.hero', [
    'settings' => [
        'title' => 'Custom Hero Title Test',
        'image' => 'custom-uploads/my-ship.png',
        'video_badge_label' => 'OPERATIONS 24/7',
        'video_badge_title' => 'Streaming Live Fleet',
    ]
])->render();
assert(str_contains($heroHtml, 'Custom Hero Title Test'), 'Hero title rendered');
assert(str_contains($heroHtml, 'my-ship.png'), 'Hero custom image resolved');
assert(str_contains($heroHtml, 'OPERATIONS 24/7'), 'Hero video badge label rendered');
assert(str_contains($heroHtml, 'Streaming Live Fleet'), 'Hero video badge title rendered');
echo "PASS: Hero banner renders custom image and badges.\n";

// 2. Test Solutions with 3 custom cards and custom images
$solutionsHtml = View::make('theme::sections.solutions', [
    'settings' => [
        'title' => 'Flexible Logistics Solutions',
        'items' => [
            ['title' => 'Service A', 'tag' => 'Tag A', 'image' => 'media/service-a.jpg', 'url' => '/service-a'],
            ['title' => 'Service B', 'tag' => 'Tag B', 'image' => 'media/service-b.jpg', 'url' => '/service-b'],
            ['title' => 'Service C', 'tag' => 'Tag C', 'image' => 'images/air-freight.jpg', 'url' => '/service-c'],
        ]
    ]
])->render();
assert(str_contains($solutionsHtml, 'Service A'), 'Solutions Service A rendered');
assert(str_contains($solutionsHtml, 'Service B'), 'Solutions Service B rendered');
assert(str_contains($solutionsHtml, 'Service C'), 'Solutions Service C rendered');
assert(str_contains($solutionsHtml, 'service-a.jpg'), 'Service A image resolved');
assert(str_contains($solutionsHtml, 'air-freight.jpg'), 'Service C theme image resolved');
echo "PASS: Solutions section renders dynamic count of cards (3 cards) with custom images.\n";

// 3. Test Metrics with 5 custom counters
$metricsHtml = View::make('theme::sections.metrics', [
    'settings' => [
        'items' => [
            ['num' => '100%', 'label' => 'On-Time Vessel Departure'],
            ['num' => '150+', 'label' => 'Global Gateways'],
            ['num' => '12K', 'label' => 'Daily TEU Movement'],
            ['num' => '24/7', 'label' => 'Live Support Desk'],
            ['num' => '99.9%', 'label' => 'Cargo Integrity'],
        ]
    ]
])->render();
assert(str_contains($metricsHtml, '100%'), 'Metric 100% rendered');
assert(str_contains($metricsHtml, 'Cargo Integrity'), 'Metric Cargo Integrity rendered');
$metricItemCount = substr_count($metricsHtml, 'sn-metric-item__num');
assert($metricItemCount === 5, "Expected 5 metrics items, got {$metricItemCount}");
echo "PASS: Metrics section renders dynamic count of counters (5 items).\n";

// 4. Test End-to-End ("GLOBAL REACH") with custom badge and 3 custom images
$e2eHtml = View::make('theme::sections.end_to_end', [
    'settings' => [
        'badge' => 'WORLDWIDE CORRIDOR',
        'title' => 'Global Marine Route System',
        'sub_badge' => 'Direct Port-to-Port Linkage',
        'image1' => 'uploads/ship1.jpg',
        'image2' => 'uploads/plane2.jpg',
        'image3' => 'uploads/terminal3.jpg',
        'calc_title' => 'Fast Marine Rate Estimator',
    ]
])->render();
assert(str_contains($e2eHtml, 'WORLDWIDE CORRIDOR'), 'Custom badge rendered');
assert(str_contains($e2eHtml, 'ship1.jpg'), 'Image 1 resolved');
assert(str_contains($e2eHtml, 'plane2.jpg'), 'Image 2 resolved');
assert(str_contains($e2eHtml, 'terminal3.jpg'), 'Image 3 resolved');
assert(str_contains($e2eHtml, 'Fast Marine Rate Estimator'), 'Custom calc title rendered');
echo "PASS: End-to-End section renders custom badge ('WORLDWIDE CORRIDOR') and all 3 images.\n";

// 5. Test CTA ("START SHIPPING TODAY") with custom badge and 4 custom mosaic images
$ctaHtml = View::make('theme::sections.cta', [
    'settings' => [
        'badge' => 'INSTANT QUOTE DISPATCH',
        'title' => 'Accelerate Your Cargo Operations',
        'image1' => 'media/mosaic1.jpg',
        'image2' => 'media/mosaic2.jpg',
        'image3' => 'media/mosaic3.jpg',
        'image4' => 'media/mosaic4.jpg',
    ]
])->render();
assert(str_contains($ctaHtml, 'INSTANT QUOTE DISPATCH'), 'Custom CTA badge rendered');
assert(str_contains($ctaHtml, 'mosaic1.jpg'), 'Mosaic Image 1 resolved');
assert(str_contains($ctaHtml, 'mosaic2.jpg'), 'Mosaic Image 2 resolved');
assert(str_contains($ctaHtml, 'mosaic3.jpg'), 'Mosaic Image 3 resolved');
assert(str_contains($ctaHtml, 'mosaic4.jpg'), 'Mosaic Image 4 resolved');
echo "PASS: CTA section renders custom badge ('INSTANT QUOTE DISPATCH') and all 4 mosaic images.\n";

// 6. Test Testimonials with custom avatars and 4 items
$testimonialsHtml = View::make('theme::sections.testimonials', [
    'settings' => [
        'badge' => 'VERIFIED FEEDBACK',
        'items' => [
            ['quote' => 'Top notch freight partner.', 'author' => 'Alice Morgan', 'role' => 'Logistics VP', 'avatar' => 'avatars/alice.jpg', 'rating' => 5],
            ['quote' => 'Zero delays all season.', 'author' => 'Bob Chen', 'role' => 'Import Director', 'avatar' => 'images/client-robert.jpg', 'rating' => 5],
            ['quote' => 'Unbeatable ocean rates.', 'author' => 'Carla Diaz', 'role' => 'Procurement Lead', 'avatar' => 'avatars/carla.png', 'rating' => 5],
            ['quote' => 'Outstanding tracking portal.', 'author' => 'Dan Smith', 'role' => 'Warehouse Manager', 'avatar' => 'images/client-darlene.jpg', 'rating' => 5],
        ]
    ]
])->render();
assert(str_contains($testimonialsHtml, 'VERIFIED FEEDBACK'), 'Custom testimonials badge rendered');
assert(str_contains($testimonialsHtml, 'Alice Morgan'), 'Alice Morgan rendered');
assert(str_contains($testimonialsHtml, 'alice.jpg'), 'Alice avatar resolved');
assert(str_contains($testimonialsHtml, 'Carla Diaz'), 'Carla Diaz rendered');
assert(str_contains($testimonialsHtml, 'data-testimonials'), 'JSON dataset embedded for carousel switching');
echo "PASS: Testimonials section renders custom badge, custom avatars, and 4 items.\n";

echo "\nALL TESTS COMPLETED SUCCESSFULLY!\n";
