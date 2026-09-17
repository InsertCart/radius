<?php

namespace Tests\Feature;

use App\Cms\Search\Tokenizer;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Site search: both engines, live results, the results page and the admin
 * controls. The same behaviour is asserted against the database and the
 * index, because a site owner switching engines must not notice anything but
 * the speed.
 */
class SiteSearchTest extends TestCase
{
    use RefreshDatabase;

    private string $indexPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');

        $this->indexPath = storage_path('framework/testing/search-index-'.uniqid());
        config(['search.index.path' => $this->indexPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->indexPath);

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Owner', 'email' => 'owner@x.test', 'password' => Hash::make('password-123'),
            'role' => User::ROLE_ADMIN, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function article(string $title, array $attributes = []): Post
    {
        return Post::create(array_merge([
            'title' => $title, 'slug' => str($title)->slug(), 'status' => 'published',
            'content' => '<p>Body of '.$title.'</p>', 'published_at' => now()->subDay(),
        ], $attributes));
    }

    private function product(string $name, array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => $name, 'slug' => str($name)->slug(), 'price' => 1500,
            'type' => 'simple', 'status' => 'published',
        ], $attributes));
    }

    private function useIndex(): void
    {
        settings()->set('search_engine', 'index');
        search()->rebuild();
    }

    private function suggestTitles(string $query, ?string $type = null): array
    {
        $response = $this->getJson(route('search.suggest', array_filter(['q' => $query, 'type' => $type])))->assertOk();

        return collect($response->json('groups'))->flatMap(fn ($group) => array_column($group['items'], 'title'))->all();
    }

    public static function engines(): array
    {
        return ['database' => ['database'], 'index' => ['index']];
    }

    // Behaviour shared by both engines -------------------------------------

    #[DataProvider('engines')]
    public function test_live_results_find_published_content_only(string $engine): void
    {
        $this->article('Brewing the perfect espresso');
        $this->article('Espresso drafts', ['status' => 'draft']);
        $this->product('Espresso machine');

        $engine === 'index' ? $this->useIndex() : settings()->set('search_engine', 'database');

        $titles = $this->suggestTitles('espresso');

        $this->assertContains('Brewing the perfect espresso', $titles);
        $this->assertContains('Espresso machine', $titles);
        $this->assertNotContains('Espresso drafts', $titles);
    }

    #[DataProvider('engines')]
    public function test_live_results_can_be_limited_to_one_type(string $engine): void
    {
        $this->article('Espresso guide');
        $this->product('Espresso machine');

        $engine === 'index' ? $this->useIndex() : settings()->set('search_engine', 'database');

        $this->assertSame(['Espresso machine'], $this->suggestTitles('espresso', 'product'));
    }

    #[DataProvider('engines')]
    public function test_blog_listing_filters_by_query(string $engine): void
    {
        $this->article('Grinding coffee at home');
        $this->article('Choosing a kettle');

        $engine === 'index' ? $this->useIndex() : settings()->set('search_engine', 'database');

        $this->get(route('blog.index', ['q' => 'coffee']))
            ->assertOk()
            ->assertSee('Grinding coffee at home')
            ->assertDontSee('Choosing a kettle');
    }

    #[DataProvider('engines')]
    public function test_shop_category_listing_still_filters_by_query(string $engine): void
    {
        $category = \App\Models\Category::create(['name' => 'Gear', 'slug' => 'gear', 'type' => 'shop', 'is_active' => true]);
        $this->product('Pour over kettle')->categories()->attach($category);
        $this->product('Milk jug')->categories()->attach($category);

        $engine === 'index' ? $this->useIndex() : settings()->set('search_engine', 'database');

        $this->get(route('shop.category', ['slug' => 'gear', 'q' => 'kettle']))
            ->assertOk()
            ->assertSee('Pour over kettle')
            ->assertDontSee('Milk jug');
    }

    public function test_the_results_page_groups_matches_by_type(): void
    {
        $this->article('Cold brew notes');
        $this->product('Cold brew bottle');
        Page::create(['title' => 'Cold brew FAQ', 'slug' => 'cold-brew-faq', 'status' => 'published', 'content' => 'Answers']);

        $this->get(route('search', ['q' => 'cold brew']))
            ->assertOk()
            ->assertSeeInOrder(['Products', 'Cold brew bottle', 'Posts', 'Cold brew notes', 'Pages', 'Cold brew FAQ']);
    }

    public function test_a_type_switched_off_is_not_searched(): void
    {
        $this->article('Latte art basics');
        settings()->set('search_posts', false);

        $this->assertSame([], $this->suggestTitles('latte'));
    }

    public function test_short_queries_and_disabled_live_results(): void
    {
        $this->article('Flat white');

        $this->assertSame([], $this->suggestTitles('f'));

        settings()->set('search_instant', false);
        $this->getJson(route('search.suggest', ['q' => 'flat']))->assertNotFound();
    }

    public function test_the_live_search_script_is_printed_once_and_only_when_enabled(): void
    {
        $html = $this->get(route('blog.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-radius-search="post"', $html);
        $this->assertSame(1, substr_count($html, 'window.RadiusSearch = window.RadiusSearch'));

        settings()->set('search_instant', false);
        $this->app->forgetInstance(\App\Cms\Search\SearchManager::class);

        $this->assertStringNotContainsString('window.RadiusSearch', $this->get(route('blog.index'))->getContent());
    }

    // The index ------------------------------------------------------------

    public function test_the_index_answers_without_querying_the_database(): void
    {
        $this->article('Aeropress recipes');
        $this->useIndex();

        \DB::enableQueryLog();
        $results = search()->search('post', 'aeropress');
        $queries = collect(\DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_contains($sql, 'posts'));

        $this->assertSame(1, $results->total);
        $this->assertSame('Aeropress recipes', $results->items[0]['title']);
        $this->assertCount(0, $queries, 'The index engine read the posts table.');
    }

    public function test_the_index_follows_saves_unpublishing_and_deletes(): void
    {
        $this->useIndex();

        $post = $this->article('Siphon brewing');
        $this->assertSame(['Siphon brewing'], $this->suggestTitles('siphon'));

        $post->update(['title' => 'Vacuum pot brewing', 'content' => 'Glass and patience.']);
        $this->assertSame([], $this->suggestTitles('siphon'));
        $this->assertSame(['Vacuum pot brewing'], $this->suggestTitles('vacuum'));

        $post->update(['status' => 'draft']);
        $this->assertSame([], $this->suggestTitles('vacuum'));

        $post->update(['status' => 'published']);
        $this->assertSame(['Vacuum pot brewing'], $this->suggestTitles('vacuum'));

        $post->delete();
        $this->assertSame([], $this->suggestTitles('vacuum'));
        $this->assertSame(0, search()->engine('index')->status('post')['documents']);
    }

    public function test_the_index_hides_scheduled_posts_until_their_date(): void
    {
        $this->article('Future roast', ['published_at' => now()->addDay()]);
        $this->useIndex();

        $this->assertSame([], $this->suggestTitles('roast'));

        $this->travel(2)->days();
        $this->assertSame(['Future roast'], $this->suggestTitles('roast'));
    }

    public function test_the_index_matches_prefixes_accents_and_ranks_titles_first(): void
    {
        $this->article('Notes on grinders', ['content' => 'A café guide to espresso grinders.']);
        $this->article('Café guide', ['content' => 'Nothing about equipment.']);
        $this->useIndex();

        $this->assertSame(['Café guide', 'Notes on grinders'], $this->suggestTitles('cafe'));
        $this->assertSame(['Notes on grinders'], $this->suggestTitles('grind'));
        $this->assertSame(['Notes on grinders'], $this->suggestTitles('espresso grin'));
    }

    public function test_an_unbuilt_index_falls_back_to_the_database(): void
    {
        $this->article('Moka pot');
        settings()->set('search_engine', 'index');

        $this->assertFalse(search()->engine('index')->ready('post'));
        $this->assertSame(['Moka pot'], $this->suggestTitles('moka'));
    }

    public function test_tokenizer_keeps_non_latin_words(): void
    {
        $this->assertSame(['कॉफ़ी', 'cafe', 'кофе'], Tokenizer::words('कॉफ़ी Café кофе'));
    }

    // Admin ----------------------------------------------------------------

    public function test_choosing_the_index_in_settings_builds_it(): void
    {
        $this->article('Chemex technique');

        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.settings.update', 'search'), [
                'search_engine' => 'index',
                'search_instant' => '1',
                'search_min_chars' => 2,
                'search_suggest_limit' => 5,
                'search_posts' => '1',
                'search_products' => '1',
                'search_pages' => '1',
                'search_page' => '1',
                'search_rate_limit' => 60,
                'search_cache_seconds' => 60,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'Search index built'));

        $this->assertTrue(search()->engine('index')->ready('post'));

        $this->actingAs($admin)
            ->get(route('admin.settings.edit', 'search'))
            ->assertOk()
            ->assertSee('Search status')
            ->assertSee('Rebuild index now');

        $this->post(route('admin.tools.search.rebuild'))
            ->assertSessionHas('status', 'Search index rebuilt: 1 item(s).');
    }
}
