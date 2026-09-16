<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoBlogSeeder;
use Database\Seeders\Demo\DemoEngagementSeeder;
use Database\Seeders\Demo\DemoMediaFactory;
use Database\Seeders\Demo\DemoOrderSeeder;
use Database\Seeders\Demo\DemoPageSeeder;
use Database\Seeders\Demo\DemoShopSeeder;
use Illuminate\Database\Seeder;

/**
 * A shop and a blog with enough in them to actually test against: posts,
 * pages, products, categories, tags, comments, reviews, variants and the
 * placeholder images they all point at - and, behind the catalogue, three
 * months of customers, orders and messages, so that the admin's dashboard,
 * reports and inbox have something to show rather than a row of zeroes.
 *
 * Deliberately not called by DatabaseSeeder. A fresh install should not come
 * with fifteen invented products in it; this runs only when somebody asks for
 * it, through `php artisan cms:demo`.
 *
 * Re-running it restores the demo records to their original state, which is
 * what makes it useful for resetting a database you have been poking at. Your
 * own content is keyed on different slugs and is left alone.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        // One factory for the whole run, shared through the container, so an
        // image wanted by two seeders is drawn and registered once.
        $media = new DemoMediaFactory;
        app()->instance(DemoMediaFactory::class, $media);

        if (! $media->canDraw()) {
            $this->command?->warn('GD is not available, so demo records will have no images. Everything else still loads.');
        }

        $this->call(DemoPageSeeder::class);

        if (modules()->enabled('blog')) {
            $this->call(DemoBlogSeeder::class);
        } else {
            $this->command?->warn('The blog module is off, so no posts were created.');
        }

        if (modules()->enabled('shop')) {
            $this->call(DemoShopSeeder::class);
            // After the catalogue, because every order line points at a product.
            $this->call(DemoOrderSeeder::class);
        } else {
            $this->command?->warn('The shop module is off, so no products or orders were created.');
        }

        $this->call(DemoEngagementSeeder::class);
    }
}
