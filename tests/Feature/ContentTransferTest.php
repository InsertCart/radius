<?php

namespace Tests\Feature;

use App\Cms\Media\MediaService;
use App\Cms\Transfer\ExportOptions;
use App\Cms\Transfer\ExportService;
use App\Cms\Transfer\ImportOptions;
use App\Cms\Transfer\ImportService;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Coupon;
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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Export and import, end to end.
 *
 * The test that matters is the round trip: export a site, wipe it, import the
 * file, and check what comes back is the same site. Anything less proves the
 * exporter writes files, not that the files are worth anything.
 */
class ContentTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');

        modules()->sync();
        modules()->flush();

        Storage::fake(config('cms.media.disk'));

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

    /** A small but complete site: every type, wired to each other. */
    private function seedSite(): array
    {
        $author = $this->admin();

        $media = app(MediaService::class)->store(
            UploadedFile::fake()->image('hero.jpg', 40, 30),
            '2026/09'
        );

        $category = Category::create([
            'type' => Category::TYPE_BLOG, 'name' => 'Field notes', 'slug' => 'field-notes',
        ]);

        $child = Category::create([
            'type' => Category::TYPE_BLOG, 'name' => 'Long reads', 'slug' => 'long-reads',
            'parent_id' => $category->id,
        ]);

        $post = Post::create([
            'title' => 'The first post', 'slug' => 'the-first-post', 'status' => 'published',
            'content' => '<p>Hello <strong>world</strong>.</p>', 'excerpt' => 'A greeting.',
            'category_id' => $child->id, 'author_id' => $author->id,
            'featured_image' => $media->path, 'published_at' => now()->subWeek(),
        ]);

        $post->tags()->sync([Tag::create(['name' => 'Notes', 'slug' => 'notes'])->id]);

        Comment::create([
            'post_id' => $post->id, 'author_name' => 'Reader', 'author_email' => 'reader@x.test',
            'body' => 'Good read.', 'status' => 'approved',
        ]);

        $parent = Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'content' => '<p>Us.</p>']);
        Page::create(['title' => 'Team', 'slug' => 'team', 'status' => 'published', 'parent_id' => $parent->id]);

        $shopCategory = Category::create(['type' => Category::TYPE_SHOP, 'name' => 'Mugs', 'slug' => 'mugs']);

        $product = Product::create([
            'name' => 'Enamel mug', 'slug' => 'enamel-mug', 'sku' => 'MUG-1', 'status' => 'published',
            'price' => 1999, 'stock' => 7, 'description' => '<p>Holds tea.</p>',
            'featured_image' => $media->path,
        ]);

        $product->categories()->sync([$shopCategory->id]);
        $product->variants()->create(['name' => 'Large', 'options' => ['Size' => 'L'], 'stock' => 3]);

        Coupon::create(['code' => 'WELCOME', 'type' => 'percent', 'value' => 1000]);

        $menu = Menu::create(['name' => 'Header', 'slug' => 'header']);
        $menu->items()->create(['label' => 'About us', 'type' => 'page', 'reference_id' => $parent->id, 'sort_order' => 0]);
        $menu->items()->create(['label' => 'Blog', 'type' => 'custom', 'url' => '/blog', 'sort_order' => 1]);

        return ['author' => $author, 'media' => $media, 'post' => $post, 'product' => $product];
    }

    private function wipe(): void
    {
        Comment::query()->forceDelete();
        Post::query()->forceDelete();
        Page::query()->forceDelete();
        Product::query()->forceDelete();
        Category::query()->delete();
        Tag::query()->delete();
        Coupon::query()->delete();
        Menu::query()->delete();
        Media::query()->delete();
    }

    private function export(array $overrides = []): string
    {
        $result = app(ExportService::class)->run(ExportOptions::fromArray(array_merge([
            'types' => ['media', 'categories', 'tags', 'pages', 'posts', 'comments', 'products', 'coupons', 'menus'],
            'format' => 'zip',
        ], $overrides)));

        $this->assertFileExists($result->path);

        return $result->path;
    }

    public function test_a_bundle_holds_every_type_and_its_files(): void
    {
        $this->seedSite();

        $path = $this->export();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);

        $this->assertSame(1, $manifest['format']);
        $this->assertSame(1, $manifest['types']['posts']);
        $this->assertSame(2, $manifest['types']['pages']);
        $this->assertTrue($manifest['media_files']);

        // One JSON object per line is the format the importer streams back.
        $posts = array_map(
            fn ($line) => json_decode($line, true),
            array_filter(explode("\n", (string) $zip->getFromName('content/posts.jsonl')))
        );

        $this->assertSame('the-first-post', $posts[0]['slug']);
        $this->assertSame('long-reads', $posts[0]['category']['slug']);
        $this->assertSame('owner@x.test', $posts[0]['author']['email']);

        // The image the post uses travelled with it.
        $this->assertNotFalse($zip->locateName('media/'.$posts[0]['featured_image']));

        $zip->close();
    }

    public function test_a_site_survives_a_round_trip(): void
    {
        $this->seedSite();
        $path = $this->export();

        // Keep the file where the import can still read it after the wipe.
        $copy = storage_path('framework/testing/bundle-'.uniqid().'.zip');
        File::copy($path, $copy);

        $this->wipe();
        $this->assertSame(0, Post::count());

        $report = app(ImportService::class)->run($copy, ImportOptions::fromArray([
            'mode' => 'skip',
            'import_media' => true,
        ]), timed: false);

        $this->assertSame(0, $report->total('failed'), json_encode($report->notes()));

        $post = Post::where('slug', 'the-first-post')->first();

        $this->assertNotNull($post);
        $this->assertSame('published', $post->status);
        $this->assertSame('long-reads', $post->category->slug);
        $this->assertSame('notes', $post->tags->first()->slug);
        $this->assertSame('owner@x.test', $post->author->email);
        $this->assertStringContainsString('Hello', $post->rawContent());

        // The featured image came back as a real file in the library.
        $this->assertNotNull($post->featured_image);
        $this->assertTrue(Storage::disk(config('cms.media.disk'))->exists($post->featured_image));

        // Nesting, on both trees.
        $this->assertSame('about', Page::where('slug', 'team')->first()->parent->slug);
        $this->assertSame('field-notes', Category::where('slug', 'long-reads')->first()->parent->slug);

        $product = Product::where('slug', 'enamel-mug')->first();
        $this->assertSame(1999, $product->price);
        $this->assertSame('MUG-1', $product->sku);
        $this->assertSame('mugs', $product->categories->first()->slug);
        $this->assertSame('Large', $product->variants->first()->name);

        $this->assertSame(1, Comment::where('body', 'Good read.')->count());
        $this->assertSame('WELCOME', Coupon::first()->code);

        // A menu item that pointed at a page points at the new page.
        $item = Menu::where('slug', 'header')->first()->items()->where('type', 'page')->first();
        $this->assertSame(Page::where('slug', 'about')->value('id'), $item->reference_id);

        File::delete($copy);
    }

    public function test_importing_twice_changes_nothing_the_second_time(): void
    {
        $this->seedSite();
        $path = $this->export();

        $copy = storage_path('framework/testing/bundle-'.uniqid().'.zip');
        File::copy($path, $copy);

        $report = app(ImportService::class)->run($copy, new ImportOptions, timed: false);

        // Everything is already here, so nothing is created and nothing breaks.
        $this->assertSame(0, $report->total('failed'));
        $this->assertSame(0, $report->counts()['posts']['created']);
        $this->assertSame(1, $report->counts()['posts']['skipped']);
        $this->assertSame(1, Post::count());
        $this->assertSame(2, Page::count());
        $this->assertSame(1, Comment::count());

        File::delete($copy);
    }

    public function test_update_mode_overwrites_and_skip_mode_does_not(): void
    {
        $this->seedSite();
        $path = $this->export();

        $copy = storage_path('framework/testing/bundle-'.uniqid().'.zip');
        File::copy($path, $copy);

        Post::where('slug', 'the-first-post')->update(['title' => 'Renamed by hand']);

        app(ImportService::class)->run($copy, new ImportOptions(mode: ImportOptions::SKIP_EXISTING), timed: false);
        $this->assertSame('Renamed by hand', Post::where('slug', 'the-first-post')->value('title'));

        app(ImportService::class)->run($copy, new ImportOptions(mode: ImportOptions::UPDATE_EXISTING), timed: false);
        $this->assertSame('The first post', Post::where('slug', 'the-first-post')->value('title'));

        File::delete($copy);
    }

    public function test_a_status_can_be_forced_on_the_way_in(): void
    {
        $this->seedSite();
        $path = $this->export();

        $copy = storage_path('framework/testing/bundle-'.uniqid().'.zip');
        File::copy($path, $copy);

        $this->wipe();

        app(ImportService::class)->run($copy, new ImportOptions(status: 'draft'), timed: false);

        $this->assertSame('draft', Post::where('slug', 'the-first-post')->value('status'));
        $this->assertSame('draft', Page::where('slug', 'about')->value('status'));

        File::delete($copy);
    }

    public function test_the_home_page_of_an_import_does_not_displace_this_site_s_own(): void
    {
        $this->seedSite();
        Page::where('slug', 'about')->update(['is_homepage' => true]);

        $path = $this->export(['types' => ['pages']]);
        $copy = storage_path('framework/testing/bundle-'.uniqid().'.zip');
        File::copy($path, $copy);

        Page::query()->forceDelete();
        $ours = Page::create(['title' => 'Welcome', 'slug' => 'welcome', 'status' => 'published', 'is_homepage' => true]);

        app(ImportService::class)->run($copy, new ImportOptions, timed: false);

        $this->assertTrue($ours->fresh()->is_homepage);
        $this->assertFalse(Page::where('slug', 'about')->value('is_homepage'));

        File::delete($copy);
    }

    public function test_a_single_json_document_can_be_exported_and_read_back(): void
    {
        $this->seedSite();

        $result = app(ExportService::class)->run(ExportOptions::fromArray([
            'types' => ['pages', 'posts'],
            'format' => 'json',
        ]));

        $document = json_decode((string) File::get($result->path), true);

        $this->assertSame('the-first-post', $document['content']['posts'][0]['slug']);

        $copy = storage_path('framework/testing/bundle-'.uniqid().'.json');
        File::copy($result->path, $copy);

        $this->wipe();

        app(ImportService::class)->run($copy, new ImportOptions, timed: false);

        $this->assertSame(1, Post::count());
        $this->assertSame(2, Page::count());

        File::delete($copy);
    }

    public function test_a_spreadsheet_export_is_a_readable_csv(): void
    {
        $this->seedSite();

        $result = app(ExportService::class)->run(ExportOptions::fromArray([
            'types' => ['posts'],
            'format' => 'csv',
        ]));

        $this->assertStringEndsWith('.csv', $result->filename);

        $csv = (string) File::get($result->path);

        $this->assertStringContainsString('Title,Slug,Status', $csv);
        $this->assertStringContainsString('The first post', $csv);
        $this->assertStringContainsString('Long reads', $csv);
    }

    public function test_filters_narrow_what_is_exported(): void
    {
        $this->seedSite();

        Post::create(['title' => 'A draft', 'slug' => 'a-draft', 'status' => 'draft']);

        $result = app(ExportService::class)->run(ExportOptions::fromArray([
            'types' => ['posts'],
            'status' => 'published',
            'format' => 'json',
        ]));

        $document = json_decode((string) File::get($result->path), true);

        $this->assertCount(1, $document['content']['posts']);
        $this->assertSame('the-first-post', $document['content']['posts'][0]['slug']);
    }

    // The screen ------------------------------------------------------------

    public function test_an_admin_can_download_an_export_from_the_panel(): void
    {
        $this->seedSite();

        $response = $this->actingAs(User::where('role', User::ROLE_ADMIN)->first())
            ->post('/admin/import-export/export', [
                'types' => ['posts', 'pages'],
                'format' => 'json',
            ]);

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_an_editor_cannot_reach_import_or_export(): void
    {
        $editor = User::create([
            'name' => 'Ed', 'email' => 'ed@x.test', 'password' => Hash::make('password123'),
            'role' => User::ROLE_EDITOR, 'status' => 'active', 'email_verified_at' => now(),
        ]);

        $this->actingAs($editor)->get('/admin/import-export')->assertForbidden();
        $this->actingAs($editor)->post('/admin/import-export/export', ['types' => ['posts'], 'format' => 'json'])->assertForbidden();
        $this->actingAs($editor)->post('/admin/import-export/upload', ['kind' => 'bundle'])->assertForbidden();
    }

    public function test_an_upload_is_described_before_it_is_applied(): void
    {
        $this->seedSite();
        $path = $this->export(['types' => ['posts', 'pages'], 'format' => 'json']);

        $upload = new UploadedFile($path, 'radius-export.json', 'application/json', null, true);

        $response = $this->actingAs(User::where('role', User::ROLE_ADMIN)->first())
            ->post('/admin/import-export/upload', ['kind' => 'bundle', 'file' => $upload]);

        $response->assertRedirect();

        // Nothing has been applied - the redirect goes to a review screen that
        // says what is in the file.
        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('What this file holds')
            ->assertSee('Run the import');
    }

    public function test_a_file_that_is_not_an_export_is_refused(): void
    {
        $bogus = UploadedFile::fake()->createWithContent('notes.json', 'this is not JSON at all');

        $response = $this->actingAs($this->admin())
            ->post('/admin/import-export/upload', ['kind' => 'bundle', 'file' => $bogus]);

        $this->followRedirects($response)->assertSee('neither a Radius export archive nor readable JSON');
    }

    public function test_a_wordpress_file_is_not_accepted_as_a_bundle(): void
    {
        $file = UploadedFile::fake()->createWithContent('export.xml', '<rss></rss>');

        $this->actingAs($this->admin())
            ->post('/admin/import-export/upload', ['kind' => 'bundle', 'file' => $file])
            ->assertSessionHas('error');
    }
}
