<?php

namespace Tests\Feature;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Builder\Blocks\Block;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every widget has to survive being dropped on a page before it is filled in.
 *
 * The renderer catches what a widget throws and prints the message on the
 * canvas, so a mistake here is not a white screen - but it is a "this widget
 * could not be rendered" box where a picture should be, which is what an admin
 * sees the moment they drag the widget in.
 */
class BuilderWidgetRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');
    }

    /** Settings a freshly dropped widget has, and the emptiest case of all. */
    public static function settingCases(): array
    {
        return [
            'with its defaults' => [true],
            'with no settings at all' => [false],
        ];
    }

    #[DataProvider('settingCases')]
    public function test_every_widget_renders_before_it_is_configured(bool $withDefaults): void
    {
        $registry = app(BlockRegistry::class);

        // Product widgets need a product in context, as they have on a product page.
        $product = Product::create([
            'name' => 'Sample', 'slug' => 'sample', 'price' => 1000,
            'type' => 'simple', 'status' => 'published',
        ]);

        $rendered = 0;

        foreach ($registry->all() as $type => $class) {
            if (! $class::isAvailable()) {
                continue;
            }

            /** @var Block $block */
            $block = $registry->make($type);
            $settings = $withDefaults ? $class::defaults() : [];

            foreach ([true, false] as $editing) {
                $html = $block->render($settings, [
                    'id' => 'abcd1234',
                    'editing' => $editing,
                    'model' => $product,
                ]);

                $this->assertStringNotContainsString('Missing view', $html,
                    "The \"{$type}\" widget has no view.");

                $rendered++;
            }
        }

        $this->assertGreaterThan(20, $rendered, 'Expected the widget registry to be populated.');
    }

    /** The placeholder is called from the block views, so it cannot be protected. */
    public function test_a_widget_view_can_draw_the_unconfigured_placeholder(): void
    {
        $html = app(BlockRegistry::class)->make('image')
            ->render([], ['id' => 'abcd1234', 'editing' => true]);

        $this->assertStringContainsString('Choose an image', $html);

        // Visitors see nothing at all rather than editor furniture.
        $this->assertSame('', trim(app(BlockRegistry::class)->make('image')
            ->render([], ['id' => 'abcd1234', 'editing' => false])));
    }
}
