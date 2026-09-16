<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\ContactSubmission;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\Demo\DemoBlogSeeder;
use Database\Seeders\Demo\DemoEngagementSeeder;
use Database\Seeders\Demo\DemoMediaFactory;
use Database\Seeders\Demo\DemoOrderSeeder;
use Database\Seeders\Demo\DemoPageSeeder;
use Database\Seeders\Demo\DemoShopSeeder;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Installs - or removes again - a body of sample content to test against.
 *
 * Removal is exact rather than approximate: the seeders own a fixed list of
 * slugs, so `--remove` deletes those and nothing else. Content you wrote
 * yourself survives, because it is not on the list.
 */
class DemoContentCommand extends Command
{
    protected $signature = 'cms:demo
                            {--remove : Delete the demo content instead of creating it}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Install sample posts, pages, products and orders for testing, or remove them again';

    public function handle(): int
    {
        return $this->option('remove') ? $this->remove() : $this->install();
    }

    private function install(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->warn('This site is running in production.');

            if (! $this->confirm('Add sample content to a production site?', false)) {
                return self::FAILURE;
            }
        }

        $this->components->info('Installing demo content...');

        Artisan::call(
            'db:seed',
            ['--class' => DemoContentSeeder::class, '--force' => true],
            $this->getOutput()
        );

        $this->newLine();
        $this->components->info('Done. The demo content is in place.');
        $this->summary();

        $this->newLine();
        $this->line('  Remove it again at any time with <options=bold>php artisan cms:demo --remove</>');

        return self::SUCCESS;
    }

    private function remove(): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete all demo posts, pages, products and orders? Content you created yourself is not touched.', true)) {
            return self::FAILURE;
        }

        // force-deleted rather than soft-deleted: leaving trashed rows behind
        // would block the slugs if the demo is ever reinstalled.
        $posts = Post::withTrashed()->whereIn('slug', DemoBlogSeeder::postSlugs())->get()
            ->each->forceDelete()->count();

        $pages = Page::withTrashed()->whereIn('slug', DemoPageSeeder::pageSlugs())->get()
            ->each->forceDelete()->count();

        $products = Product::withTrashed()->whereIn('slug', DemoShopSeeder::productSlugs())->get()
            ->each->forceDelete()->count();

        // Orders go before the customers who placed them: deleting a user
        // only nulls the order's user_id, and the order itself would stay.
        $orders = Order::where('order_number', 'like', DemoOrderSeeder::PREFIX.'%')->delete();

        $coupons = Coupon::whereIn('code', DemoOrderSeeder::couponCodes())->delete();

        $customers = User::withTrashed()
            ->whereIn('email', DemoOrderSeeder::customerEmails())
            ->get()->each->forceDelete()->count();

        $messages = ContactSubmission::whereIn('email', DemoEngagementSeeder::submissionEmails())->delete();

        $subscribers = Subscriber::whereIn('email', DemoEngagementSeeder::subscriberEmails())->delete();

        $logs = ActivityLog::whereIn('description', DemoEngagementSeeder::activityDescriptions())->delete();

        $categories = Category::where('type', Category::TYPE_BLOG)
            ->whereIn('slug', array_column(DemoBlogSeeder::categories(), 'slug'))
            ->delete();

        $categories += Category::where('type', Category::TYPE_SHOP)
            ->whereIn('slug', DemoShopSeeder::categorySlugs())
            ->delete();

        $tags = Tag::whereIn('slug', array_keys(DemoBlogSeeder::tags()))->delete();

        $authors = User::withTrashed()
            ->whereIn('email', array_column(DemoBlogSeeder::authors(), 'email'))
            ->get()->each->forceDelete()->count();

        $images = (new DemoMediaFactory)->purge();

        $this->components->info('Demo content removed.');

        $this->components->twoColumnDetail('Posts', (string) $posts);
        $this->components->twoColumnDetail('Pages', (string) $pages);
        $this->components->twoColumnDetail('Products', (string) $products);
        $this->components->twoColumnDetail('Orders', (string) $orders);
        $this->components->twoColumnDetail('Coupons', (string) $coupons);
        $this->components->twoColumnDetail('Categories', (string) $categories);
        $this->components->twoColumnDetail('Tags', (string) $tags);
        $this->components->twoColumnDetail('Demo authors', (string) $authors);
        $this->components->twoColumnDetail('Demo customers', (string) $customers);
        $this->components->twoColumnDetail('Contact messages', (string) $messages);
        $this->components->twoColumnDetail('Subscribers', (string) $subscribers);
        $this->components->twoColumnDetail('Activity log entries', (string) $logs);
        $this->components->twoColumnDetail('Images', (string) $images);

        return self::SUCCESS;
    }

    /** What is now in the database, so the numbers can be checked at a glance. */
    private function summary(): void
    {
        $this->newLine();

        $this->components->twoColumnDetail(
            'Posts',
            Post::whereIn('slug', DemoBlogSeeder::postSlugs())->count().' created'
        );
        $this->components->twoColumnDetail(
            'Pages',
            Page::whereIn('slug', DemoPageSeeder::pageSlugs())->count().' created'
        );
        $this->components->twoColumnDetail(
            'Products',
            Product::whereIn('slug', DemoShopSeeder::productSlugs())->count().' created'
        );

        $orders = Order::where('order_number', 'like', DemoOrderSeeder::PREFIX.'%');

        $this->components->twoColumnDetail(
            'Orders',
            (clone $orders)->count().' created, '.money((int) $orders->paid()->sum('grand_total')).' paid'
        );
        $this->components->twoColumnDetail(
            'Customers',
            User::whereIn('email', DemoOrderSeeder::customerEmails())->count().' created'
        );
        $this->components->twoColumnDetail(
            'Contact messages',
            ContactSubmission::whereIn('email', DemoEngagementSeeder::submissionEmails())->count().' created'
        );
        $this->components->twoColumnDetail(
            'Subscribers',
            Subscriber::whereIn('email', DemoEngagementSeeder::subscriberEmails())->count().' created'
        );
    }
}
