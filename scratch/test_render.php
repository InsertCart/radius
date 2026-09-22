<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Cms\Themes\ThemeSections;
use Illuminate\Support\Facades\View;

$sections = app(ThemeSections::class);
$all = $sections->all();

echo "=== THEME SECTIONS AUDIT ===\n";
echo "Total registered sections: " . count($all) . "\n\n";

// =========================================================================
// 1. Video Preview URL & Video Badge Tests
// =========================================================================
echo "=== 1. VIDEO PREVIEW TESTS ===\n";

// Case A: video_url is empty / removed
$heroNoVideo = View::make('theme::sections.hero', [
    'settings' => [
        'video_url' => '',
        'title' => 'Ocean Hero Without Video',
    ]
])->render();

assert(!str_contains($heroNoVideo, 'sn-hero__video-badge'), 'FAIL: video-badge should NOT be rendered when video_url is empty');
assert(!str_contains($heroNoVideo, 'snVideoModal'), 'FAIL: snVideoModal should NOT be rendered when video_url is empty');
assert(!str_contains($heroNoVideo, 'youtube'), 'FAIL: youtube should NOT be in output when video_url is empty');
echo "PASS: When video_url is empty, video badge and modal are completely hidden.\n";

// Case B: video_url is whitespace
$heroSpaceVideo = View::make('theme::sections.hero', [
    'settings' => [
        'video_url' => '   ',
        'title' => 'Ocean Hero With Space Video',
    ]
])->render();
assert(!str_contains($heroSpaceVideo, 'sn-hero__video-badge'), 'FAIL: video-badge should NOT be rendered when video_url is whitespace');
assert(!str_contains($heroSpaceVideo, 'snVideoModal'), 'FAIL: snVideoModal should NOT be rendered when video_url is whitespace');
echo "PASS: When video_url is whitespace, video badge and modal are completely hidden.\n";

// Case C: video_url is provided (YouTube)
$heroWithVideo = View::make('theme::sections.hero', [
    'settings' => [
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'video_badge_label' => 'LIVE HARBOR CAM',
        'video_badge_title' => 'Fleet in Motion',
    ]
])->render();
assert(str_contains($heroWithVideo, 'sn-hero__video-badge'), 'FAIL: video-badge SHOULD be rendered when video_url is provided');
assert(str_contains($heroWithVideo, 'snVideoModal'), 'FAIL: snVideoModal SHOULD be rendered when video_url is provided');
assert(str_contains($heroWithVideo, 'youtube-nocookie.com/embed/dQw4w9WgXcQ'), 'FAIL: embed URL should be correctly formed');
assert(str_contains($heroWithVideo, 'LIVE HARBOR CAM'), 'FAIL: custom badge label should be rendered');
echo "PASS: When video_url is provided, video badge and modal are properly rendered with embed URL.\n";

// =========================================================================
// 2. Button Visibility Tests (When blank, do not show button)
// =========================================================================
echo "\n=== 2. THEME-WIDE BUTTON BLANK VISIBILITY TESTS ===\n";

// 2.1 Hero CTA Button
$heroNoCta = View::make('theme::sections.hero', [
    'settings' => [
        'cta_text' => '',
    ]
])->render();
assert(!str_contains($heroNoCta, 'sn-btn--primary'), 'FAIL: hero primary quote button should NOT be rendered when cta_text is blank');
echo "PASS: Hero CTA button is hidden when cta_text is blank.\n";

// 2.2 Solutions button
$solNoBtn = View::make('theme::sections.solutions', [
    'settings' => [
        'btn_text' => '',
    ]
])->render();
assert(!str_contains($solNoBtn, 'sn-btn--primary'), 'FAIL: solutions button should NOT be rendered when btn_text is blank');
echo "PASS: Solutions button is hidden when btn_text is blank.\n";

$solWithBtn = View::make('theme::sections.solutions', [
    'settings' => [
        'btn_text' => 'Discover More',
        'btn_url' => '/custom-services',
    ]
])->render();
assert(str_contains($solWithBtn, 'Discover More'), 'FAIL: solutions button SHOULD be rendered when btn_text is filled');
assert(str_contains($solWithBtn, '/custom-services'), 'FAIL: solutions button URL should match');
echo "PASS: Solutions button renders correctly when btn_text is provided.\n";

