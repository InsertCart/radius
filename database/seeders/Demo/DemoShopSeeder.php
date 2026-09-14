<?php

namespace Database\Seeders\Demo;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

/**
 * Demo shop catalogue: a two-level category tree and fifteen products chosen
 * to cover every case the shop has to render.
 *
 * Simple, variable and digital types are all present, along with a product on
 * sale inside a date window, one out of stock, one on backorder, one with
 * stock management off and one still in draft. A catalogue of fifteen
 * identical in-stock products would prove nothing.
 *
 * Prices are integers in minor units - cents - because that is how the shop
 * stores money. 1850 is 18.50.
 */
class DemoShopSeeder extends Seeder
{
    use BuildsHtmlBody;

    public function __construct(private DemoMediaFactory $media) {}

    /** @return array<int, array<string, mixed>> */
    public static function categories(): array
    {
        return [
            ['slug' => 'coffee', 'name' => 'Coffee', 'description' => 'Roasted to order, twice a week.', 'icon' => 'beaker', 'sort_order' => 1],
            ['slug' => 'single-origin', 'name' => 'Single origin', 'parent' => 'coffee', 'description' => 'One farm, one lot, one harvest.', 'sort_order' => 2],
            ['slug' => 'blends', 'name' => 'Blends', 'parent' => 'coffee', 'description' => 'Built for consistency, season to season.', 'sort_order' => 3],
            ['slug' => 'decaf', 'name' => 'Decaf', 'parent' => 'coffee', 'description' => 'Sugarcane process, no solvents.', 'sort_order' => 4],
            ['slug' => 'equipment', 'name' => 'Equipment', 'description' => 'Kit we use ourselves and would replace tomorrow.', 'icon' => 'cog', 'sort_order' => 5],
            ['slug' => 'brewers', 'name' => 'Brewers', 'parent' => 'equipment', 'description' => 'Drippers, kettles and servers.', 'sort_order' => 6],
            ['slug' => 'grinders', 'name' => 'Grinders', 'parent' => 'equipment', 'description' => 'Hand and electric, burrs only.', 'sort_order' => 7],
            ['slug' => 'accessories', 'name' => 'Accessories', 'parent' => 'equipment', 'description' => 'Filters, scales and the small things.', 'sort_order' => 8],
            ['slug' => 'gifts', 'name' => 'Gifts', 'description' => 'Bundles and things that arrive ready to give.', 'icon' => 'gift', 'sort_order' => 9],
        ];
    }

    /** @return array<int, string> */
    public static function productSlugs(): array
    {
        return array_column(self::products(), 'slug');
    }

    /** @return array<int, string> */
    public static function categorySlugs(): array
    {
        return array_column(self::categories(), 'slug');
    }

    public function run(): void
    {
        $categories = $this->syncCategories();

        foreach (self::products() as $data) {
            $product = Product::withTrashed()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'sku' => $data['sku'],
                    'short_description' => $data['short'],
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'sale_price' => $data['sale_price'] ?? null,
                    'sale_starts_at' => isset($data['sale_price']) ? now()->subDays(4) : null,
                    'sale_ends_at' => isset($data['sale_price']) ? now()->addDays(17) : null,
                    'cost_price' => (int) round($data['price'] * 0.55),
                    'type' => $data['type'] ?? 'simple',
                    'manage_stock' => $data['manage_stock'] ?? true,
                    'stock' => $data['stock'] ?? 0,
                    'allow_backorder' => $data['backorder'] ?? false,
                    'weight' => $data['weight'] ?? null,
                    'dimensions' => $data['dimensions'] ?? null,
                    'requires_shipping' => ($data['type'] ?? 'simple') !== 'digital',
                    'status' => $data['status'] ?? 'published',
                    'is_featured' => $data['featured'] ?? false,
                    'sort_order' => $data['sort_order'] ?? 0,
                    'featured_image' => $this->media->image('product-'.$data['slug'], $data['name'], $data['palette']),
                    'meta_title' => $data['name'],
                    'meta_description' => $data['short'],
                    'deleted_at' => null,
                ]
            );

