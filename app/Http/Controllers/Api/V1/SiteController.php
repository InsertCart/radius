<?php

namespace App\Http\Controllers\Api\V1;

use App\Cms\Api\ApiManager;
use App\Http\Controllers\Api\ApiController;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What an app asks for first: who this site is, what it is willing to answer,
 * and the handful of settings the app has to agree with to behave correctly.
 *
 * Always available while the API is on - it is how an app finds out which of
 * the other endpoints exist, so gating it behind a toggle would be circular.
 * It publishes nothing a visitor could not read off the front page.
 */
class SiteController extends ApiController
{
    public function __construct(private ApiManager $api) {}

    public function show(Request $request): JsonResponse
    {
        return $this->data([
            'name' => setting('site_name', config('app.name')),
            'tagline' => setting('site_tagline'),
            'url' => url('/'),
            'email' => setting('site_email'),
            'phone' => setting('site_phone'),
            'address' => setting('site_address'),

            'logo' => site_logo_url(),
            'logo_light' => site_logo_light_url(),
            'favicon' => site_favicon_url(),
            'primary_color' => setting('primary_color', '#2563eb'),
            'color_scheme' => site_color_scheme(),

            'locale' => setting('locale', 'en'),
            'timezone' => setting('timezone', config('app.timezone')),
            'date_format' => setting('date_format', 'd M Y'),

            'currency' => [
                'code' => setting('shop_currency', 'USD'),
                'symbol' => setting('shop_currency_symbol', '$'),
                'position' => setting('shop_currency_position', 'before'),
            ],

            // What this app may call. An app reads this instead of hard-coding
            // assumptions, so switching the shop off in the admin panel makes
            // the shop tab disappear rather than fail.
            'features' => $this->api->enabledFeatures(),

            'account' => [
                'registration_open' => (bool) setting('registration_enabled', true)
                    && $this->api->feature('registration'),
                'email_verification_required' => (bool) setting('email_verification', false),
                'guest_cart' => $this->api->allowsGuestCart(),
            ],

            'shop' => $this->api->feature('shop') ? [
                'guest_checkout' => (bool) setting('shop_guest_checkout', true),
                'tax_inclusive' => (bool) setting('shop_tax_inclusive', false),
                'terms_url' => terms_url(),
            ] : null,

            'social' => array_filter([
                'facebook' => setting('social_facebook'),
                'instagram' => setting('social_instagram'),
                'twitter' => setting('social_twitter'),
                'linkedin' => setting('social_linkedin'),
                'youtube' => setting('social_youtube'),
                'whatsapp' => whatsapp_url(),
            ]),

            'maintenance' => [
                'active' => (bool) setting('maintenance_mode', false),
                'message' => setting('maintenance_message'),
            ],

            'cms' => [
                'product' => config('cms.name'),
                'version' => cms_version(),
                'api_version' => $this->api->version(),
            ],
        ]);
    }

    /**
     * The site's menus, so an app can build its navigation from the same
     * source the website uses.
     */
    public function menus(Request $request): JsonResponse
    {
        $menus = Menu::with('tree.children')->get()->mapWithKeys(fn (Menu $menu) => [
            $menu->slug => [
                'name' => $menu->name,
                'items' => $menu->tree
                    ->filter(fn (MenuItem $item) => $item->isVisible())
                    ->map(fn (MenuItem $item) => $this->menuItem($item))
                    ->values()
                    ->all(),
            ],
        ])->all();

        return $this->data($menus);
    }

    private function menuItem(MenuItem $item): array
    {
        return [
            'label' => $item->label,
            'url' => $item->resolveUrl(),
            'type' => $item->type,
            'target' => $item->target,
            'icon' => $item->icon,
            'children' => $item->children
                ->filter(fn (MenuItem $child) => $child->isVisible())
                ->map(fn (MenuItem $child) => $this->menuItem($child))
                ->values()
                ->all(),
        ];
    }
}
