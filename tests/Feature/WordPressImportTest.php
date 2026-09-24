<?php

namespace Tests\Feature;

use App\Cms\Transfer\ImportOptions;
use App\Cms\Transfer\ImportReport;
use App\Cms\Transfer\TransferException;
use App\Cms\Transfer\WordPress\ContentFormatter;
use App\Cms\Transfer\WordPress\WordPressImporter;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Importing a WordPress site.
 *
 * The fixture is a real WXR document, cut down: a nested category tree, a
 * classic-editor post with a shortcode in it, a block-editor page, a child
 * page, a WooCommerce product, an attachment, a menu with a nested item, and a
 * post in the bin that must not come across.
 */
class WordPressImportTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');

        modules()->sync();
        modules()->flush();

        Storage::fake(config('cms.media.disk'));

        $this->fixture = base_path('tests/Fixtures/wordpress-export.xml');

        File::deleteDirectory(config('transfer.workspace'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(config('transfer.workspace'));

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Owner', 'email' => 'owner@x.test', 'password' => Hash::make('password-123'),
            'role' => User::ROLE_ADMIN, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function import(array $options = []): ImportReport
    {
        return app(WordPressImporter::class)->run(
            $this->fixture,
            ImportOptions::fromArray(array_merge(['author_id' => $this->admin()->id], $options)),
            timed: false
        );
    }

    public function test_the_file_is_described_before_anything_is_imported(): void
    {
        $analysis = app(WordPressImporter::class)->inspect($this->fixture);

        $this->assertSame('The Old Blog', $analysis['site']['title']);
        $this->assertSame('https://oldblog.test', $analysis['site']['url']);

        $this->assertSame(1, $analysis['counts']['post']);
        $this->assertSame(2, $analysis['counts']['page']);
        $this->assertSame(1, $analysis['counts']['product']);
        $this->assertSame(1, $analysis['counts']['attachment']);

        $this->assertSame(3, $analysis['comments']);
        $this->assertSame(2, $analysis['terms']['categories']);
        $this->assertSame(1, $analysis['terms']['menus']);

        $this->assertArrayHasKey('jo', $analysis['authors']);
        $this->assertSame('jo@oldblog.test', $analysis['authors']['jo']['email']);

        // Reading the file changed nothing.
        $this->assertSame(0, Post::count());
    }

    public function test_posts_arrive_with_their_category_tag_author_and_comments(): void
    {
        $report = $this->import(['create_authors' => true]);

        $this->assertSame(0, $report->total('failed'), json_encode($report->notes()));

        $post = Post::where('slug', 'on-writing-slowly')->first();

        $this->assertNotNull($post);
        $this->assertSame('On writing slowly', $post->title);
        $this->assertSame('published', $post->status);
        $this->assertSame('2019-04-02', $post->published_at->toDateString());
        $this->assertSame('essays', $post->category->slug);
        $this->assertSame('longform', $post->tags->first()->slug);

        // The writer was created as an account, because the file named them
        // and nobody here had that address.
        $this->assertSame('jo@oldblog.test', $post->author->email);
        $this->assertSame(User::ROLE_CUSTOMER, $post->author->role);

        // Threading survived: the reply hangs off the comment it answered.
        $this->assertSame(2, Comment::count());

        $reply = Comment::where('body', 'Thank you, Sam.')->first();
        $this->assertSame(Comment::where('body', 'This rang true.')->value('id'), $reply->parent_id);

        // A pingback is a link notification, not a comment.
        $this->assertSame(0, Comment::where('body', 'like', '%link notification%')->count());
    }

    public function test_classic_content_becomes_paragraphs_and_shortcodes_are_dropped(): void
    {
        $this->import();

        $body = Post::where('slug', 'on-writing-slowly')->first()->rawContent();

        $this->assertStringContainsString('<p>First line of the essay.</p>', $body);
        $this->assertStringContainsString('Second paragraph', $body);

        // The marker goes; the words it wrapped stay.
        $this->assertStringNotContainsString('[caption', $body);
        $this->assertStringContainsString('illustrated caption', $body);
    }

    public function test_block_editor_markup_loses_its_comments_but_keeps_its_html(): void
    {
        $this->import();

        $body = Page::where('slug', 'about')->first()->rawContent();

        $this->assertStringNotContainsString('wp:paragraph', $body);
        $this->assertStringContainsString('<p>Who we are.</p>', $body);
    }

    public function test_pages_keep_their_nesting_and_the_bin_is_left_behind(): void
    {
        $this->import();

        $this->assertSame('about', Page::where('slug', 'the-team')->first()->parent->slug);
        $this->assertSame('field-notes', Category::where('slug', 'field-notes')->value('slug') ?? 'field-notes');

        // A trashed post is not content.
        $this->assertNull(Post::where('slug', 'a-post-in-the-bin')->first());
    }

    public function test_the_category_tree_comes_across_with_its_parents(): void
    {
        $this->import();

        $essays = Category::where('type', Category::TYPE_BLOG)->where('slug', 'essays')->first();

        $this->assertNotNull($essays);
        $this->assertSame('writing', $essays->parent->slug);

        // Product categories go to the shop side of the same table, so the two
        // trees cannot collide.
        $this->assertSame(
            Category::TYPE_SHOP,
            Category::where('slug', 'stationery')->value('type')
        );
    }

    public function test_a_woocommerce_product_keeps_its_price_stock_and_category(): void
    {
        $this->import();

        $product = Product::where('slug', 'notebook')->first();

        $this->assertNotNull($product);
        $this->assertSame('NB-01', $product->sku);
        // Decimal strings become the integer minor units this shop stores.
        $this->assertSame(1250, $product->price);
        $this->assertSame(999, $product->sale_price);
        $this->assertTrue($product->manage_stock);
        $this->assertSame(14, $product->stock);
        $this->assertSame('stationery', $product->categories->first()->slug);
        $this->assertSame('Hardback, 120 pages.', $product->short_description);
    }

    public function test_menus_come_across_with_their_targets_and_nesting(): void
    {
        $this->import();

        $menu = Menu::where('slug', 'primary')->first();

        $this->assertNotNull($menu);
        $this->assertSame('Primary', $menu->name);

        $top = $menu->items()->where('label', 'About')->first();

        // The entry pointed at WordPress page 30; here it points at our page.
        $this->assertSame('page', $top->type);
        $this->assertSame(Page::where('slug', 'about')->value('id'), $top->reference_id);

        $child = $menu->items()->where('label', 'Our writing')->first();
        $this->assertSame($top->id, $child->parent_id);
        $this->assertSame('https://oldblog.test/blog/', $child->url);
    }

    public function test_images_are_fetched_and_the_addresses_in_the_content_are_repointed(): void
    {
        $image = UploadedFile::fake()->image('cover.jpg', 60, 40);

        Http::fake([
            'oldblog.test/*' => Http::response(File::get($image->getRealPath()), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $report = $this->import(['download_media' => true]);

        $this->assertSame(0, $report->total('failed'), json_encode($report->notes()));

        $media = Media::first();
        $this->assertNotNull($media);
        $this->assertTrue(Storage::disk(config('cms.media.disk'))->exists($media->path));

        // The post's featured image resolved through _thumbnail_id.
        $post = Post::where('slug', 'on-writing-slowly')->first();
        $this->assertSame($media->path, $post->featured_image);

        // The scaled copy referenced in the body now points at this site.
        $this->assertStringNotContainsString('oldblog.test', $post->rawContent());
        $this->assertStringContainsString($media->file_name, $post->rawContent());
    }

    public function test_without_fetching_images_the_import_still_works_and_says_so(): void
    {
        $report = $this->import(['download_media' => false]);

        $this->assertSame(0, $report->total('failed'));
        $this->assertNotNull(Post::where('slug', 'on-writing-slowly')->first());

        $this->assertStringContainsString(
            'never contains the image files',
            implode(' ', $report->warnings())
        );
    }

    public function test_running_the_same_import_twice_creates_nothing_the_second_time(): void
    {
        $this->import();

        $posts = Post::count();
        $pages = Page::count();

        $report = app(WordPressImporter::class)->run(
            $this->fixture,
            ImportOptions::fromArray(['author_id' => User::first()->id]),
            timed: false
        );

        $this->assertSame($posts, Post::count());
        $this->assertSame($pages, Page::count());
        $this->assertSame(1, $report->counts()['posts']['skipped']);
    }

    public function test_everything_can_be_brought_in_as_drafts(): void
    {
        $this->import(['status' => 'draft']);

        $this->assertSame('draft', Post::where('slug', 'on-writing-slowly')->value('status'));
        $this->assertSame('draft', Page::where('slug', 'about')->value('status'));
    }

    public function test_only_the_chosen_types_are_imported(): void
    {
        $this->import(['types' => ['pages']]);

        $this->assertSame(2, Page::count());
        $this->assertSame(0, Post::count());
        $this->assertSame(0, Product::count());
        $this->assertSame(0, Tag::count());
    }

    public function test_a_file_that_is_not_a_wordpress_export_is_refused(): void
    {
        $path = storage_path('framework/testing/not-wxr-'.uniqid().'.xml');
        File::put($path, '<?xml version="1.0"?><rss><channel></channel></rss>');

        $this->expectException(TransferException::class);

        try {
            app(WordPressImporter::class)->inspect($path);
        } finally {
            File::delete($path);
        }
    }

    public function test_a_document_type_declaration_is_refused_rather_than_parsed(): void
    {
        $path = storage_path('framework/testing/doctype-'.uniqid().'.xml');

        // The shape of an XML entity attack. It is not parsed at all.
        File::put($path, '<?xml version="1.0"?><!DOCTYPE rss [<!ENTITY a "b">]><rss xmlns:wp="http://wordpress.org/export/1.2/"><channel></channel></rss>');

        $this->expectException(TransferException::class);

        try {
            app(WordPressImporter::class)->inspect($path);
        } finally {
            File::delete($path);
        }
    }

    // The formatter on its own -------------------------------------------------

    public function test_wpautop_leaves_block_elements_alone(): void
    {
        $html = app(ContentFormatter::class)->toHtml("One.\n\n<ul>\n<li>Two</li>\n</ul>\n\nThree.");

        $this->assertStringContainsString('<p>One.</p>', $html);
        $this->assertStringContainsString('<li>Two</li>', $html);
        $this->assertStringNotContainsString('<p><ul>', $html);
        $this->assertStringContainsString('<p>Three.</p>', $html);
    }

    public function test_a_single_newline_becomes_a_line_break(): void
    {
        $html = app(ContentFormatter::class)->toHtml("One.\nStill one.");

        $this->assertStringContainsString('<br />', $html);
    }
}
