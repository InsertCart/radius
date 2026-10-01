<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Support\SiteAddressChanger;
use App\Cms\Support\SiteUrlRewriter;
use App\Cms\Updates\BackupService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Change site address: the screen for a database that moved from one domain
 * to another - staging to live, usually.
 *
 * Two steps, like an import. The first only counts what would change, so the
 * person sees "143 links in 38 posts" before anything is written; the second
 * takes a database backup and then does it.
 */
class SiteAddressController extends Controller
{
    public function __construct(private SiteAddressChanger $changer) {}

    public function index(): View
    {
        return view('admin.system.site-address', [
            'current' => rtrim(url('/'), '/'),
            'appUrl' => rtrim((string) config('app.url'), '/'),
            'preview' => null,
        ]);
    }

    public function preview(Request $request): View
    {
        [$from, $to] = $this->addresses($request);

        return view('admin.system.site-address', [
            'current' => rtrim(url('/'), '/'),
            'appUrl' => rtrim((string) config('app.url'), '/'),
            'from' => $from,
            'to' => $to,
            'preview' => $this->changer->preview($from, $to),
        ]);
    }

    public function apply(Request $request, BackupService $backups): RedirectResponse
    {
        [$from, $to] = $this->addresses($request);

        if ($request->boolean('backup')) {
            try {
                $backups->dumpDatabase('before-address-change');
            } catch (\Throwable $e) {
                report($e);

                return redirect()->route('admin.system.site-address')->withInput()
                    ->with('error', 'The backup failed, so nothing was changed: '.$e->getMessage());
            }
        }

        $results = $this->changer->apply($from, $to);

        $links = array_sum(array_column($results, 'links'));
        $rows = array_sum(array_column($results, 'rows'));

        activity('system.site_address_changed', "Changed the site address from {$from} to {$to}.", properties: $results);

        return redirect()->route('admin.system.site-address')->with('status', $links === 0
            ? "Nothing pointed at {$from}, so nothing was changed."
            : "Done. {$links} address(es) in {$rows} record(s) now point at {$to}.");
    }

    /**
     * @return array{0: string, 1: string} both normalised
     *
     * @throws ValidationException
     */
    private function addresses(Request $request): array
    {
        $request->validate([
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
        ]);

        $from = SiteUrlRewriter::normalise($request->string('from')->toString());
        $to = SiteUrlRewriter::normalise($request->string('to')->toString());

        $errors = [];

        if ($from === null) {
            $errors['from'] = 'Enter the old address in full, starting with http:// or https://.';
        }

        if ($to === null) {
            $errors['to'] = 'Enter the new address in full, starting with http:// or https://.';
        }

        // Same host: a scheme change alone ("http" to "https") is allowed,
        // because that is a real and common move. The identical address is not.
        if ($errors === [] && $from === $to) {
            $errors['to'] = 'The new address is the same as the old one.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [$from, $to];
    }
}