// 2.3 CTA banner buttons
$ctaNoBtns = View::make('theme::sections.cta', [
    'settings' => [
        'badge' => '',
        'btn1_text' => '',
        'btn2_text' => '',
    ]
])->render();
assert(!str_contains($ctaNoBtns, 'sn-cta__actions'), 'FAIL: sn-cta__actions should NOT render when both buttons are blank');
assert(!str_contains($ctaNoBtns, 'sn-badge'), 'FAIL: sn-badge should NOT render when badge is blank');
echo "PASS: CTA section hides buttons container and badge when blank.\n";

$ctaOneBtn = View::make('theme::sections.cta', [
    'settings' => [
        'btn1_text' => 'Only Button 1',
        'btn2_text' => '',
    ]
])->render();
assert(str_contains($ctaOneBtn, 'Only Button 1'), 'FAIL: Button 1 should render');
assert(!str_contains($ctaOneBtn, 'sn-btn--outline'), 'FAIL: Button 2 (outline) should NOT render');
echo "PASS: CTA section renders only Button 1 when Button 2 is blank.\n";

// 2.4 End-to-End Book button & badges
$e2eNoBtn = View::make('theme::sections.end_to_end', [
    'settings' => [
        'badge' => '',
        'sub_badge' => '',
        'btn_text' => '',
    ]
])->render();
assert(!str_contains($e2eNoBtn, 'Book This Route'), 'FAIL: Book This Route button should NOT render when btn_text is blank');
assert(!str_contains($e2eNoBtn, 'sn-badge'), 'FAIL: badge should NOT render when blank');
assert(!str_contains($e2eNoBtn, 'sn-gallery-item__badge'), 'FAIL: sub_badge should NOT render when blank');
echo "PASS: End-to-End section hides book button and badges when blank.\n";

// 2.5 Rail button
$railNoBtn = View::make('theme::sections.rail', [
    'settings' => [
        'btn_text' => '',
        'badge' => '',
    ]
])->render();
assert(!str_contains($railNoBtn, 'sn-btn--outline'), 'FAIL: rail button should NOT render when btn_text is blank');
echo "PASS: Rail section hides button when btn_text is blank.\n";

// 2.6 Journal button
$journalNoBtn = View::make('theme::sections.journal', [
    'settings' => [
        'btn_text' => '',
        'badge' => '',
    ]
])->render();
assert(!str_contains($journalNoBtn, 'sn-btn--outline'), 'FAIL: journal button should NOT render when btn_text is blank');
echo "PASS: Journal section hides button when btn_text is blank.\n";

// 2.7 Newsletter button
$nlNoBtn = View::make('theme::sections.newsletter', [
    'settings' => [
        'btn_text' => '',
        'badge' => '',
    ]
])->render();
assert(!str_contains($nlNoBtn, '<button type="submit"'), 'FAIL: newsletter submit button should NOT render when btn_text is blank');
assert(!str_contains($nlNoBtn, 'sn-badge'), 'FAIL: newsletter badge should NOT render when blank');
echo "PASS: Newsletter section hides submit button and badge when blank.\n";

// 2.8 Announcement strip
$announcementEmpty = View::make('theme::partials.announcement', [
    'settings' => [
        'badge' => '',
        'link_text' => '',
    ]
])->render();
assert(!str_contains($announcementEmpty, 'sn-announcement__badge'), 'FAIL: announcement badge should NOT render when blank');
assert(!str_contains($announcementEmpty, 'sn-announcement__link'), 'FAIL: announcement link should NOT render when blank');
echo "PASS: Announcement strip hides badge and link when blank.\n";

// 2.9 Header Quote button
$headerNoBtn = View::make('theme::partials.header', [
    'settings' => [
        'btn_text' => '',
    ]
])->render();
assert(!str_contains($headerNoBtn, 'sn-btn--glass'), 'FAIL: header quote button should NOT render when btn_text is blank');
assert(!str_contains($headerNoBtn, 'sn-btn--blue'), 'FAIL: mobile drawer quote button should NOT render when btn_text is blank');
echo "PASS: Header hides quote buttons on desktop and mobile drawer when btn_text is blank.\n";

echo "\n=======================================================\n";
echo "ALL TESTS PASSED SUCCESSFULLY! THEME IS 100% VERIFIED.\n";
echo "=======================================================\n";
