<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\ContactSubmission;
use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The admin landing page.
 *
 * Every panel is guarded by its module, so the dashboard of a blog-only site
 * shows no empty shop widgets - and runs no shop queries either.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => $this->stats(),
            'recentOrders' => $this->recentOrders(),
            'salesChart' => $this->salesChart(),
            'lowStock' => $this->lowStock(),
            'needsAttention' => $this->needsAttention(),
            'recentActivity' => ActivityLog::with('user')->latest()->limit(8)->get(),
        ]);
    }

    /** @return array<string, array{label: string, value: string|int, hint: ?string, icon: string}> */
    private function stats(): array
    {
        $stats = [];

        if (modules()->enabled('shop')) {
            $paidThisMonth = Order::paid()
                ->where('paid_at', '>=', now()->startOfMonth())
                ->sum('grand_total');

            $stats['revenue'] = [
                'label' => 'Revenue this month',
                'value' => money((int) $paidThisMonth),
                'hint' => Order::paid()->where('paid_at', '>=', now()->startOfMonth())->count().' paid orders',
                'icon' => 'currency',
            ];

            $stats['orders'] = [
                'label' => 'Orders',
                'value' => Order::count(),
                'hint' => Order::where('status', 'pending')->count().' awaiting action',
                'icon' => 'shopping-bag',
            ];

            $stats['products'] = [
                'label' => 'Products',
                'value' => Product::count(),
                'hint' => Product::published()->count().' published',
                'icon' => 'cube',
            ];
        }

        if (modules()->enabled('blog')) {
            $stats['posts'] = [
                'label' => 'Blog posts',
                'value' => Post::count(),
                'hint' => Post::published()->count().' published',
                'icon' => 'newspaper',
            ];
        }

        $stats['users'] = [
            'label' => 'Users',
            'value' => User::count(),
            'hint' => User::where('created_at', '>=', now()->subDays(30))->count().' new in 30 days',
            'icon' => 'users',
        ];

        return $stats;
    }

    private function recentOrders()
    {
        if (modules()->disabled('shop')) {
            return collect();
        }

        return Order::with('user')->latest()->limit(8)->get();
    }

    /**
     * Paid revenue per day for the last 30 days, zero-filled so the chart has
     * a continuous x-axis rather than gaps on quiet days.
     *
     * @return array{labels: string[], values: int[]}
     */
    private function salesChart(): array
    {
        if (modules()->disabled('shop')) {
            return ['labels' => [], 'values' => []];
        }

        $rows = Order::paid()
            ->where('paid_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->get([
                DB::raw('DATE(paid_at) as day'),
                DB::raw('SUM(grand_total) as total'),
            ])
            ->keyBy('day');

        $labels = [];
        $values = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $key = $date->toDateString();

            $labels[] = $date->format('d M');
            $values[] = (int) ($rows[$key]->total ?? 0) / 100;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function lowStock()
    {
        if (modules()->disabled('shop') || ! setting('shop_stock_management', true)) {
            return collect();
        }

        return Product::published()
            ->where('manage_stock', true)
            ->where('stock', '<=', (int) setting('shop_low_stock_threshold', 5))
            ->orderBy('stock')
            ->limit(6)
            ->get(['id', 'name', 'slug', 'stock']);
    }

    /** Items waiting on a human, shown as a to-do list. */
    private function needsAttention(): array
    {
        $items = [];

        if (modules()->enabled('blog')) {
            $pending = Comment::pending()->count();

            if ($pending > 0) {
                $items[] = [
                    'label' => "{$pending} comment".($pending === 1 ? '' : 's').' awaiting moderation',
                    'url' => route('admin.comments.index'),
                ];
            }
        }

        if (modules()->enabled('shop')) {
            $pending = Order::where('status', 'pending')->count();

            if ($pending > 0) {
                $items[] = [
                    'label' => "{$pending} order".($pending === 1 ? '' : 's').' to process',
                    'url' => route('admin.orders.index'),
                ];
            }
        }

        if (modules()->enabled('contact')) {
            $unread = ContactSubmission::unread()->count();

            if ($unread > 0) {
                $items[] = [
                    'label' => "{$unread} unread contact message".($unread === 1 ? '' : 's'),
                    'url' => route('admin.contact.index'),
                ];
            }
        }

        if (modules()->enabled('newsletter')) {
            $recent = Subscriber::subscribed()->where('created_at', '>=', now()->subDays(7))->count();

            if ($recent > 0) {
                $items[] = [
                    'label' => "{$recent} new newsletter subscriber".($recent === 1 ? '' : 's').' this week',
                    'url' => route('admin.subscribers.index'),
                ];
            }
        }

        // A site running without a second factor on its admin account is the
        // single most common way these installs get taken over.
        if (auth()->user()->isAdmin() && ! auth()->user()->hasTwoFactorEnabled()) {
            $items[] = [
                'label' => 'Your account has no two-factor authentication',
                'url' => route('two-factor.setup'),
            ];
        }

        if (config('app.debug') && app()->environment('production')) {
            $items[] = [
                'label' => 'APP_DEBUG is on in production - turn it off in .env',
                'url' => route('admin.system.index'),
            ];
        }

        return $items;
    }
}
