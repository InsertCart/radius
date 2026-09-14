<?php

namespace Database\Seeders\Demo;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Demo pages, including a two-level tree so the parent/child picker and the
 * breadcrumb rendering have something real to work with.
 *
 * The landing page is created but is deliberately NOT set as the homepage:
 * flipping that switch changes what the site serves at "/", and demo data
 * should never make that decision for you.
 */
class DemoPageSeeder extends Seeder
{
    use BuildsHtmlBody;

    public function __construct(private DemoMediaFactory $media) {}

    /** @return array<int, string> */
    public static function pageSlugs(): array
    {
        return array_column(self::pages(), 'slug');
    }

    public function run(): void
    {
        $ids = [];

        // Two passes: parents must exist before a child can point at one.
        foreach (self::pages() as $data) {
            $page = Page::withTrashed()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'status' => $data['status'] ?? 'published',
                    'show_in_menu' => $data['show_in_menu'] ?? false,
                    'sort_order' => $data['sort_order'] ?? 0,
                    'template' => $data['template'] ?? null,
                    'schema_type' => $data['schema_type'] ?? null,
                    'featured_image' => isset($data['palette'])
                        ? $this->media->image('page-'.$data['slug'], $data['title'], $data['palette'])
                        : null,
                    'meta_title' => $data['title'],
                    'meta_description' => $data['meta_description'] ?? null,
                    'deleted_at' => null,
                ]
            );

