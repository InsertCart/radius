<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Marketplace\MarketplaceException;
use App\Cms\Marketplace\MarketplaceItem;
use App\Cms\Plugins\PluginCatalogClient;
use App\Cms\Plugins\PluginInstallException;
use App\Cms\Plugins\PluginMarketplaceInstaller;
use App\Http\Controllers\Controller;
use App\Models\Plugin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * System -> Browse plugins: the plugin directory. Free plugins install in one
 * click; paid ones link to where they are sold.
 */
class PluginMarketplaceController extends Controller
{
    public function __construct(
        private PluginCatalogClient $catalog,
        private PluginMarketplaceInstaller $installer,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureEnabled();

        $catalog = $this->catalog->cached();
        $query = trim((string) $request->query('q', ''));
        $tag = trim((string) $request->query('tag', ''));

        return view('admin.plugins.marketplace.index', [
            'catalog' => $catalog,
            'items' => $catalog?->search($query, $tag) ?? [],
            'tags' => $catalog?->tags() ?? [],
            'query' => $query,
            'tag' => $tag,
            'error' => $catalog ? null : $this->catalog->lastError(),
            'installed' => Plugin::all()->keyBy('slug'),
            'lastChecked' => $this->catalog->lastCheckedAt(),
            'remoteImages' => (bool) config('marketplace.remote_images', true),
        ]);
    }

    public function show(string $slug): View
    {
        $item = $this->item($slug);

        return view('admin.plugins.marketplace.show', [
            'item' => $item,
            'status' => $this->installer->status($item),
            'remoteImages' => (bool) config('marketplace.remote_images', true),
        ]);
    }

    public function install(string $slug): RedirectResponse
    {
        $item = $this->item($slug);

        try {
            $result = $this->installer->install($item);
        } catch (MarketplaceException|PluginInstallException $e) {
            activity('plugin.marketplace.rejected', "Could not install {$item->name} from the plugin directory.",
                properties: ['slug' => $item->slug, 'version' => $item->version, 'reason' => $e->getMessage()]);

            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The plugin could not be installed. Check the logs for details.');
        }

        $verb = $result['updated'] ? 'Updated' : 'Installed';

        activity(
            $result['updated'] ? 'plugin.marketplace.updated' : 'plugin.marketplace.installed',
            "{$verb} the {$result['name']} plugin from the plugin directory.",
            properties: ['slug' => $result['slug'], 'version' => $result['version']]
        );

        return redirect()->route('admin.plugins.index')->with('status', $result['updated']
            ? "{$result['name']} updated to version {$result['version']}."
            : "{$result['name']} installed. Switch it on when you are ready.");
    }

    public function refresh(): RedirectResponse
    {
        $this->ensureEnabled();

        try {
            $count = $this->catalog->refresh()->count();
        } catch (MarketplaceException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The plugin directory could not be loaded. Check the logs for details.');
        }

        return back()->with('status', "The plugin directory lists {$count} plugin".($count === 1 ? '' : 's').'.');
    }

    /** Enforced here rather than by leaving routes out, so it holds with a cached route table. */
    private function ensureEnabled(): void
    {
        abort_unless($this->catalog->enabled(), 404);
    }

    private function item(string $slug): MarketplaceItem
    {
        $this->ensureEnabled();

        $catalog = $this->catalog->cached();

        abort_unless($catalog !== null, 404);

        return $catalog->find($slug) ?? abort(404);
    }
}
