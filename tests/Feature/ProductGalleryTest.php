<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The product form posts gallery[] as media ids in display order. Saving must
 * keep that order, and only ever touch the "gallery" collection.
 */
class ProductGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');
    }

    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'owner@x.test'], [
            'name' => 'Owner', 'email' => 'owner@x.test', 'password' => Hash::make('password-123'),
            'role' => User::ROLE_ADMIN, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function media(string $name): Media
    {
        return Media::create([
            'name' => $name, 'file_name' => "{$name}.jpg", 'mime_type' => 'image/jpeg',
            'extension' => 'jpg', 'size' => 100, 'disk' => 'public', 'path' => "media/{$name}.jpg",
        ]);
    }

    private function save(Product $product, array $gallery): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), [
                'name' => 'Mug', 'slug' => 'mug', 'price' => 10, 'type' => 'simple',
                'status' => 'published', 'gallery' => $gallery,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_gallery_saves_in_the_submitted_order_and_can_be_cleared(): void
    {
        $product = Product::create(['name' => 'Mug', 'slug' => 'mug', 'price' => 10, 'type' => 'simple', 'status' => 'published']);
        [$a, $b, $c] = [$this->media('a'), $this->media('b'), $this->media('c')];

        $this->save($product, [$c->id, $a->id, $b->id]);
        $this->assertSame([$c->id, $a->id, $b->id], $product->fresh()->gallery->pluck('id')->all());

        $this->save($product->fresh(), [$b->id, $c->id]);
        $this->assertSame([$b->id, $c->id], $product->fresh()->gallery->pluck('id')->all());

        $this->save($product->fresh(), []);
        $this->assertCount(0, $product->fresh()->gallery);
    }

    public function test_saving_the_gallery_leaves_other_media_collections_alone(): void
    {
        $product = Product::create(['name' => 'Mug', 'slug' => 'mug', 'price' => 10, 'type' => 'simple', 'status' => 'published']);
        $other = $this->media('other');
        $product->morphToMany(Media::class, 'mediable')->attach($other->id, ['collection' => 'default']);

        $this->save($product, [$this->media('a')->id]);
        $this->save($product->fresh(), []);

        $this->assertDatabaseHas('mediables', ['media_id' => $other->id, 'collection' => 'default']);
    }
}
