<?php

namespace Database\Seeders\Demo;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo blog content: categories, tags, a dozen posts and a comment thread.
 *
 * The posts deliberately cover every state the blog can be in - published,
 * featured, scheduled for the future, draft - because the point of demo data
 * is to exercise the listing filters, not just fill a page.
 */
class DemoBlogSeeder extends Seeder
{
    use BuildsHtmlBody;

    public function __construct(private DemoMediaFactory $media) {}

    /** @return array<int, array<string, mixed>> */
    public static function categories(): array
    {
        return [
            ['slug' => 'guides', 'name' => 'Guides', 'description' => 'Step-by-step walkthroughs for getting more out of your kit.', 'icon' => 'book-open', 'sort_order' => 1],
            ['slug' => 'recipes', 'name' => 'Recipes', 'description' => 'Ratios, timings and variations worth trying this week.', 'icon' => 'beaker', 'sort_order' => 2],
            ['slug' => 'roastery', 'name' => 'From the roastery', 'description' => 'What we are roasting, and the people we buy it from.', 'icon' => 'fire', 'sort_order' => 3],
        ];
    }

    /** @return array<string, string> slug => name */
    public static function tags(): array
    {
        return [
            'espresso' => 'Espresso',
            'filter' => 'Filter',
            'how-to' => 'How-to',
            'beginners' => 'Beginners',
            'equipment' => 'Equipment',
            'maintenance' => 'Maintenance',
            'sustainability' => 'Sustainability',
            'gift-guide' => 'Gift guide',
            'sourcing' => 'Sourcing',
            'seasonal' => 'Seasonal',
        ];
    }

    /**
     * The demo authors. Sign-in is deliberately impossible: these accounts get
     * an unusable password hash, so they can own posts without becoming a way
     * into the admin on a site someone forgot to clean up.
     *
     * @return array<int, array<string, string>>
     */
    public static function authors(): array
    {
        return [
            ['email' => 'ava.mercer@demo.invalid', 'name' => 'Ava Mercer', 'role' => User::ROLE_EDITOR],
            ['email' => 'theo.lang@demo.invalid', 'name' => 'Theo Lang', 'role' => User::ROLE_EDITOR],
        ];
    }

    /** @return array<int, string> */
    public static function postSlugs(): array
    {
        return array_column(self::posts(), 'slug');
    }

    public function run(): void
    {
        $categories = $this->syncCategories();
        $tags = $this->syncTags();
        $authors = $this->syncAuthors();

        foreach (self::posts() as $data) {
            $post = Post::withTrashed()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'content' => $data['content'],
                    'category_id' => $categories[$data['category']] ?? null,
                    'author_id' => $authors[$data['author']] ?? null,
                    'status' => $data['status'],
                    'published_at' => $data['published_at'],
                    'is_featured' => $data['featured'] ?? false,
                    'allow_comments' => $data['allow_comments'] ?? true,
                    'featured_image' => $this->media->image('post-'.$data['slug'], $data['title'], $data['palette']),
                    'meta_title' => $data['title'],
                    'meta_description' => $data['excerpt'],
                    'deleted_at' => null,
                ]
            );

            $post->tags()->sync(array_values(array_intersect_key($tags, array_flip($data['tags']))));

