<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Opens every GET page as a guest, a customer and an administrator against the
 * demo content, and fails if any of them throws. Catches the kind of bug where
 * a feature is half-wired - a missing route, view or column - and only blows
 * up once somebody actually visits the page.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** Pages that need a live gateway, a signed link or the outside world. */
    private const SKIP = [
        'install', 'email/verify/{id}/{hash}', 'reset-password/{token}',
        'checkout/return/{gateway}/{order}', 'checkout/cancel/{gateway}/{order}',
        'newsletter/unsubscribe/{token}', 'admin/updates/backups/{name}',
        'account/orders/{order}/download/{item}', 'up',
    ];

    public function test_no_page_throws(): void
    {
        Http::fake();

        $this->get('/');
        $this->seed(DatabaseSeeder::class);
        foreach (array_keys(modules()->all()) as $slug) {
            modules()->enable($slug);
        }
        $this->seed(DemoContentSeeder::class);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'smoke-admin@example.test', 'password' => Hash::make('x'),
            'role' => User::ROLE_ADMIN, 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $customer = Order::whereNotNull('user_id')->first()?->user ?? User::create([
            'name' => 'Customer', 'email' => 'smoke-customer@example.test', 'password' => Hash::make('x'),
            'role' => User::ROLE_CUSTOMER, 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $customer->forceFill(['status' => 'active', 'email_verified_at' => now()])->save();

        $failures = [];

        foreach (app('router')->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (collect(self::SKIP)->contains(fn ($s) => $route->uri() === $s || str_starts_with($route->uri(), $s.'/'))) {
                continue;
            }

            $uri = $this->fill($route, $customer);
            if ($uri === null) {
                continue;
            }

            $as = match (true) {
                str_starts_with($route->uri(), 'admin') => $admin,
                str_starts_with($route->uri(), 'account') => $customer,
                default => null,
            };

            foreach ($as ? [$as] : [null, $customer] as $user) {
                $this->flushSession();
                $request = $user ? $this->actingAs($user)->withSession(['auth.two_factor_confirmed' => true]) : $this;
                $response = $request->get('/'.ltrim($uri, '/'));
                app('auth')->forgetGuards();

                if ($response->getStatusCode() >= 500) {
                    $e = $response->exception;
                    $failures[] = sprintf('%s [%s] %s: %s @ %s:%d', $uri, $user?->role ?? 'guest',
                        $e ? class_basename($e) : $response->getStatusCode(),
                        $e?->getMessage(), $e ? str_replace(base_path(), '', $e->getFile()) : '', $e?->getLine());
                }
            }
        }

        $this->assertSame([], $failures, "Pages that crashed:\n".implode("\n", $failures));
    }

    private function fill(Route $route, User $customer): ?string
    {
        $uri = $route->uri();
        $order = Order::where('user_id', $customer->id)->first() ?? Order::first();
        $firstLayout = \App\Models\Layout::first();

        $values = [
            'slug' => match (true) {
                str_starts_with($uri, 'blog/category') => Category::where('type', 'post')->value('slug') ?? Category::value('slug'),
                str_starts_with($uri, 'shop/category') => Category::where('type', 'product')->value('slug') ?? Category::value('slug'),
                str_starts_with($uri, 'blog/tag') => Tag::value('slug'),
                str_starts_with($uri, 'blog') => Post::value('slug'),
                str_starts_with($uri, 'shop') => Product::value('slug'),
                str_starts_with($uri, 'admin/themes') => 'storefront',
                default => Page::value('slug'),
            },
            'order' => $order?->getRouteKey(),
            'gateway' => \App\Models\PaymentGateway::first()?->getRouteKey(),
            'type' => 'page',
            'id' => Page::value('id'),
            'region' => 'header',
            'key' => 'default',
            'layout' => $firstLayout?->getRouteKey(),
            'group' => null,
        ];

        foreach ($route->signatureParameters(Model::class) as $param) {
            $class = $param->getType()?->getName();
            if ($class && ! isset($values[$param->getName()]) && ($model = $class::first())) {
                $values[$param->getName()] = $model->getRouteKey();
            }
        }

        $missing = false;
        $uri = preg_replace_callback('/\{(\w+)(\?)?\}/', function ($m) use ($values, &$missing) {
            $v = $values[$m[1]] ?? null;
            if ($v === null && empty($m[2])) {
                $missing = true;
            }

            return (string) $v;
        }, $uri);

        return $missing ? null : rtrim($uri, '/');
    }
}
