<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Plugins\PluginCatalogClient;
use App\Cms\Plugins\PluginInstaller;
use App\Cms\Plugins\PluginInstallException;
use App\Cms\Plugins\PluginManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Install, switch on, switch off and uninstall plugins.
 *
 * Every action here changes which code the site runs, so the whole screen is
 * administrator-only, behind the same two-factor check as the updater.
 */
class PluginController extends Controller
{
    public function __construct(
        private PluginManager $plugins,
        private PluginInstaller $installer,
        private PluginCatalogClient $directory,
    ) {}

    public function index(): View
    {
        // Pick up anything copied into plugins/ over FTP since the last visit.
        $invalid = $this->plugins->sync();

        return view('admin.plugins.index', [
            'plugins' => $this->plugins->all(),
            'invalid' => $invalid,
            'safeMode' => $this->plugins->safeMode(),
            'uploadsAllowed' => $this->plugins->uploadsAllowed(),
            'maxUploadKb' => config('cms.plugins.max_upload_kb'),
            'directoryEnabled' => $this->directory->enabled(),
            'updates' => $this->availableUpdates(),
        ]);
    }

    /**
     * Newer directory versions of installed plugins. Wrapped so an unreachable
     * directory never costs the Plugins screen more than its update buttons.
     *
     * @return array<string, \App\Cms\Marketplace\MarketplaceItem>
     */
    private function availableUpdates(): array
    {
        try {
            return $this->directory->availableUpdates();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    public function upload(Request $request): RedirectResponse
    {
        abort_unless($this->plugins->uploadsAllowed(), 403, 'Plugin uploads are switched off on this site.');

        $request->validate([
            'plugin' => ['required', 'file', 'mimes:zip', 'max:'.config('cms.plugins.max_upload_kb', 20480)],
            'overwrite' => ['nullable', 'boolean'],
        ], [
            'plugin.mimes' => 'A plugin must be uploaded as a .zip archive.',
            'plugin.max' => 'That archive is larger than this site allows.',
        ]);

        try {
            $result = $this->installer->installFromUpload($request->file('plugin'), $request->boolean('overwrite'));
        } catch (PluginInstallException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The plugin could not be installed. Check the logs for details.');
        }

        $verb = $result['updated'] ? 'Updated' : 'Installed';

        activity('plugin.installed', "{$verb} the {$result['name']} plugin ({$result['version']}).", properties: ['slug' => $result['slug']]);

        return back()->with('status', $result['updated']
            ? "{$result['name']} updated to {$result['version']}."
            : "{$result['name']} installed. Switch it on when you are ready.");
    }

    public function toggle(string $slug): RedirectResponse
    {
        $plugin = $this->plugins->find($slug) ?? abort(404);

        try {
            if ($plugin->enabled) {
                $this->plugins->disable($slug);
                $message = "{$plugin->name} is now off.";
            } else {
                $this->plugins->enable($slug);
                $message = "{$plugin->name} is now on.";
            }
        } catch (PluginInstallException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', "{$plugin->name} could not be switched on: {$e->getMessage()}");
        }

        activity('plugin.toggled', $message, properties: ['slug' => $slug, 'enabled' => ! $plugin->enabled]);

        return back()->with('status', $message);
    }

    public function destroy(string $slug): RedirectResponse
    {
        $plugin = $this->plugins->find($slug) ?? abort(404);

        try {
            $this->plugins->uninstall($slug);
        } catch (PluginInstallException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The plugin could not be uninstalled. Check the logs for details.');
        }

        activity('plugin.uninstalled', "Uninstalled the {$plugin->name} plugin.", properties: ['slug' => $slug]);

        return back()->with('status', "{$plugin->name} uninstalled, along with its data.");
    }
}
