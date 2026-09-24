<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Transfer\ExportOptions;
use App\Cms\Transfer\ExportService;
use App\Cms\Transfer\ImportOptions;
use App\Cms\Transfer\ImportReport;
use App\Cms\Transfer\ImportService;
use App\Cms\Transfer\ResourceRegistry;
use App\Cms\Transfer\TransferException;
use App\Cms\Transfer\TransferWorkspace;
use App\Cms\Transfer\WordPress\WordPressImporter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Moving content in and out.
 *
 * Importing is deliberately two steps. An import file is written somewhere
 * else by somebody else, and the person clicking the button deserves to be
 * told what is in it - 412 posts, 90 images, a menu - before any of it lands
 * on their site. So an upload is parked, read, described, and only applied
 * when they say so.
 *
 * Admin-only throughout, which follows from what an import can do: it creates
 * content, it can create user accounts, and it fetches files from an address
 * somebody else chose. That is not an editor's decision.
 */
class TransferController extends Controller
{
    public function __construct(
        private ResourceRegistry $registry,
        private ExportService $exporter,
        private ImportService $importer,
        private WordPressImporter $wordpress,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.transfer.index', [
            'resources' => $this->registry->available(),
            'pending' => $this->pendingUploads(),
            'report' => $this->lastReport($request),
            'maxUploadMb' => round(((int) config('transfer.max_upload_kb', 102400)) / 1024),
            'serverLimit' => $this->serverUploadLimit(),
        ]);
    }

    // Export -------------------------------------------------------------------

    public function export(Request $request): BinaryFileResponse|RedirectResponse
    {
        $validated = $request->validate([
            'types' => ['required', 'array', 'min:1'],
            'types.*' => ['string', 'in:'.implode(',', $this->registry->keys())],
            'status' => ['nullable', 'in:any,draft,published,scheduled,archived'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'format' => ['required', 'in:zip,json,csv'],
            'include_media_files' => ['nullable', 'boolean'],
            'include_layouts' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->exporter->run(ExportOptions::fromArray([
                'types' => $validated['types'],
                'status' => $validated['status'] ?? null,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
                'format' => $validated['format'],
                'include_media_files' => $request->boolean('include_media_files'),
                'include_layouts' => $request->boolean('include_layouts'),
            ]));
        } catch (TransferException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($result->isEmpty()) {
            return back()->withInput()->with('error', 'Nothing matched those filters, so there was nothing to export.');
        }

        activity('content.exported', 'Exported content. '.$result->summary());

        // Deleted once it has been sent: it is a full copy of the site's
        // content sitting in storage, and there is no reason to keep one.
        return response()->download($result->path, $result->filename)->deleteFileAfterSend();
    }

    // Import -------------------------------------------------------------------

    /**
     * Takes the file and parks it. Nothing is applied here - the next screen
     * says what was found and asks.
     */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'kind' => ['required', 'in:bundle,wordpress'],
            'file' => ['required', 'file', 'max:'.(int) config('transfer.max_upload_kb', 102400)],
        ]);

        $kind = $request->string('kind')->toString();
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $allowed = $kind === 'wordpress' ? ['xml'] : ['zip', 'json'];

        if (! in_array($extension, $allowed, true)) {
            return back()->with('error', $kind === 'wordpress'
                ? 'A WordPress export is an .xml file. Use Tools > Export inside WordPress to make one.'
                : 'A Radius export is a .zip or a .json file.');
        }

        TransferWorkspace::sweep();

        $token = Str::lower(Str::random(24));
        $directory = TransferWorkspace::path('uploads'.DIRECTORY_SEPARATOR.$token);

        // The stored name is ours, not the upload's: a filename off a browser
        // is attacker-controlled and has no business choosing a disk path.
        $stored = 'import.'.$extension;
        $file->move($directory, $stored);

        File::put($directory.DIRECTORY_SEPARATOR.'meta.json', json_encode([
            'kind' => $kind,
            'file' => $stored,
            'original_name' => Str::limit($file->getClientOriginalName(), 120, ''),
            'uploaded_at' => now()->toIso8601String(),
            'uploaded_by' => $request->user()->id,
        ]));

        return redirect()->route('admin.transfer.review', $token);
    }

    public function review(Request $request, string $token): View|RedirectResponse
    {
        $upload = $this->parked($token);

        if (! $upload) {
            return redirect()->route('admin.transfer.index')->with('error', 'That upload has expired. Upload the file again.');
        }

        try {
            $analysis = $upload['kind'] === 'wordpress'
                ? $this->wordpress->inspect($upload['path'])
                : $this->importer->inspect($upload['path']);
        } catch (TransferException $e) {
            $this->discardUpload($token);

            return redirect()->route('admin.transfer.index')->with('error', $e->getMessage());
        }

        return view('admin.transfer.review', [
            'analysis' => $this->reachable($analysis),
            'token' => $token,
            'upload' => $upload,
            'resources' => $this->registry->available(),
            'staff' => User::whereIn('role', User::STAFF_ROLES)->orderBy('name')->get(),
        ]);
    }

    public function run(Request $request, string $token): RedirectResponse
    {
        $upload = $this->parked($token);

        if (! $upload) {
            return redirect()->route('admin.transfer.index')->with('error', 'That upload has expired. Upload the file again.');
        }

        $validated = $request->validate([
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:'.implode(',', $this->registry->keys())],
            'mode' => ['required', 'in:skip,update'],
            'status' => ['nullable', 'in:keep,draft,published'],
            'author_id' => ['nullable', 'exists:users,id'],
        ]);

        $options = ImportOptions::fromArray([
            'types' => $validated['types'] ?? null,
            'mode' => $validated['mode'],
            'status' => ($validated['status'] ?? 'keep') === 'keep' ? null : $validated['status'],
            'author_id' => $validated['author_id'] ?? $request->user()->id,
            'create_authors' => $request->boolean('create_authors'),
            'import_media' => $request->boolean('import_media'),
            'download_media' => $request->boolean('download_media'),
            'include_layouts' => $request->boolean('include_layouts'),
            'rewrite_urls' => $request->boolean('rewrite_urls'),
        ]);

        // An import of a few thousand posts takes minutes, and the default 30
        // seconds would leave the site half-populated with no report at all.
        // The run has its own deadline, set from config, so this does not hand
        // one request the machine forever.
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        try {
            $report = $upload['kind'] === 'wordpress'
                ? $this->wordpress->run($upload['path'], $options)
                : $this->importer->run($upload['path'], $options);
        } catch (TransferException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Kept only while it is being read, and gone the moment it has been:
        // it is a full copy of somebody's site.
        $this->discardUpload($token);

        return redirect()->route('admin.transfer.index')
            ->with('transfer_report', $report->toArray())
            ->with('status', 'Import finished. '.$report->summary());
    }

    public function discard(string $token): RedirectResponse
    {
        $this->discardUpload($token);

        return redirect()->route('admin.transfer.index')->with('status', 'Upload discarded.');
    }

    /**
     * Narrows what a file offers to what this site can actually take.
     *
     * A bundle from a site with the shop switched on, read by one with it off,
     * holds products this installation has no table screen or route for.
     * Listing them would offer a tick box that fails validation; naming them
     * as unreadable says the true thing.
     */
    private function reachable(array $analysis): array
    {
        $known = $this->registry->keys();
        $types = (array) ($analysis['types'] ?? []);

        $analysis['unknown'] = array_values(array_unique(array_merge(
            (array) ($analysis['unknown'] ?? []),
            array_diff(array_keys($types), $known)
        )));

        $analysis['types'] = array_intersect_key($types, array_flip($known));

        return $analysis;
    }

    // Internals -----------------------------------------------------------------

    /**
     * A parked upload, or null.
     *
     * The token has to be exactly what this controller generates. It reaches a
     * filesystem path, and "it came from our own redirect" is not a reason to
     * skip checking that.
     *
     * @return array{kind: string, path: string, original_name: string, uploaded_at: ?string, size: int}|null
     */
    private function parked(string $token): ?array
    {
        if (! preg_match('/^[a-z0-9]{24}$/', $token)) {
            return null;
        }

        $directory = TransferWorkspace::path('uploads').DIRECTORY_SEPARATOR.$token;
        $meta = $directory.DIRECTORY_SEPARATOR.'meta.json';

        if (! is_file($meta)) {
            return null;
        }

        $data = json_decode((string) File::get($meta), true);

        if (! is_array($data)) {
            return null;
        }

        $path = $directory.DIRECTORY_SEPARATOR.basename((string) ($data['file'] ?? ''));

        if (! is_file($path)) {
            return null;
        }

        return [
            'kind' => ($data['kind'] ?? 'bundle') === 'wordpress' ? 'wordpress' : 'bundle',
            'path' => $path,
            'original_name' => (string) ($data['original_name'] ?? basename($path)),
            'uploaded_at' => $data['uploaded_at'] ?? null,
            'size' => (int) filesize($path),
        ];
    }

    private function discardUpload(string $token): void
    {
        if (preg_match('/^[a-z0-9]{24}$/', $token)) {
            File::deleteDirectory(TransferWorkspace::path('uploads').DIRECTORY_SEPARATOR.$token);
        }
    }

    /** Uploads parked but never applied, so the screen can offer them again. */
    private function pendingUploads(): array
    {
        $pending = [];

        foreach (File::directories(TransferWorkspace::path('uploads')) as $directory) {
            $token = basename($directory);
            $upload = $this->parked($token);

            if ($upload) {
                $pending[$token] = $upload;
            }
        }

        return $pending;
    }

    private function lastReport(Request $request): ?ImportReport
    {
        $data = $request->session()->get('transfer_report');

        return is_array($data) ? ImportReport::fromArray($data) : null;
    }

    /**
     * What PHP itself will accept, which is usually smaller than what this
     * screen would allow and is the limit people actually hit.
     */
    private function serverUploadLimit(): int
    {
        $toBytes = static function (string $value): int {
            $value = trim($value);
            $unit = strtolower(substr($value, -1));
            $number = (int) $value;

            return match ($unit) {
                'g' => $number * 1024 * 1024 * 1024,
                'm' => $number * 1024 * 1024,
                'k' => $number * 1024,
                default => $number,
            };
        };

        $limits = array_filter([
            $toBytes((string) ini_get('upload_max_filesize')),
            $toBytes((string) ini_get('post_max_size')),
        ]);

        return $limits === [] ? 0 : (int) round(min($limits) / 1024 / 1024);
    }
}
