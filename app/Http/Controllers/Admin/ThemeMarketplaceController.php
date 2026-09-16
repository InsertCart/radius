<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Marketplace\CatalogClient;
use App\Cms\Marketplace\MarketplaceException;
use App\Cms\Marketplace\MarketplaceInstaller;
use App\Cms\Marketplace\MarketplaceItem;
use App\Cms\Themes\ThemeInstallException;
use App\Http\Controllers\Controller;
use App\Models\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Appearance -> Browse themes: the directory of free themes, and one-click
 * install and update.
 *
 * Admin-only, like uploading a theme, because installing one is deploying code.
 */
class ThemeMarketplaceController extends Controller
{
    public function __construct(
        private CatalogClient $catalog,
        private MarketplaceInstaller $installer,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureEnabled();

        $catalog = $this->catalog->cached();
        $query = trim((string) $request->query('q', ''));
        $tag = trim((string) $request->query('tag', ''));

        return view('admin.themes.marketplace.index', [
            'catalog' => $catalog,
            'items' => $catalog?->search($query, $tag) ?? [],
            'tags' => $catalog?->tags() ?? [],
            'query' => $query,
            'tag' => $tag,
            'error' => $catalog ? null : $this->catalog->lastError(),
            'installed' => Theme::all()->keyBy('slug'),
            'lastChecked' => $this->catalog->lastCheckedAt(),
            'remoteImages' => (bool) config('marketplace.remote_images', true),
        ]);
    }

    public function show(string $slug): View
    {
        $item = $this->item($slug);

        return view('admin.themes.marketplace.show', [
            'item' => $item,
            'status' => $this->installer->status($item),
            'checks' => $this->installer->preflight($item),
            'remoteImages' => (bool) config('marketplace.remote_images', true),
        ]);
    }

    public function install(string $slug): RedirectResponse
    {
        $item = $this->item($slug);

        try {
            $result = $this->installer->install($item);
        } catch (MarketplaceException|ThemeInstallException $e) {
            // Both are written for site owners, so the message is safe to show.
            activity('theme.marketplace.rejected', "Could not install {$item->name} from the theme directory.",
                properties: ['slug' => $item->slug, 'version' => $item->version, 'reason' => $e->getMessage()]);

            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The theme could not be installed. Check the logs for details.');
        }

        // Compiled Blade is keyed by path, and an updated theme's views have
        // just changed underneath it.
        if ($result['updated']) {
            Artisan::call('view:clear');
        }

        $verb = $result['updated'] ? 'Updated' : 'Installed';

        activity(
            $result['updated'] ? 'theme.marketplace.updated' : 'theme.marketplace.installed',
            "{$verb} the {$result['name']} theme from the theme directory.",
            properties: ['slug' => $result['slug'], 'version' => $result['version']]
        );

        $message = $result['updated']
            ? "{$result['name']} updated to version {$result['version']}."
            : "{$result['name']} installed. Activate it when you are ready.";

        return redirect()->route('admin.themes.index')
            ->with('status', $message)
            ->with('theme_warnings', $result['warnings']);
    }

    public function refresh(): RedirectResponse
    {
        $this->ensureEnabled();

        try {
            $catalog = $this->catalog->refresh();
        } catch (MarketplaceException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The theme directory could not be loaded. Check the logs for details.');
        }

        $count = $catalog->count();

        return back()->with('status', "The theme directory lists {$count} theme".($count === 1 ? '' : 's').'.');
    }

    /**
     * The kill switch is enforced here rather than by leaving the routes out,
     * so it also holds for a site that has cached its route table.
     */
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
