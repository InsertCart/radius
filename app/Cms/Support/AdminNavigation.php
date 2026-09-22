<?php

namespace App\Cms\Support;

use App\Cms\Marketplace\CatalogClient;
use App\Cms\Updates\UpdateChecker;
use App\Models\Comment;
use App\Models\ContactSubmission;
use App\Models\Order;
use App\Models\User;

/**
 * Builds the admin sidebar.
 *
 * Entries whose module is switched off are simply never produced, so the
 * navigation always matches what the routes actually allow. Badge counts are
 * only queried for sections the current user can reach.
 */
class AdminNavigation
{
    /** @return array<string, array<int, array{label: string, url: string, active: bool, badge: ?int, icon: ?string}>> */
    public function build(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $sections = [
            'Overview' => [
                $this->link('Dashboard', 'admin.dashboard', null, null, 'dashboard'),
            ],
            'Content' => $this->contentLinks(),
            'Shop' => $this->shopLinks($user),
            'Audience' => $this->audienceLinks($user),
            'Appearance' => $this->appearanceLinks($user),
            'System' => $this->systemLinks($user),
        ];

        return array_filter($sections, fn ($links) => $links !== []);
    }

    private function contentLinks(): array
    {
        $links = [];

        if (modules()->enabled('pages')) {
            $links[] = $this->link('Pages', 'admin.pages.index', 'admin.pages.*', null, 'pages');
        }

        if (modules()->enabled('blog')) {
            $links[] = $this->link('Posts', 'admin.posts.index', 'admin.posts.*', null, 'posts');
            $links[] = $this->link('Comments', 'admin.comments.index', 'admin.comments.*',
                $this->count(fn () => Comment::pending()->count()), 'comments');
        }

        if (modules()->anyEnabled('blog', 'shop')) {
            $links[] = $this->link('Categories', 'admin.categories.index', 'admin.categories.*', null, 'categories');
        }

        $links[] = $this->link('Menus', 'admin.menus.index', 'admin.menus.*', null, 'menus');
        $links[] = $this->link('Media', 'admin.media.index', 'admin.media.*', null, 'media');

        return $links;
    }

    private function shopLinks(User $user): array
    {
        // Gateway credentials are secrets, so the link follows the route: an
        // editor is not shown a door that answers 403.
        $payments = $user->isAdmin() && modules()->enabled('payments');

        if (modules()->disabled('shop')) {
            // Payments can still be configured for a site that only sells
            // through a custom flow, so it is not nested under the shop.
            return $payments
                ? [$this->link('Payment gateways', 'admin.payments.index', 'admin.payments.*', null, 'payments')]
                : [];
        }

        $links = [
            $this->link('Products', 'admin.products.index', 'admin.products.*', null, 'products'),
            $this->link('Orders', 'admin.orders.index', 'admin.orders.*',
                $this->count(fn () => Order::where('status', 'pending')->count()), 'orders'),
            $this->link('Coupons', 'admin.coupons.index', 'admin.coupons.*', null, 'coupons'),
        ];

        if ($payments) {
            $links[] = $this->link('Payment gateways', 'admin.payments.index', 'admin.payments.*', null, 'payments');
        }

        return $links;
    }

    private function audienceLinks(User $user): array
    {
        // Account management is admin-only.
        $links = $user->isAdmin()
            ? [$this->link('Users', 'admin.users.index', 'admin.users.*', null, 'users')]
            : [];

        if (modules()->enabled('contact')) {
            $links[] = $this->link('Messages', 'admin.contact.index', 'admin.contact.*',
                $this->count(fn () => ContactSubmission::unread()->count()), 'messages');
        }

        if (modules()->enabled('newsletter')) {
            $links[] = $this->link('Subscribers', 'admin.subscribers.index', 'admin.subscribers.*', null, 'subscribers');
        }

        return $links;
    }

    private function appearanceLinks(User $user): array
    {
        $links = [
            $this->link('Builder', 'admin.builder.index', 'admin.builder.*', null, 'builder'),
        ];

        // Installing a theme deploys code, so it is an owner's screen.
        if ($user->isAdmin()) {
            // Badged with the number of installed directory themes that have a
            // newer version. Only themes installed from the directory are
            // looked up, so a site that never used it makes no request.
            $links[] = $this->link('Themes', 'admin.themes.index', 'admin.themes.index', $this->count(
                fn () => count(app(CatalogClient::class)->availableUpdates())
            ), 'themes');

            if (app(CatalogClient::class)->enabled()) {
                $links[] = $this->link('Browse themes', 'admin.themes.marketplace.index', 'admin.themes.marketplace.*', null, 'marketplace');
            }
        }

        if (modules()->enabled('seo')) {
            $links[] = $this->link('SEO', 'admin.seo.index', 'admin.seo.*', null, 'seo');
        }

        return $links;
    }

    private function systemLinks(User $user): array
    {
        $links = [];

        // Modules, settings and diagnostics are admin-only; editors stop at
        // content.
        if ($user->isAdmin()) {
            // Bucket credentials, and buttons that move every file on the
            // site. Follows the route: admin-only, and gone with the module.
            if (modules()->enabled('cdn')) {
                $links[] = $this->link('Media storage', 'admin.cdn.index', 'admin.cdn.*', null, 'cdn');
            }

            // App credentials, and the switchboard for what the API exposes.
            // Follows the route: admin-only, and gone with the module.
            if (modules()->enabled('api')) {
                $links[] = $this->link('Mobile API', 'admin.api.index', 'admin.api.*', null, 'api');
            }

            $links[] = $this->link('Modules', 'admin.modules.index', 'admin.modules.*', null, 'modules');
            $links[] = $this->link('Settings', 'admin.settings.edit', 'admin.settings.*', null, 'settings');
            $links[] = $this->link('System', 'admin.system.index', 'admin.system.*', null, 'system');

            // Badged with a 1 when a newer release is waiting. The check is
            // cached and wrapped, so an unreachable update server can never
            // slow down or break the sidebar.
            $links[] = $this->link('Updates', 'admin.updates.index', 'admin.updates.*',
                $this->count(fn () => app(UpdateChecker::class)->updateAvailable() ? 1 : 0), 'updates');
        }

        return $links;
    }

    private function link(string $label, string $route, ?string $activePattern = null, ?int $badge = null, ?string $icon = null): array
    {
        return [
            'label' => $label,
            'url' => safe_route($route, [], '#'),
            'active' => request()->routeIs($activePattern ?? $route),
            'badge' => $badge ?: null,
            'icon' => $icon,
        ];
    }

    /** Badge counts must never take the panel down if a table is missing. */
    private function count(callable $callback): int
    {
        try {
            return (int) $callback();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
