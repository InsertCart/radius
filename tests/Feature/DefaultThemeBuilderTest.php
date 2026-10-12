<?php

namespace Tests\Feature;

use App\Cms\Builder\RegionManager;
use App\Cms\Builder\RegionStarter;
use App\Cms\Themes\ThemeManager;
use App\Cms\Themes\ThemeSections;
use App\Models\Layout;
use App\Models\Post;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The bundled default theme in the builder.
 *
 * It used to offer the builder nothing of its own: the homepage could not be
 * edited at all, and the blog index, header and footer opened as an empty
 * canvas while the live site plainly showed the theme's version. Each of them
 * now opens as an editable copy of what the theme shows.
 */
class DefaultThemeBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');

        themes()->sync();
    }

    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'owner@x.test'], [
            'name' => 'Owner', 'password' => Hash::make('password-123'),
            'role' => User::ROLE_ADMIN, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function posts(int $count): void
    {
        foreach (range(1, $count) as $number) {
            Post::create([
                'title' => "Field note {$number}",
                'slug' => "field-note-{$number}",
                'status' => 'published',
                'published_at' => now()->subMinutes($number),
                'content' => 'Sample content.',
            ]);
        }
    }

    /** Publish a region from the theme's own starter, after adjusting one section's settings. */
    private function publish(string $region, array $settings = [], ?callable $adjust = null): void
    {
        $tree = app(RegionStarter::class)->build($region, 'theme-default');
        $this->assertNotSame([], $tree, "The default theme has no starter for [{$region}].");

        foreach ($tree as $index => $section) {
            $widget = $section['elements'][0]['elements'][0]['settings'];

            foreach ($settings as $key => $value) {
                if (str_starts_with($key, $widget['section'].'__')) {
                    $tree[$index]['elements'][0]['elements'][0]['settings'][$key] = $value;
                }
            }
        }

        Layout::forRegion($region)->publish($adjust ? $adjust($tree) : $tree, $this->admin()->id);
        app(RegionManager::class)->flush();
    }

    /** The section keys a tree holds, in order. */
    private function sectionsIn(array $tree): array
    {
        return array_map(fn ($section) => $section['elements'][0]['elements'][0]['settings']['section'], $tree);
    }

    public function test_every_part_opens_as_the_themes_own_version_rather_than_empty(): void
    {
        foreach (['home', 'hero', 'header', 'footer', 'blog_index'] as $region) {
            $this->actingAs($this->admin())
                ->get(route('admin.builder.region', $region))
                ->assertOk();

            $layout = Layout::forRegion($region);

            $this->assertSame('theme-section', $layout->editableTree()[0]['elements'][0]['elements'][0]['widgetType'], $region);
            $this->assertNull($layout->published_at, "Opening [{$region}] must not put anything live.");
        }

        $this->assertSame(
            ['hero', 'featured_products', 'latest_posts'],
            $this->sectionsIn(Layout::forRegion('home')->editableTree())
        );
    }

    public function test_the_homepage_looks_the_same_before_anything_is_built(): void
    {
        $this->posts(4);

        $this->get('/')
            ->assertOk()
            ->assertSee('From the blog')
            ->assertSee('Field note 1')
            ->assertSee('Field note 3')
            ->assertDontSee('Field note 4')
            ->assertDontSee('cb-region', false);
    }

    public function test_the_number_of_posts_on_the_homepage_can_be_changed(): void
    {
        $this->posts(6);

        $this->publish('home', ['latest_posts__count' => 5, 'latest_posts__title' => 'Fresh off the press']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Fresh off the press')
            ->assertSee('Field note 5')
            ->assertDontSee('Field note 6');
    }

    public function test_a_homepage_section_can_be_removed(): void
    {
        $this->posts(2);

        // Keep the hero only, as an admin deleting the other sections would.
        $this->publish('home', [], fn (array $tree) => [$tree[0]]);

        $this->get('/')
            ->assertOk()
            ->assertSee(setting('site_name', config('app.name')))
            ->assertDontSee('From the blog')
            ->assertDontSee('Field note 1');
    }

    public function test_the_blog_index_keeps_its_page_and_takes_the_builders_settings(): void
    {
        $this->posts(5);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('Thoughts, updates and announcements.')
            ->assertSee('Field note 5');

        $this->publish('blog_index', [
            'blog_list__title' => 'The journal',
            'blog_list__per_page' => 2,
            'blog_list__show_search' => false,
        ]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('The journal')
            ->assertSee('Field note 2')
            ->assertDontSee('Field note 3')
            ->assertDontSee('placeholder="Search posts"', false)
            ->assertSee(route('blog.index').'?page=2', false);

        $this->get(route('blog.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Field note 3')
            ->assertDontSee('Field note 2');
    }

    public function test_parts_of_the_header_and_footer_can_be_hidden(): void
    {
        $this->get('/')->assertSee('aria-label="Cart"', false)->assertSee('Powered by');

        $this->publish('header', ['header__show_cart' => false]);
        $this->publish('footer', ['footer__show_credit' => false, 'footer__copyright' => 'Made in Jaipur']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('aria-label="Cart"', false)
            ->assertDontSee('Powered by')
            ->assertSee('Made in Jaipur');
    }

    public function test_a_starter_leaves_out_sections_whose_module_is_off(): void
    {
        // Rows first: with none, every module merely counts as on.
        modules()->sync();
        modules()->disable('shop');

        $this->assertTrue(modules()->enabled('blog'));

        $this->assertSame(
            ['hero', 'latest_posts'],
            $this->sectionsIn(app(RegionStarter::class)->build('home', 'theme-default'))
        );
    }

    public function test_a_whole_page_is_previewed_with_the_header_and_footer_marked(): void
    {
        $this->actingAs($this->admin());

        // The homepage is a page, so it is shown inside the theme's layout and
        // the regions around it are marked for the editor to link to.
        $this->get(route('admin.builder.preview.region', 'home'))
            ->assertOk()
            ->assertSee('<!--cb-region:header-->', false)
            ->assertSee('<!--cb-region:footer-->', false)
            ->assertSee('<!--/cb-region-->', false);

        // The header itself is a fragment: no layout around it, nothing to mark.
        $this->get(route('admin.builder.preview.region', 'header'))
            ->assertOk()
            ->assertDontSee('cb-region:', false);

        // And the marking never outlives the canvas it was switched on for.
        $this->get(route('admin.builder.preview.region', 'home'))->assertOk();
        $this->get('/')->assertOk()->assertDontSee('cb-region', false);
    }

    public static function bundledThemes(): array
    {
        return [['storefront'], ['zenith'], ['shipnex'], ['casino']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bundledThemes')]
    public function test_every_bundled_theme_previews_its_homepage_inside_its_own_layout(string $slug): void
    {
        Theme::query()->update(['is_active' => false]);
        Theme::where('slug', $slug)->update(['is_active' => true]);
        Cache::flush();

        foreach ([ThemeManager::class, ThemeSections::class, RegionManager::class] as $service) {
            app()->forgetInstance($service);
        }

        themes()->registerViewNamespace();

        $this->actingAs($this->admin());

        $this->get(route('admin.builder.region', 'home'))->assertOk();

        $this->get(route('admin.builder.preview.region', 'home'))
            ->assertOk()
            ->assertSee('<!--cb-region:header-->', false)
            ->assertSee('data-cb-widget', false);
    }

    public function test_the_editor_names_the_page_and_lists_the_other_parts(): void
    {
        // Emptied on purpose, so the "nothing built" note is what is shown.
        Layout::forRegion('blog_index')->publish([], $this->admin()->id);

        $this->actingAs($this->admin())
            ->get(route('admin.builder.region', 'blog_index'))
            ->assertOk()
            ->assertSee('Your theme\'s blog index is in use', false)
            ->assertDontSee('blog_index is in use')
            ->assertSee('id="cb-part-switch"', false)
            ->assertSee('Editing: Blog index')
            ->assertSee('Homepage (whole page)');
    }
}