            $ids[$data['slug']] = $page->id;
        }

        foreach (self::pages() as $data) {
            if (empty($data['parent'])) {
                continue;
            }

            // 'about' ships with the installer rather than the demo set, so a
            // parent that is not in $ids is still worth looking up.
            $parentId = $ids[$data['parent']] ?? Page::where('slug', $data['parent'])->value('id');

            Page::whereKey($ids[$data['slug']])->update(['parent_id' => $parentId]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private static function pages(): array
    {
        return [
            [
                'slug' => 'welcome',
                'title' => 'Welcome',
                'status' => 'published',
                'show_in_menu' => false,
                'sort_order' => 0,
                'palette' => 'espresso',
                'meta_description' => 'A sample landing page. Set it as the homepage from Content, Pages if you want it at the root of the site.',
                'content' => self::body([
                    ['h2', 'Coffee roasted the week you drink it'],
                    'This is a sample landing page created by the demo data. Everything on it is editable from <strong>Content &rarr; Pages</strong>, and you can make it the site homepage by ticking "Use as homepage" in the page settings.',
                    ['ul', [
                        '<strong>Roasted to order.</strong> Nothing ships more than 48 hours after it leaves the drum.',
                        '<strong>Published pricing.</strong> We list what we paid the producer, lot by lot.',
                        '<strong>Free delivery over 40.</strong> Two to three working days, tracked.',
                    ]],
                    ['h2', 'Not sure where to start?'],
                    'The Morning Blend is the safe answer: forgiving in any brewer, good with milk, and hard to get wrong. If you already know your way around a grinder, go straight to the single origins.',
                    ['blockquote', 'Delete this page, or rewrite it - it exists so a fresh install has something to look at other than an empty shell.'],
                ]),
            ],
            [
                'slug' => 'our-story',
                'title' => 'Our story',
                'parent' => 'about',
                'status' => 'published',
                'show_in_menu' => false,
                'sort_order' => 1,
                'palette' => 'clay',
                'meta_description' => 'How a twelve-kilo drum in a rented unit turned into a roastery.',
                'content' => self::body([
                    'We started in 2019 with a second-hand twelve-kilo drum, a rented unit with questionable wiring, and a list of four cafes who had agreed to try a bag.',
                    ['h2', 'The first two years'],
                    'Most of it was spent learning what we did not know. We roasted too dark for six months because it hid our mistakes, then too light for three because we overcorrected, and eventually settled somewhere honest.',
                    ['h2', 'Where we are now'],
                    'Eleven producers across four countries, a wholesale list of about thirty cafes, and the same drum. The wiring has been fixed.',
                ]),
            ],
            [
                'slug' => 'faq',
                'title' => 'Frequently asked questions',
                'status' => 'published',
                'show_in_menu' => true,
                'sort_order' => 2,
                'schema_type' => 'FAQPage',
                'meta_description' => 'Delivery times, subscriptions, grind options and what to do if something arrives damaged.',
                'content' => self::body([
                    ['h2', 'When is my coffee roasted?'],
                    'Tuesdays and Thursdays. Orders placed before 10am on a roast day ship that afternoon; everything else goes out on the next one.',
                    ['h2', 'Can you grind it for me?'],
                    'Yes, and you can choose the brew method at checkout. Ground coffee stales considerably faster than whole beans, so order smaller and more often if you go this way.',
                    ['h2', 'Do you offer subscriptions?'],
                    'Weekly, fortnightly or monthly, and you can skip or pause any delivery up to 24 hours before it is roasted. There is no minimum term.',
                    ['h2', 'Something arrived damaged. What now?'],
                    'Send a photo to the contact form within seven days and we will replace it on the next roast day. We do not ask for the damaged item back.',
                    ['h2', 'Do you ship internationally?'],
                    'Within the EU and to the UK, US and Canada. Duties are not included and are collected by the carrier on delivery.',
                ]),
            ],
            [
                'slug' => 'shipping-and-returns',
                'title' => 'Shipping and returns',
                'status' => 'published',
                'show_in_menu' => false,
                'sort_order' => 3,
                'meta_description' => 'Delivery timescales, costs, and how returns work on coffee and on equipment.',
                'content' => self::body([
                    ['h2', 'Delivery'],
                    ['ul', [
                        'Standard, 2-3 working days - 4.50, free over 40.',
                        'Express, next working day if ordered before 10am on a roast day - 8.00.',
                        'Collection from the roastery - free, Monday to Friday, 9am to 4pm.',
                    ]],
                    ['h2', 'Returns on equipment'],
                    'Thirty days, unused and in its original packaging, refunded to the original payment method within five working days of arriving back with us.',
                    ['h2', 'Returns on coffee'],
                    'Food safety rules mean we cannot resell returned coffee, so we do not ask you to send it back. If a bag is not right - stale, damaged, or simply not what you expected - tell us and we will replace or refund it. This is not a loophole we have ever had to worry about.',
                    ['h2', 'Lost parcels'],
                    'If tracking has not moved for five working days, contact us and we will re-send rather than make you wait out the carrier investigation.',
                ]),
            ],
            [
                'slug' => 'wholesale',
                'title' => 'Wholesale',
                'status' => 'published',
                'show_in_menu' => true,
                'sort_order' => 4,
                'palette' => 'sage',
                'meta_description' => 'Trade pricing, training and equipment support for cafes and offices.',
                'content' => self::body([
                    'We supply around thirty cafes, a handful of restaurants and a growing number of offices that got tired of instant.',
                    ['h2', 'What comes with an account'],
                    ['ul', [
                        'Trade pricing from the first kilo, with no minimum order.',
                        'Free dial-in visit when you take on a new coffee.',
                        'Loan grinders for accounts over 10kg a week.',
                        'Next-day delivery on anything ordered before 2pm.',
                    ]],
                    ['h2', 'Getting started'],
                    'Tell us what you are serving now and what is wrong with it. We will send samples of two or three coffees we think fit, and come and dial them in on your machine before you commit to anything.',
                ]),
            ],
            [
                'slug' => 'wholesale-pricing',
                'title' => 'Wholesale pricing',
                'parent' => 'wholesale',
                'status' => 'published',
                'show_in_menu' => false,
                'sort_order' => 1,
                'meta_description' => 'Trade price bands by weekly volume.',
                'content' => self::body([
                    'Prices are per kilogram, excluding tax, and are set by your average weekly volume over the preceding month. Nobody is asked to commit to a band in advance.',
                    ['ul', [
                        'Under 5kg a week - list price less 25%.',
                        '5kg to 20kg - list price less 32%.',
                        'Over 20kg - list price less 38%, plus a loan grinder.',
                    ]],
                    ['h2', 'Single origins'],
                    'Priced per lot, because what we pay varies per lot. Current sheet is sent monthly with your invoice, and the price you are quoted holds until the lot runs out.',
                ]),
            ],
            [
                'slug' => 'wholesale-faq',
                'title' => 'Wholesale FAQ',
                'parent' => 'wholesale',
                'status' => 'published',
                'show_in_menu' => false,
                'sort_order' => 2,
                'meta_description' => 'Common questions from cafes opening a trade account.',
                'content' => self::body([
                    ['h2', 'Is there a minimum order?'],
                    'No. Several of our accounts take 2kg a week and have done for years.',
                    ['h2', 'What are the payment terms?'],
                    'Thirty days from invoice once you have placed three orders. Before that, payment on delivery.',
                    ['h2', 'Can we get our own blend?'],
                    'Over about 15kg a week, yes. It takes three or four sessions to get right and we do not charge for the development.',
                    ['h2', 'Do you train staff?'],
                    'A half-day session at the roastery, free with an account, up to four people. Refreshers whenever your team turns over.',
                ]),
            ],
            [
                'slug' => 'visit-the-roastery',
                'title' => 'Visit the roastery',
                'status' => 'published',
                'show_in_menu' => false,
                'sort_order' => 5,
                'palette' => 'amber',
                'meta_description' => 'Opening hours, the cupping table and how to find us.',
                'content' => self::body([
                    'The roastery is open to anyone who wants to come and look at it, which is more interesting than most people expect and noisier than all of them expect.',
                    ['h2', 'Opening hours'],
                    ['ul', [
                        'Monday to Friday, 9am to 4pm - collections and the counter.',
                        'Saturday, 10am to 2pm - counter only.',
                        'Public cupping, first Thursday of the month at 6pm - free, twelve places, book ahead.',
                    ]],
                    ['h2', 'Finding us'],
                    'Unit 14 on the industrial estate behind the station. The entrance is around the back, past the loading bay. If you can smell it, you are close.',
                ]),
            ],
            [
                'slug' => 'press-kit',
                'title' => 'Press kit',
                'status' => 'draft',
                'show_in_menu' => false,
                'sort_order' => 6,
                'meta_description' => 'Logos, photography and boilerplate for press use.',
                'content' => self::body([
                    'Draft - waiting on the new photography before this goes live.',
                    ['h2', 'Boilerplate'],
                    'A small-batch coffee roastery working directly with eleven producers across four countries, publishing the price paid for every lot.',
                    ['h2', 'Assets'],
                    ['ul', [
                        'Logo, light and dark, SVG and PNG - TODO, waiting on the redraw.',
                        'Roastery photography - TODO, shoot booked.',
                        'Founder headshots - done.',
                    ]],
                ]),
            ],
        ];
    }
}