            $product->categories()->sync(array_values(array_filter(
                array_map(fn (string $slug) => $categories[$slug] ?? null, $data['categories'])
            )));

            $this->variants($product, $data['variants'] ?? []);

            // Sales and view counts are not mass assignable; a shop where
            // every product shows zero sold tells you nothing about sorting.
            $product->forceFill([
                'views' => $data['views'] ?? 0,
                'sold_count' => $data['sold'] ?? 0,
            ])->saveQuietly();
        }

        $this->reviews();
    }

    /** @return array<string, int> slug => id */
    private function syncCategories(): array
    {
        $ids = [];

        foreach (self::categories() as $data) {
            $ids[$data['slug']] = Category::updateOrCreate(
                ['type' => Category::TYPE_SHOP, 'slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'icon' => $data['icon'] ?? null,
                    'sort_order' => $data['sort_order'],
                    'is_active' => true,
                    'show_in_menu' => empty($data['parent']),
                ]
            )->id;
        }

        foreach (self::categories() as $data) {
            if (! empty($data['parent'])) {
                Category::whereKey($ids[$data['slug']])->update(['parent_id' => $ids[$data['parent']]]);
            }
        }

        return $ids;
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     */
    private function variants(Product $product, array $variants): void
    {
        if ($variants === []) {
            return;
        }

        foreach ($variants as $index => $variant) {
            ProductVariant::updateOrCreate(
                ['product_id' => $product->id, 'sku' => $variant['sku']],
                [
                    'name' => $variant['name'],
                    'options' => $variant['options'],
                    'price' => $variant['price'] ?? null,
                    'sale_price' => $variant['sale_price'] ?? null,
                    'stock' => $variant['stock'],
                    'is_active' => $variant['is_active'] ?? true,
                    'sort_order' => $index,
                ]
            );
        }
    }

    /**
     * Reviews across a handful of products, in every moderation state, then a
     * rating refresh so the cached aggregate on each product is correct.
     */
    private function reviews(): void
    {
        $reviews = [
            ['product' => 'kirinyaga-aa', 'author' => 'Hannah R.', 'rating' => 5, 'status' => 'approved', 'verified' => true,
                'title' => 'The blackcurrant thing is real', 'body' => 'I assumed the tasting notes were creative writing. They are not. Brewed as filter at 1:16 it is unmistakable, and it held up for the whole bag.'],
            ['product' => 'kirinyaga-aa', 'author' => 'Daniel O.', 'rating' => 4, 'status' => 'approved', 'verified' => true,
                'title' => 'Excellent, if you have a grinder', 'body' => 'Worth the money as filter. As espresso I could not get it to behave in my machine, but that is probably me rather than the coffee.'],
            ['product' => 'kirinyaga-aa', 'author' => 'Sam T.', 'rating' => 5, 'status' => 'pending', 'verified' => false,
                'title' => 'Second bag already', 'body' => 'Ordered Tuesday, arrived Thursday, roasted the day it shipped. No complaints at all.'],
            ['product' => 'morning-blend', 'author' => 'Fiona M.', 'rating' => 5, 'status' => 'approved', 'verified' => true,
                'title' => 'The one I keep reordering', 'body' => 'Forgiving in every brewer I own and genuinely good with milk. This is the bag that lives in the cupboard permanently now.'],
            ['product' => 'morning-blend', 'author' => 'Greg P.', 'rating' => 3, 'status' => 'approved', 'verified' => false,
                'title' => 'Good, not exciting', 'body' => 'Perfectly pleasant and very consistent. If you want something to think about, buy a single origin instead.'],
            ['product' => 'hand-grinder-pro', 'author' => 'Nadia K.', 'rating' => 5, 'status' => 'approved', 'verified' => true,
                'title' => 'Replaced an electric with this', 'body' => 'Forty seconds of cranking for an espresso dose and the grind is more even than the electric it replaced. The electric is in a cupboard now.'],
            ['product' => 'hand-grinder-pro', 'author' => 'Owen B.', 'rating' => 4, 'status' => 'approved', 'verified' => true,
                'title' => 'Great, but it is work', 'body' => 'No complaints about the grind. Two drinks a day is fine; if you are making six, get the electric and save your wrist.'],
            ['product' => 'pour-over-kettle', 'author' => 'Leah C.', 'rating' => 4, 'status' => 'approved', 'verified' => true,
                'title' => 'Pours beautifully', 'body' => 'The flow control is the whole point and it delivers. Half a star off because the handle gets warmer than I would like near the end of a boil.'],
            ['product' => 'ceramic-dripper', 'author' => 'Ravi S.', 'rating' => 5, 'status' => 'approved', 'verified' => true,
                'title' => 'Does exactly what it should', 'body' => 'It is a cone. It is a very good cone. Preheat it properly and it holds temperature far better than the plastic one it replaced.'],
            ['product' => 'insulated-tumbler', 'author' => 'Anon', 'rating' => 1, 'status' => 'spam', 'verified' => false,
                'title' => 'VISIT MY SITE FOR BETTER PRICES', 'body' => 'Buy discount electronics from my shop, best prices guaranteed, click here!!!'],
            ['product' => 'insulated-tumbler', 'author' => 'Marta L.', 'rating' => 4, 'status' => 'approved', 'verified' => true,
                'title' => 'Still hot at lunchtime', 'body' => 'Filled at eight, still properly hot at one. The lid is fiddly to clean but it does not leak in a bag, which is the part I cared about.'],
            ['product' => 'starter-kit', 'author' => 'Chris W.', 'rating' => 5, 'status' => 'approved', 'verified' => true,
                'title' => 'Bought it as a gift, bought a second for myself', 'body' => 'Everything you need and nothing you do not. The little guide in the box is better written than most of the videos online.'],
        ];

        $touched = [];

        foreach ($reviews as $data) {
            $product = Product::where('slug', $data['product'])->first();

            if (! $product) {
                continue;
            }

            ProductReview::updateOrCreate(
                ['product_id' => $product->id, 'author_name' => $data['author'], 'title' => $data['title']],
                [
                    'rating' => $data['rating'],
                    'body' => $data['body'],
                    'status' => $data['status'],
                    'verified_purchase' => $data['verified'],
                ]
            );

            $touched[$product->id] = $product;
        }

        foreach ($touched as $product) {
            $product->refreshRating();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function products(): array
    {
        return [
            [
                'slug' => 'kirinyaga-aa',
                'name' => 'Kirinyaga AA',
                'sku' => 'COF-KIR-250',
                'categories' => ['coffee', 'single-origin'],
                'price' => 1850,
                'type' => 'variable',
                'stock' => 64,
                'featured' => true,
                'views' => 3120,
                'sold' => 412,
                'weight' => 0.25,
                'palette' => 'clay',
                'sort_order' => 1,
                'short' => 'Washed SL-28 from central Kenya. Blackcurrant, tomato leaf and a long, dry finish.',
                'description' => self::body([
                    'A washed lot from a station serving around 340 smallholders in Kirinyaga, central Kenya. Cherry is delivered the day it is picked, fermented overnight, then washed and soaked before drying on raised beds.',
                    ['h3', 'In the cup'],
                    'Blackcurrant is the note everybody reaches for, and it is the right one. Underneath it there is tomato leaf, a firm acidity and a finish that stays dry rather than sweet.',
                    ['h3', 'Brewing'],
                    ['ul', [
                        'Filter: 1:16, medium grind, 94C, three minutes total.',
                        'Espresso: possible but demanding. 1:2.5 in 30 seconds, and expect to be sour before you are right.',
                    ]],
                    ['h3', 'Details'],
                    ['ul', [
                        'Varietal: SL-28, SL-34',
                        'Altitude: 1,650-1,800 masl',
                        'Process: Washed, 18-hour ferment',
                        'Roast: Filter',
                    ]],
                ]),
                'variants' => [
                    ['sku' => 'COF-KIR-250', 'name' => '250g, whole bean', 'options' => ['Weight' => '250g', 'Grind' => 'Whole bean'], 'price' => 1850, 'stock' => 40],
                    ['sku' => 'COF-KIR-250-F', 'name' => '250g, ground for filter', 'options' => ['Weight' => '250g', 'Grind' => 'Filter'], 'price' => 1850, 'stock' => 12],
                    ['sku' => 'COF-KIR-1000', 'name' => '1kg, whole bean', 'options' => ['Weight' => '1kg', 'Grind' => 'Whole bean'], 'price' => 6400, 'stock' => 12],
                ],
            ],
            [
                'slug' => 'huila-reserve',
                'name' => 'Huila Reserve',
                'sku' => 'COF-HUI-250',
                'categories' => ['coffee', 'single-origin'],
                'price' => 1650,
                'stock' => 88,
                'views' => 1740,
                'sold' => 268,
                'weight' => 0.25,
                'palette' => 'sage',
                'sort_order' => 2,
                'short' => 'Washed Caturra from Huila, Colombia. Red apple, panela and milk chocolate.',
                'description' => self::body([
                    'The coffee we hand to anyone who says they are not sure what they like. Washed Caturra from a group of farms around Pitalito, grown between 1,500 and 1,750 metres.',
                    ['h3', 'In the cup'],
                    'Red apple acidity, panela sweetness and enough chocolate underneath to take milk without disappearing. Balanced rather than loud, and it stays good for the whole bag.',
                    ['h3', 'Brewing'],
                    'Forgiving anywhere. 1:16 filter, or 1:2 espresso in 27 seconds. There is no wrong answer here, which is rather the point.',
                ]),
            ],
            [
                'slug' => 'morning-blend',
                'name' => 'Morning Blend',
                'sku' => 'COF-MOR-250',
                'categories' => ['coffee', 'blends'],
                'price' => 1450,
                'sale_price' => 1150,
                'stock' => 140,
                'featured' => true,
                'views' => 4890,
                'sold' => 931,
                'weight' => 0.25,
                'palette' => 'espresso',
                'sort_order' => 3,
                'short' => 'Our house blend. Hazelnut, cocoa and brown sugar - built to work with milk.',
                'description' => self::body([
                    'Two parts washed Colombian for structure, one part natural Brazilian for body and sweetness. Roasted a shade darker than our single origins so it holds its own against milk.',
                    ['h3', 'In the cup'],
                    'Hazelnut, cocoa and brown sugar. As a flat white it is exactly what a flat white should taste like; black, it is comfortable rather than interesting, and that is deliberate.',
                    ['h3', 'Why it is on offer'],
                    'We over-roasted against a wholesale order that moved. It is the same coffee at a lower price for the next two weeks, and there is nothing wrong with it.',
                ]),
            ],
            [
                'slug' => 'night-owl-decaf',
                'name' => 'Night Owl Decaf',
                'sku' => 'COF-DEC-250',
                'categories' => ['coffee', 'decaf'],
                'price' => 1550,
                'stock' => 46,
                'views' => 1120,
                'sold' => 187,
                'weight' => 0.25,
                'palette' => 'indigo',
                'sort_order' => 4,
                'short' => 'Sugarcane-process decaf from Colombia. Cocoa, dried fig, no solvent anywhere near it.',
                'description' => self::body([
                    'Decaffeinated by the sugarcane ethyl acetate method in Colombia, which uses a compound derived from fermented cane rather than a synthetic solvent. It is gentler on the coffee than most alternatives and you can taste the difference.',
                    ['h3', 'In the cup'],
                    'Cocoa and dried fig, with a soft acidity. It does not taste hollowed out, which is the usual complaint about decaf and a fair one.',
                    ['h3', 'Brewing'],
                    'Grind a little finer than you would the caffeinated equivalent - decaffeinated beans are more brittle and extract faster.',
                ]),
            ],
            [
                'slug' => 'pour-over-kettle',
                'name' => 'Pour-over Kettle 1L',
                'sku' => 'EQP-KET-1L',
                'categories' => ['equipment', 'brewers'],
                'price' => 7900,
                'stock' => 23,
                'views' => 2240,
                'sold' => 141,
                'weight' => 1.1,
                'dimensions' => '28 x 14 x 22 cm',
                'palette' => 'slate',
                'sort_order' => 5,
                'short' => 'Gooseneck spout, 1 litre, variable temperature from 40C to 100C.',
                'description' => self::body([
                    'A gooseneck kettle with proper temperature control. The spout tapers hard enough to give you a pour you can actually aim, at a flow rate slow enough to matter.',
                    ['h3', 'Specification'],
                    ['ul', [
                        'Capacity: 1 litre',
                        'Temperature: 40C to 100C in 1-degree steps',
                        'Hold: maintains a set temperature for 60 minutes',
                        'Body: brushed stainless steel, no plastic on the water path',
                    ]],
                    ['h3', 'In use'],
                    'It reaches 94C from cold in a little over three minutes. The handle warms up towards the end of a full boil - not enough to be a problem, but you will notice it.',
                ]),
            ],
            [
                'slug' => 'ceramic-dripper',
                'name' => 'Ceramic Dripper',
                'sku' => 'EQP-DRP-02',
                'categories' => ['equipment', 'brewers'],
                'price' => 3200,
                'stock' => 57,
                'views' => 1680,
                'sold' => 305,
                'weight' => 0.4,
                'dimensions' => '12 x 12 x 10 cm',
                'palette' => 'teal',
                'sort_order' => 6,
                'short' => 'Size 02 cone in glazed stoneware. Brews one to four cups.',
                'description' => self::body([
                    'A 60-degree cone in glazed stoneware, with a spiral rib pattern and a single large opening. Takes standard size 02 paper filters.',
                    ['h3', 'Why ceramic'],
                    'It holds heat far better than plastic once preheated, which keeps the brew temperature stable through the drawdown. Preheating is not optional - a cold ceramic cone will rob you of several degrees.',
                    ['h3', 'Care'],
                    'Dishwasher safe. Chips if you knock it against a tap, so wash it in a bowl rather than a steel sink if you can.',
                ]),
            ],
            [
                'slug' => 'hand-grinder-pro',
                'name' => 'Hand Grinder Pro',
                'sku' => 'EQP-GRD-HND',
                'categories' => ['equipment', 'grinders'],
                'price' => 14500,
                'stock' => 11,
                'featured' => true,
                'views' => 5310,
                'sold' => 96,
                'weight' => 0.62,
                'dimensions' => '16 x 6 x 6 cm',
                'palette' => 'amber',
                'sort_order' => 7,
                'short' => '48mm stainless burrs, stepless adjustment, good enough for espresso.',
                'description' => self::body([
                    'A hand grinder with 48mm conical stainless burrs and a stepless collar, held in double bearings so the burrs stay aligned under load. That alignment is the whole reason it can do espresso.',
                    ['h3', 'Specification'],
                    ['ul', [
                        'Burrs: 48mm conical, hardened stainless',
                        'Adjustment: stepless, approximately 15 microns per detent of feel',
                        'Capacity: 25g',
                        'Espresso dose: around 40 seconds of grinding',
                    ]],
                    ['h3', 'Honestly'],
                    'If you make more than three or four drinks a day, buy an electric. This is the best grinder you can get at this price, and it is still a hand grinder.',
                ]),
            ],
            [
                'slug' => 'electric-grinder',
                'name' => 'Electric Burr Grinder',
                'sku' => 'EQP-GRD-ELC',
                'categories' => ['equipment', 'grinders'],
                'price' => 24900,
                // Out of stock, with backorders off: the case that has to show
                // a disabled add-to-cart rather than a quietly failing one.
                'stock' => 0,
                'views' => 3980,
                'sold' => 74,
                'weight' => 3.4,
                'dimensions' => '38 x 16 x 21 cm',
                'palette' => 'slate',
                'sort_order' => 8,
                'short' => '64mm flat burrs, single dose, low retention. Back in stock next month.',
                'description' => self::body([
                    'Single-dose electric grinder with 64mm flat burrs, a bellows lid and a short, steep chute that keeps retention under half a gram.',
                    ['h3', 'Specification'],
                    ['ul', [
                        'Burrs: 64mm flat, titanium coated',
                        'Motor: 250W, 1,350 rpm',
                        'Retention: under 0.5g dose to dose',
                        'Adjustment: stepless, with a numbered reference collar',
                    ]],
                    ['h3', 'Availability'],
                    'The current batch sold out faster than we planned for. The next shipment lands at the end of next month and we are not taking backorders in the meantime.',
                ]),
            ],
            [
                'slug' => 'paper-filters-100',
                'name' => 'Paper Filters, 100 pack',
                'sku' => 'ACC-FIL-100',
                'categories' => ['equipment', 'accessories'],
                'price' => 800,
                'stock' => 6,
                // Low stock plus backorders on: the shop should keep selling.
                'backorder' => true,
                'views' => 940,
                'sold' => 1284,
                'weight' => 0.2,
                'palette' => 'sage',
                'sort_order' => 9,
                'short' => 'Size 02 bleached cones, 100 per box. Fits our ceramic dripper and any V60.',
                'description' => self::body([
                    'Oxygen-bleached size 02 cones, 100 to a box. Bleached rather than natural because unbleached paper contributes a papery taste that rinsing never entirely removes.',
                    ['h3', 'Rinse them anyway'],
                    'A few seconds of hot water through the filter before you add coffee preheats the cone and washes out what little taste there is. It takes no time and it is worth doing every single brew.',
                ]),
            ],
            [
                'slug' => 'insulated-tumbler',
                'name' => 'Insulated Tumbler 350ml',
                'sku' => 'ACC-TUM-350',
                'categories' => ['equipment', 'accessories', 'gifts'],
                'price' => 2800,
                'type' => 'variable',
                'stock' => 72,
                'views' => 2110,
                'sold' => 448,
                'weight' => 0.33,
                'dimensions' => '18 x 8 x 8 cm',
                'palette' => 'plum',
                'sort_order' => 10,
                'short' => 'Vacuum-walled stainless, 350ml, leak-proof lid. Four colours.',
                'description' => self::body([
                    'Double-walled vacuum stainless steel with a threaded, gasketed lid that genuinely does not leak in a bag. Holds a double flat white with room to spare.',
                    ['h3', 'Specification'],
                    ['ul', [
                        'Capacity: 350ml',
                        'Heat retention: hot for around 5 hours, cold for 12',
                        'Lid: threaded, silicone gasket, dishwasher safe',
                        'Body: hand wash - the dishwasher will dull the finish',
                    ]],
                ]),
                'variants' => [
                    ['sku' => 'ACC-TUM-350-BK', 'name' => 'Matte black', 'options' => ['Colour' => 'Matte black'], 'stock' => 31],
                    ['sku' => 'ACC-TUM-350-SG', 'name' => 'Sage', 'options' => ['Colour' => 'Sage'], 'stock' => 24],
                    ['sku' => 'ACC-TUM-350-CL', 'name' => 'Clay', 'options' => ['Colour' => 'Clay'], 'stock' => 17],
                    ['sku' => 'ACC-TUM-350-ST', 'name' => 'Brushed steel', 'options' => ['Colour' => 'Brushed steel'], 'stock' => 0, 'is_active' => false],
                ],
            ],
            [
                'slug' => 'cupping-spoon-set',
                'name' => 'Cupping Spoon Set',
                'sku' => 'ACC-SPN-04',
                'categories' => ['equipment', 'accessories', 'gifts'],
                'price' => 3600,
                'stock' => 19,
                'views' => 610,
                'sold' => 63,
                'weight' => 0.28,
                'palette' => 'teal',
                'sort_order' => 11,
                'short' => 'Four heavy-gauge cupping spoons in a linen roll.',
                'description' => self::body([
                    'Four deep-bowled cupping spoons in heavy-gauge stainless, in a stitched linen roll. The bowl shape is what matters: deep and wide enough to aerate properly when you slurp.',
                    'They are also, unglamorously, very good soup spoons.',
                ]),
            ],
            [
                'slug' => 'tasting-journal',
                'name' => 'Tasting Journal',
                'sku' => 'GFT-JNL-01',
                'categories' => ['gifts', 'accessories'],
                'price' => 1400,
                // Stock management off: an always-available item, and the case
                // where the stock column should not be shown at all.
                'manage_stock' => false,
                'views' => 780,
                'sold' => 219,
                'weight' => 0.31,
                'dimensions' => '21 x 15 x 2 cm',
                'palette' => 'clay',
                'sort_order' => 12,
                'short' => 'Ninety-six pages, one brew per page. Grind, dose, time, verdict.',
                'description' => self::body([
                    'A cloth-bound notebook with ninety-six pre-printed brew sheets: coffee, roast date, grind setting, dose, yield, time, temperature, and a wide space for what you actually thought.',
                    'The point is not ceremony. It is that in three weeks you will not remember which setting worked, and the bag will be gone by the time you want it again.',
                ]),
            ],
            [
                'slug' => 'brew-guide-ebook',
                'name' => 'The Brew Guide (eBook)',
                'sku' => 'DIG-EBK-01',
                'categories' => ['gifts'],
                'price' => 900,
                'type' => 'digital',
                'manage_stock' => false,
                'views' => 1460,
                'sold' => 388,
                'palette' => 'indigo',
                'sort_order' => 13,
                'short' => 'Eighty pages on grind, water and ratio. PDF and EPUB, delivered instantly.',
                'description' => self::body([
                    'Everything we end up explaining at the counter, written down properly: grind size, water chemistry, ratios, and a troubleshooting section organised by what is wrong with the cup rather than by equipment.',
                    ['h3', 'What you get'],
                    ['ul', [
                        'PDF and EPUB, no DRM.',
                        'Eighty pages, illustrated.',
                        'Free updates when we revise it.',
                    ]],
                    ['h3', 'Note for this demo'],
                    'This product is set up as a digital download but has no file attached. Upload one under the product\'s Digital tab to see the delivery flow end to end.',
                ]),
            ],
            [
                'slug' => 'starter-kit',
                'name' => 'Filter Starter Kit',
                'sku' => 'GFT-KIT-01',
                'categories' => ['gifts', 'brewers'],
                'price' => 6900,
                'sale_price' => 5400,
                'stock' => 27,
                'featured' => true,
                'views' => 3640,
                'sold' => 176,
                'weight' => 1.3,
                'dimensions' => '26 x 20 x 14 cm',
                'palette' => 'amber',
                'sort_order' => 14,
                'short' => 'Dripper, filters, a 250g bag and the guide. Everything except the kettle.',
                'description' => self::body([
                    'The box we put together for people who want to start brewing properly and do not want to research four purchases to do it.',
                    ['h3', 'In the box'],
                    ['ul', [
                        'Ceramic dripper, size 02',
                        '100 paper filters',
                        '250g of Morning Blend, roasted that week',
                        'A printed brew guide, one page, laminated',
                    ]],
                    ['h3', 'What is not in the box'],
                    'A kettle and a grinder. Both matter more than anything here, and both cost more than the kit - we would rather say so than pad the box with versions that are not worth owning.',
                ]),
            ],
            [
                'slug' => 'spring-seasonal',
                'name' => 'Spring Seasonal',
                'sku' => 'COF-SPR-250',
                'categories' => ['coffee', 'blends'],
                'price' => 1750,
                'stock' => 0,
                'status' => 'draft',
                'views' => 0,
                'sold' => 0,
                'weight' => 0.25,
                'palette' => 'sage',
                'sort_order' => 15,
                'short' => 'Draft listing. Not on sale until the lot lands.',
                'description' => self::body([
                    'Draft - do not publish. Copy still needs the cupping notes from the sample roast, and we have not confirmed the third component.',
                    ['h3', 'TODO'],
                    ['ul', [
                        'Cupping notes from Thursday\'s table.',
                        'Confirm the Costa Rican allocation.',
                        'Photography.',
                    ]],
                ]),
            ],
        ];
    }
}
