<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * The switchboard for optional features.
 *
 * Toggling a module changes which routes exist, so the route and view caches
 * are dropped afterwards; leaving a stale cached route table in place would
 * keep a disabled module reachable.
 */
class ModuleController extends Controller
{
    public function __construct(private ModuleManager $modules) {}

    public function index(): View
    {
        $modules = collect($this->modules->all())
            ->map(function (array $module, string $slug) {
                return $module + [
                    'dependents' => $this->modules->dependents($slug),
                    'requires' => $module['requires'] ?? [],
                    'usage' => $this->usageFor($slug),
                ];
            });

        return view('admin.modules.index', [
            'modules' => $modules,
        ]);
    }

    public function toggle(string $slug): RedirectResponse
    {
        abort_unless(array_key_exists($slug, config('cms.modules', [])), 404);

        $wasEnabled = $this->modules->enabled($slug);
        $name = $this->modules->name($slug);

        try {
            if ($wasEnabled) {
                $alsoDisabled = $this->modules->disable($slug);
                $message = "{$name} is now off.";

                if ($alsoDisabled !== []) {
                    $names = implode(', ', array_map(fn ($s) => $this->modules->name($s), $alsoDisabled));
                    $message .= " {$names} was also switched off because it depends on {$name}.";
                }
            } else {
                $alsoEnabled = $this->modules->enable($slug);
                $message = "{$name} is now on.";

                if ($alsoEnabled !== []) {
                    $names = implode(', ', array_map(fn ($s) => $this->modules->name($s), $alsoEnabled));
                    $message .= " {$names} was switched on because {$name} requires it.";
                }
            }
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->refreshCaches();

        activity('module.toggled', $message, properties: ['module' => $slug, 'enabled' => ! $wasEnabled]);

        return back()->with('status', $message);
    }

    /**
     * Route registration reads module state, so cached routes and compiled
     * views both have to go.
     */
    private function refreshCaches(): void
    {
        try {
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('config:clear');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * How much content each module holds, so an admin can see what turning it
     * off will hide. Nothing is deleted by disabling a module.
     */
    private function usageFor(string $slug): ?string
    {
        try {
            return match ($slug) {
                'blog' => \App\Models\Post::count().' posts',
                'pages' => \App\Models\Page::count().' pages',
                'shop' => \App\Models\Product::count().' products, '.\App\Models\Order::count().' orders',
                'media' => \App\Models\Media::count().' files',
                'users' => \App\Models\User::count().' accounts',
                'newsletter' => \App\Models\Subscriber::count().' subscribers',
                'contact' => \App\Models\ContactSubmission::count().' messages',
                'payments' => \App\Models\PaymentGateway::where('is_enabled', true)->count().' gateways live',
                'firebase' => \App\Models\PushDevice::count().' registered devices',
                'themes' => \App\Models\Theme::count().' installed',
                default => null,
            };
        } catch (\Throwable $e) {
            return null;
        }
    }
}
