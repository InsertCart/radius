<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The installer and demo seeders write a lot of columns through mass
 * assignment. Run under the strict mode set in TestCase, so a column left out
 * of a model's $fillable fails here instead of being quietly lost on a
 * buyer's site.
 */
class SeedersStrictTest extends TestCase
{
    use RefreshDatabase;

    public function test_installer_and_demo_seeders_lose_no_attributes(): void
    {
        $this->seed(DatabaseSeeder::class);

        // The demo seeder skips the shop and blog when their modules are off.
        foreach (array_keys(modules()->all()) as $slug) {
            modules()->enable($slug);
        }

        $this->seed(DemoContentSeeder::class);

        $this->assertDatabaseHas('products', []);
        $this->assertDatabaseHas('orders', []);
    }
}
