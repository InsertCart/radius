<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Themes\ThemeInstaller;
use App\Cms\Themes\ThemeInstallException;
use App\Cms\Themes\ThemeManager;
use App\Http\Controllers\Controller;
use App\Models\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Upload, activate and remove front-end templates.
 */
class ThemeController extends Controller
{
    public function __construct(
        private ThemeManager $themes,
        private ThemeInstaller $installer,
    ) {}

    public function index(): View
    {
        // Pick up anything dropped into themes/ over FTP since the last visit.
        $this->themes->sync();

        return view('admin.themes.index', [
            'themes' => Theme::orderByDesc('is_active')->orderBy('name')->get(),
            'activeSlug' => $this->themes->activeSlug(),
            'maxUploadKb' => config('cms.themes.max_upload_kb'),
            'allowedExtensions' => config('cms.themes.allowed_extensions'),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'theme' => ['required', 'file', 'mimes:zip', 'max:'.config('cms.themes.max_upload_kb', 40960)],
            'overwrite' => ['nullable', 'boolean'],
        ], [
            'theme.mimes' => 'A theme must be uploaded as a .zip archive.',
            'theme.max' => 'That archive is larger than this site allows.',
        ]);

        try {
            $result = $this->installer->installFromUpload(
                $request->file('theme'),
                $request->boolean('overwrite')
            );
        } catch (ThemeInstallException $e) {
            // The message is written for site owners, so it is safe to show.
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The theme could not be installed. Check the logs for details.');
        }

        activity('theme.installed', "Installed the {$result['name']} theme.", properties: ['slug' => $result['slug']]);

        $message = "{$result['name']} installed. Activate it when you are ready.";

        return back()
            ->with('status', $message)
            ->with('theme_warnings', $result['warnings']);
    }

    public function activate(string $slug): RedirectResponse
    {
        try {
            $this->themes->activate($slug);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        // Compiled Blade files are keyed by path, and the theme's views have
        // just changed underneath them.
        Artisan::call('view:clear');

        activity('theme.activated', "Activated the {$slug} theme.");

        return back()->with('status', 'Theme activated.');
    }

    public function destroy(string $slug): RedirectResponse
    {
        try {
            $this->themes->delete($slug);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        activity('theme.deleted', "Deleted the {$slug} theme.");

        return back()->with('status', 'Theme deleted.');
    }

    /** Re-reads themes/ and republishes assets. */
    public function sync(): RedirectResponse
    {
        $count = $this->themes->sync();

        return back()->with('status', "Found {$count} theme".($count === 1 ? '' : 's').' on disk.');
    }
}