            // Not fillable - a view counter has no business being mass
            // assignable - but a demo with every post on zero views looks dead.
            $post->forceFill(['views' => $data['views']])->saveQuietly();
        }

        $this->comments();
    }

    /** @return array<string, int> slug => id */
    private function syncCategories(): array
    {
        $ids = [];

        foreach (self::categories() as $data) {
            $ids[$data['slug']] = Category::updateOrCreate(
                ['type' => Category::TYPE_BLOG, 'slug' => $data['slug']],
                $data + ['is_active' => true, 'show_in_menu' => true]
            )->id;
        }

        // 'news' ships with the installer; the demo posts just borrow it.
        $ids['news'] = Category::where('type', Category::TYPE_BLOG)->where('slug', 'news')->value('id');

        return $ids;
    }

    /** @return array<string, int> slug => id */
    private function syncTags(): array
    {
        $ids = [];

        foreach (self::tags() as $slug => $name) {
            $ids[$slug] = Tag::updateOrCreate(['slug' => $slug], ['name' => $name])->id;
        }

        return $ids;
    }

    /** @return array<string, int> email => id */
    private function syncAuthors(): array
    {
        $ids = [];

        foreach (self::authors() as $author) {
            $user = User::withTrashed()->firstOrNew(['email' => $author['email']]);

            $user->fill([
                'name' => $author['name'],
                'role' => $author['role'],
                'status' => 'active',
            ]);

            // Hashed from 64 random bytes that are then thrown away, so the
            // account is a byline and not a way in.
            $user->password = Hash::make(Str::random(64));
            $user->email_verified_at = now();
            $user->deleted_at = null;
            $user->save();

            $ids[$author['email']] = $user->id;
        }

        return $ids;
    }

    /**
     * A thread on the busiest post plus a couple of loose comments, covering
     * the approved / pending / spam states the moderation screen filters on.
     */
    private function comments(): void
    {
        $post = Post::where('slug', 'dial-in-espresso-in-five-minutes')->first();

        if (! $post) {
            return;
        }

        $root = Comment::updateOrCreate(
            ['post_id' => $post->id, 'author_email' => 'priya@demo.invalid', 'parent_id' => null],
            [
                'author_name' => 'Priya N.',
                'body' => 'This finally explained why my shots were running fast. Went from 14 seconds to 27 by grinding two notches finer. Thank you!',
                'status' => 'approved',
                'ip_address' => '203.0.113.14',
            ]
        );

        Comment::updateOrCreate(
            ['post_id' => $post->id, 'author_email' => 'ava.mercer@demo.invalid', 'parent_id' => $root->id],
            [
                'author_name' => 'Ava Mercer',
                'body' => 'Glad it helped, Priya. If the shot starts choking as the beans age, come back a notch - fresher coffee needs a coarser setting than most people expect.',
                'status' => 'approved',
                'ip_address' => '203.0.113.2',
            ]
        );

        Comment::updateOrCreate(
            ['post_id' => $post->id, 'author_email' => 'marcus@demo.invalid', 'parent_id' => null],
            [
                'author_name' => 'Marcus D.',
                'body' => 'Any chance of a follow-up on milk texturing? My latte art is still more of a blob than a heart.',
                'status' => 'pending',
                'ip_address' => '198.51.100.77',
            ]
        );

        Comment::updateOrCreate(
            ['post_id' => $post->id, 'author_email' => 'offers@demo.invalid', 'parent_id' => null],
            [
                'author_name' => 'Best Deals Now',
                'body' => 'CHEAP WATCHES AND HANDBAGS, click my profile for 90% off today only!!!',
                'status' => 'spam',
                'ip_address' => '198.51.100.201',
            ]
        );

        $guide = Post::where('slug', 'pour-over-ratios-explained')->first();

        if ($guide) {
            Comment::updateOrCreate(
                ['post_id' => $guide->id, 'author_email' => 'jonas@demo.invalid', 'parent_id' => null],
                [
                    'author_name' => 'Jonas W.',
                    'body' => 'Switched from 1:15 to 1:16 after reading this and the cup is noticeably sweeter. Worth the 30 seconds of extra brew time.',
                    'status' => 'approved',
                    'ip_address' => '203.0.113.51',
                ]
            );
        }
    }

    /**
     * The post library. Dates are relative to the run so the blog archive
     * always looks current, however long after install the demo is loaded.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function posts(): array
    {
        $ava = 'ava.mercer@demo.invalid';
        $theo = 'theo.lang@demo.invalid';

        return [
            [
                'slug' => 'dial-in-espresso-in-five-minutes',
                'title' => 'How to dial in espresso in five minutes',
                'excerpt' => 'Three variables, one at a time, in the order that actually converges. No scales-and-spreadsheets ritual required.',
                'category' => 'guides',
                'author' => $ava,
                'status' => 'published',
                'published_at' => now()->subDays(3)->setTime(9, 15),
                'featured' => true,
                'views' => 4821,
                'palette' => 'espresso',
                'tags' => ['espresso', 'how-to', 'beginners'],
                'content' => self::body([
                    'Dialling in has a reputation for being fussy, and it earns that reputation mostly because people change three things at once and then cannot tell which one helped. Change one variable at a time, in a fixed order, and the whole thing takes about five minutes and three shots.',
                    ['h2', 'Start from a known dose'],
                    'Weigh your basket dose and keep it fixed for the whole session. An 18g basket takes 18g, not "about a scoop". Every adjustment below assumes the dose is not moving.',
                    ['h2', 'Then chase the time, not the taste'],
                    'Pull a shot and time it from the moment you hit the button to the moment you stop. You are aiming for roughly twice the dose in liquid weight - 36g out of 18g in - somewhere between 25 and 32 seconds.',
                    ['ul', [
                        'Too fast and thin? Grind finer.',
                        'Too slow, dripping, or bitter? Grind coarser.',
                        'Wildly inconsistent between shots? The problem is your distribution, not the grinder.',
                    ]],
                    ['h2', 'Taste last'],
                    'Only once the timing is stable is tasting worth anything. Sour means under-extracted: go finer or a touch hotter. Harsh and drying means over-extracted: go coarser or shorten the shot. Two adjustments is normally enough.',
                    ['blockquote', 'Fresh coffee needs a coarser grind than most people expect. If a bag that ran perfectly last week suddenly chokes the machine, it is the coffee degassing, not you.'],
                    'Write down the setting that worked, on the bag, in pencil. Next time you open the same coffee you start from the answer instead of the beginning.',
                ]),
            ],
            [
                'slug' => 'pour-over-ratios-explained',
                'title' => 'Pour-over ratios, explained properly',
                'excerpt' => 'Why 1:16 is the default, when to move off it, and what actually changes in the cup when you do.',
                'category' => 'guides',
                'author' => $theo,
                'status' => 'published',
                'published_at' => now()->subDays(9)->setTime(11, 0),
                'featured' => true,
                'views' => 3106,
                'palette' => 'sage',
                'tags' => ['filter', 'how-to', 'beginners'],
                'content' => self::body([
                    'A brew ratio is just coffee weight against water weight. Written as 1:16, it means one gram of coffee for every sixteen grams of water - 30g of coffee into 480g of water, and so on. That is the entire concept.',
                    ['h2', 'Why 1:16 is the usual starting point'],
                    'It sits in the middle of the range where a medium roast tastes balanced without being either syrupy or watery. It is a default, not a law, and it is the number to return to whenever you have changed too many things and lost the thread.',
                    ['h2', 'Moving the ratio on purpose'],
                    ['ul', [
                        '1:14 - heavier body, lower clarity. Good for darker roasts and for drinking with milk.',
                        '1:16 - the balanced middle. Start here with anything new.',
                        '1:18 - light, tea-like, more aromatic. Suits delicate washed coffees and light roasts.',
                    ]],
                    'What a ratio cannot fix is grind size. If the brew runs through in ninety seconds, adding coffee will not save it; the water is still leaving before it has taken anything with it. Fix the drawdown time first, then adjust the ratio for body.',
                    ['h2', 'A worked example'],
                    'For a two-cup V60: 22g of coffee, 350g of water at 94C, poured in four additions about 45 seconds apart, finishing the drawdown around three minutes. If it finishes much earlier, go finer; much later, coarser.',
                ]),
            ],
            [
                'slug' => 'five-cold-brew-recipes-for-summer',
                'title' => 'Five cold brew recipes for a hot week',
                'excerpt' => 'One base recipe and four variations, including the salted one that sounds wrong and is not.',
                'category' => 'recipes',
                'author' => $ava,
                'status' => 'published',
                'published_at' => now()->subDays(16)->setTime(8, 30),
                'views' => 2287,
                'palette' => 'teal',
                'tags' => ['filter', 'seasonal', 'how-to'],
                'content' => self::body([
                    'Cold brew forgives almost everything except stale coffee and impatience. Get those two right and the rest is variation.',
                    ['h2', 'The base'],
                    'Coarse grind, 1:8 by weight for a concentrate, sixteen hours in the fridge, then filtered and diluted 1:1 with water or milk to serve. One batch keeps about a week refrigerated.',
                    ['h2', 'Four variations worth the fridge space'],
                    ['ul', [
                        'Salted: a pinch of fine salt per litre of concentrate. It does not taste salty - it flattens the bitterness and the sweetness comes forward.',
                        'Orange peel: two wide strips of peel in the steep, removed before filtering.',
                        'Cascara fizz: concentrate topped with sparkling water and a cascara syrup.',
                        'Cocoa nib: 20g of nibs added to the grounds for a chocolatey, heavier cup.',
                    ]],
                    'Filter through a paper cone rather than a mesh basket if you want the concentrate clean enough to drink black over ice; the mesh leaves fines that turn muddy by day three.',
                ]),
            ],
            [
                'slug' => 'meet-the-growers-kirinyaga',
                'title' => 'Meet the growers behind our Kirinyaga lot',
                'excerpt' => 'A washing station, 340 smallholders and the reason this coffee tastes like blackcurrant.',
                'category' => 'roastery',
                'author' => $theo,
                'status' => 'published',
                'published_at' => now()->subDays(24)->setTime(15, 45),
                'views' => 1542,
                'palette' => 'clay',
                'tags' => ['sourcing', 'sustainability'],
                'content' => self::body([
                    'The Kirinyaga lot we have been buying for four seasons comes from a washing station that serves around 340 smallholder farms, most of them under a hectare. Cherry is delivered the same day it is picked, floated to remove the underripe fruit, and fermented overnight before washing.',
                    ['h2', 'Why the processing matters here'],
                    'That overnight ferment and the long soak afterwards are what give the cup its clarity. The blackcurrant note everybody comments on is not an additive or a roasting trick; it is what SL-28 does when the fruit is picked ripe and the water is clean.',
                    ['h2', 'What we pay'],
                    'We publish the farmgate price we paid alongside the C-market price for the same week, because "direct trade" means nothing on its own. This season we paid roughly 2.4 times the market rate, and the premium went to the station rather than through an exporter.',
                    ['blockquote', 'Transparency is a number, not an adjective. If a roaster will not tell you what they paid, assume it was the going rate.'],
                ]),
            ],
            [
                'slug' => 'descale-your-kettle-without-ruining-it',
                'title' => 'Descaling your kettle without ruining it',
                'excerpt' => 'Citric acid, the right dilution, and the three parts people forget to rinse.',
                'category' => 'guides',
                'author' => $ava,
                'status' => 'published',
                'published_at' => now()->subDays(31)->setTime(10, 10),
                'views' => 1893,
                'palette' => 'slate',
                'tags' => ['maintenance', 'equipment', 'how-to'],
                'content' => self::body([
                    'Scale is calcium carbonate, and it comes off with any weak acid. The mistake is reaching for something far too strong and taking the finish off with it.',
                    ['h2', 'The dilution'],
                    'Ten grams of food-grade citric acid per litre of water. Fill, bring to 80C, leave for twenty minutes, pour away. Vinegar works too, but the smell lingers for days and the result is no better.',
                    ['h2', 'The parts everyone forgets'],
                    ['ul', [
                        'The spout interior, where scale collects into a ring that flakes into the cup.',
                        'The temperature probe, if your kettle has one - scale on the probe makes it read low.',
                        'The lid seal, which holds acid and will perish if you leave it there.',
                    ]],
                    'Rinse three times with fresh water. If the fourth boil still smells of anything, rinse again; nothing in this process is improved by hurrying it.',
                    ['h2', 'How often'],
                    'In hard-water areas, monthly. Soft water, twice a year. If you can see it, you left it too long.',
                ]),
            ],
            [
                'slug' => 'beginners-guide-to-grinders',
                'title' => "A beginner's buying guide to grinders",
                'excerpt' => 'Burr size, alignment and stepless adjustment - which of these you actually need, and which is marketing.',
                'category' => 'guides',
                'author' => $theo,
                'status' => 'published',
                'published_at' => now()->subDays(38)->setTime(13, 20),
                'featured' => true,
                'views' => 5417,
                'palette' => 'amber',
                'tags' => ['equipment', 'beginners', 'espresso'],
                'content' => self::body([
                    'If you are choosing between spending more on a machine or more on a grinder, spend it on the grinder. A modest machine with an excellent grinder makes better coffee than the reverse, and it is not close.',
                    ['h2', 'Burrs beat blades, and that is the whole of that argument'],
                    'A blade grinder chops; a burr grinder mills to a size. Chopped coffee contains both dust and boulders, and they extract at wildly different rates, so the cup is simultaneously bitter and sour. No brewing technique recovers from that.',
                    ['h2', 'What matters, in order'],
                    ['ul', [
                        'Burr alignment - the single biggest factor in consistency, and the one nobody puts on the box.',
                        'Adjustment fineness - stepped is fine for filter; espresso wants stepless or very fine steps.',
                        'Burr size - matters for speed and heat, much less for quality at home volumes.',
                        'Retention - matters if you switch coffees daily, otherwise it is a rounding error.',
                    ]],
                    ['h2', 'Hand or electric?'],
                    'A good hand grinder at 120 currency units will out-grind a bad electric at twice the price. The trade is effort: about 40 seconds of cranking for an espresso dose. If you make one or two drinks a day, that is nothing. If you make six, buy the electric.',
                ]),
            ],
            [
                'slug' => 'holiday-gift-guide-twelve-picks',
                'title' => 'Gift guide: twelve picks under 60',
                'excerpt' => 'For the person who has a machine but no grinder, and the person who has both but no patience.',
                'category' => 'news',
                'author' => $ava,
                'status' => 'published',
                'published_at' => now()->subDays(45)->setTime(9, 0),
                'views' => 2760,
                'palette' => 'plum',
                'tags' => ['gift-guide', 'equipment', 'seasonal'],
                'content' => self::body([
                    'Everything here is something we own and keep using, which rules out most of what usually turns up on these lists.',
                    ['h2', 'For someone starting out'],
                    'A ceramic dripper and a hundred filters costs less than a month of takeaway coffee and changes what they drink every morning. Pair it with a bag of something forgiving - a washed Colombian, not an experimental natural.',
                    ['h2', 'For someone with the gear already'],
                    ['ul', [
                        'A tasting journal, for the person who keeps forgetting which setting worked.',
                        'A set of cupping spoons, for the person who has started saying "notes of".',
                        'An insulated tumbler, for the person whose coffee goes cold on their desk every single day.',
                    ]],
                    ['h2', 'For someone impossible to buy for'],
                    'A subscription. Three months, one bag a month, no decisions required from anybody. It is the only gift on this list that cannot go wrong.',
                ]),
            ],
            [
                'slug' => 'why-we-roast-in-small-batches',
                'title' => 'Why we roast in small batches',
                'excerpt' => 'Twelve kilos at a time is slower and costs more per bag. Here is what it buys.',
                'category' => 'roastery',
                'author' => $theo,
                'status' => 'published',
                'published_at' => now()->subDays(52)->setTime(16, 30),
                'views' => 1174,
                'palette' => 'espresso',
                'tags' => ['sourcing', 'sustainability'],
                'content' => self::body([
                    'Our drum holds twelve kilos and we rarely fill it past ten. A larger machine would cut our roasting week roughly in half, and we have decided twice now not to buy one.',
                    ['h2', 'Control over the last thirty seconds'],
                    'Most of what separates a good roast from a flat one happens after first crack, in a window of about ninety seconds. On a small drum that window is easy to steer. On a large one the thermal mass makes the machine, rather than the roaster, the one deciding.',
                    ['h2', 'Roasting to order, honestly'],
                    'Small batches mean we can roast on Tuesday what ships on Wednesday. Nothing sits in a warehouse. The date on the bag is a roast date, not a best-before invented twelve months out.',
                    ['h2', 'What it costs'],
                    'More labour per kilo, and more electricity. It is on the order of a fifteen percent premium, and it is the reason our bags are not the cheapest on the shelf. We think it is visible in the cup, which is the only defence that matters.',
                ]),
            ],
            [
                'slug' => 'packaging-changes-this-year',
                'title' => 'What changed in our packaging this year',
                'excerpt' => 'Out with the multi-layer laminate, in with something a kerbside bin will actually take.',
                'category' => 'news',
                'author' => $ava,
                'status' => 'published',
                'published_at' => now()->subDays(67)->setTime(12, 0),
                'views' => 908,
                'palette' => 'sage',
                'tags' => ['sustainability', 'seasonal'],
                'content' => self::body([
                    'Our old bags were a foil laminate: excellent at keeping coffee fresh, impossible to recycle anywhere in the country. We have spent most of a year replacing them.',
                    ['h2', 'The new bag'],
                    'A mono-material polyethylene with a degassing valve of the same material, so the whole thing goes into soft-plastic collection without being taken apart. Shelf life came down from twelve months to about nine, which for coffee we want drunk within six weeks is not a real loss.',
                    ['h2', 'What we did not change'],
                    'The valve stays. Without it, freshly roasted coffee either inflates the bag until it splits or has to be rested for days before packing, and resting it in the open is worse for the coffee than any packaging decision.',
                    ['h2', 'The honest part'],
                    'Soft-plastic collection is not available everywhere, and where it is, the recovery rate is not what the label implies. This is an improvement, not a solution.',
                ]),
            ],
            [
                'slug' => 'annual-sustainability-report',
                'title' => 'Our annual sustainability report',
                'excerpt' => 'Emissions, farmgate prices and the two targets we missed.',
                'category' => 'news',
                'author' => $theo,
                'status' => 'published',
                'published_at' => now()->subDays(88)->setTime(10, 0),
                'views' => 1330,
                'palette' => 'indigo',
                'tags' => ['sustainability', 'sourcing'],
                'content' => self::body([
                    'This is the fourth year we have published one of these, and the second in which we have had to open with a target we missed.',
                    ['h2', 'Shipping emissions'],
                    'Sea freight for green coffee came down eleven percent per kilo, mostly by consolidating shipments rather than by anything clever. Outbound parcel shipping went up, because we sold more. Net, we are roughly flat.',
                    ['h2', 'Farmgate prices'],
                    'Average price paid was 2.1 times the C-market for the equivalent week, across eleven lots. The full table, lot by lot, is in the downloadable version.',
                    ['h2', 'What we missed'],
                    ['ul', [
                        'Compostable shipping mailers: still not durable enough in wet weather. Pushed to next year.',
                        'Roastery solar: planning approval took nine months. Install is booked, a year later than promised.',
                    ]],
                ]),
            ],
            [
                'slug' => 'winter-blend-coming-soon',
                'title' => 'Coming soon: the winter blend',
                'excerpt' => 'Three components, one of them new, landing at the end of the month.',
                'category' => 'news',
                'author' => $ava,
                // Published, but dated ahead: the public scope hides it until
                // the date passes, which is the scheduling case worth testing.
                'status' => 'published',
                'published_at' => now()->addDays(12)->setTime(9, 0),
                'views' => 0,
                'palette' => 'plum',
                'tags' => ['seasonal', 'sourcing'],
                'content' => self::body([
                    'The winter blend comes back at the end of the month, with one component swapped out.',
                    'As before it is built around a washed Colombian for structure, with a natural Ethiopian for the fruit. The change is in the third component: instead of the Brazilian we have used for two years, a honey-processed Costa Rican that brings more caramel and noticeably less of the nutty edge some of you told us you had had enough of.',
                    ['h2', 'How it brews'],
                    'It is aimed at espresso with milk, and at 1:2 in 28 seconds it is unambiguous about that. As filter it is drinkable but blunt; if filter is your thing, stay with the single origins.',
                ]),
            ],
            [
                'slug' => 'notes-on-water-chemistry',
                'title' => 'Notes on water chemistry',
                'excerpt' => 'A half-finished draft about hardness, alkalinity and why your coffee tastes different on holiday.',
                'category' => 'guides',
                'author' => $theo,
                'status' => 'draft',
                'published_at' => null,
                'views' => 0,
                'palette' => 'teal',
                'tags' => ['filter', 'maintenance'],
                'content' => self::body([
                    'Draft - not finished, do not publish. Need to check the alkalinity figures against the SCA paper before this goes anywhere.',
                    ['h2', 'The two numbers'],
                    'General hardness is the magnesium and calcium available to bind flavour compounds. Alkalinity is the buffering capacity, which decides how much of the coffee acidity survives into the cup. High alkalinity flattens everything.',
                    ['h2', 'TODO'],
                    ['ul', [
                        'Add the target range table.',
                        'Recipe for the mineral concentrate, with the dilution for a 5-litre jug.',
                        'Photograph the scale in the test kettle before descaling it.',
                    ]],
                ]),
            ],
        ];
    }
}
