<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Cdn\CdnManager;
use App\Http\Controllers\Controller;
use App\Models\CdnConnection;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Choose where the media library keeps its files.
 *
 * Credentials are write-only from this screen, as payment gateway keys are:
 * saved values are never sent back to the browser, and a blank field means
 * "keep what is already there".
 *
 * The one rule this screen enforces beyond validation is that files may not
 * be stranded. A connection holding the only copy of anything cannot be
 * switched off until those files are back on this server, because doing so
 * would replace every image on the site with a broken one and leave no way
 * back.
 */
class CdnController extends Controller
{
    public function __construct(private CdnManager $cdn) {}

    public function index(): View
    {
        // Pick up any provider added to the catalogue since the last visit.
        $this->cdn->sync();

        return view('admin.cdn.index', [
            'connections' => $this->cdn->providers(),
            'active' => $this->cdn->active(),
            'progress' => $this->cdn->progress(),
            'batchSize' => (int) config('cdn.batch_size', 25),
        ]);
    }

    public function edit(string $provider): View
    {
        $connection = $this->connection($provider);
        $definition = $connection->definition();

        return view('admin.cdn.edit', [
            'connection' => $connection,
            'definition' => $definition,
            'fields' => $definition['fields'] ?? [],
            // Only which credentials are filled, never their values.
            'filled' => collect($definition['fields'] ?? [])
                ->mapWithKeys(fn ($field, $key) => [$key => filled($connection->credential($key))])
                ->all(),
            'progress' => $this->cdn->progress(),
        ]);
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        $connection = $this->connection($provider);
        $fields = $connection->definition()['fields'] ?? [];

        $rules = [
            'keep_local' => ['nullable', 'boolean'],
            'path_prefix' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._\-\/]+$/'],
        ];

        foreach ($fields as $key => $field) {
            $rules['credentials.'.$key] = match ($field['type'] ?? 'text') {
                'url' => ['nullable', 'string', 'max:500', 'url'],
                'select' => ['nullable', 'string', 'in:'.implode(',', array_keys($field['options'] ?? []))],
                default => ['nullable', 'string', 'max:500'],
            };
        }

        $validated = $request->validate($rules, [
            'path_prefix.regex' => 'A folder prefix may only contain letters, numbers, dots, dashes and slashes.',
        ]);

        $connection->mergeCredentials($validated['credentials'] ?? []);
        $connection->path_prefix = ($validated['path_prefix'] ?? null) ?: null;
        $connection->keep_local = $request->boolean('keep_local');
        $connection->save();

        $this->cdn->flush();

        activity('cdn.updated', "Updated the {$connection->name()} media storage settings.", $connection);

        return back()->with('status', "{$connection->name()} settings saved.");
    }

    /** Write a file, read it back, delete it - before anything depends on it. */
    public function test(string $provider): RedirectResponse
    {
        @set_time_limit(0);

        $result = $this->cdn->test($this->connection($provider));

        return $result['ok']
            ? back()->with('status', $result['message'].($result['url'] ? ' Files will be served from '.$result['url'] : ''))
            : back()->with('error', 'Connection failed. '.$result['message']);
    }

    /**
     * Switch a connection on, which switches any other one off. Only one
     * provider can own the library at a time.
     */
    public function enable(string $provider): RedirectResponse
    {
        $connection = $this->connection($provider);

        if (! $connection->isConfigured()) {
            return back()->with('error',
                'Fill in '.implode(', ', $connection->missingFields()).' before switching this on.');
        }

        if (blank($connection->deliveryUrl())) {
            return back()->with('error',
                'This provider has no public address yet, so visitors would have nowhere to fetch files from.');
        }

        if ($stranded = $this->strandedCount()) {
            return back()->with('error',
                "{$stranded} file(s) exist only on the current provider. Bring them back to this server first, "
                .'or they will be unreachable once the address changes.');
        }

        $previous = $this->cdn->active();

        CdnConnection::where('is_enabled', true)->update(['is_enabled' => false]);
        $connection->update(['is_enabled' => true]);

        $this->cdn->flush();

        activity('cdn.enabled', "Media is now served through {$connection->name()}.", $connection);

        $message = "{$connection->name()} is now serving your media.";

        if ($connection->offloads() && $this->cdn->progress()['pending'] > 0) {
            $message .= ' Existing files are still on this server - upload them below to finish the move.';
        }

        if ($previous && $previous->isNot($connection)) {
            $message .= " {$previous->name()} has been switched off.";
        }

        return back()->with('status', $message);
    }

    public function disable(string $provider): RedirectResponse
    {
        $connection = $this->connection($provider);

        if ($stranded = $this->strandedCount()) {
            return back()->with('error',
                "{$stranded} file(s) exist only on this provider. Bring them back to this server first - "
                .'switching off now would leave them unreachable.');
        }

        $connection->update(['is_enabled' => false]);
        $this->cdn->flush();

        activity('cdn.disabled', "Media is served from this server again; {$connection->name()} was switched off.", $connection);

        return back()->with('status', "{$connection->name()} is switched off. Media is served from this server again.");
    }

    /**
     * Move one batch of existing files to the provider.
     *
     * Bounded rather than exhaustive on purpose: a shared host will kill a
     * request that runs for a minute, and a half-finished loop that lost its
     * progress would be worse than a button pressed four times.
     */
    public function push(): RedirectResponse
    {
        if (! $this->cdn->offloads()) {
            return back()->with('error', 'No storage provider is switched on, so there is nothing to upload to.');
        }

        @set_time_limit(0);

        $keepLocal = $this->cdn->keepsLocalCopy();
        $origin = Storage::disk($this->cdn->originDisk());

        $moved = 0;
        $failed = 0;

        foreach (Media::where('on_cdn', false)->limit((int) config('cdn.batch_size', 25))->get() as $media) {
            try {
                foreach ($media->paths() as $path) {
                    $this->cdn->push($path);
                }
            } catch (\Throwable $e) {
                report($e);
                $failed++;

                continue;
            }

            $media->on_cdn = true;

            if (! $keepLocal) {
                foreach ($media->paths() as $path) {
                    $origin->delete($path);
                }

                $media->has_local_copy = false;
            }

            $media->save();
            $moved++;
        }

        $this->cdn->flushProgress();

        $remaining = $this->cdn->progress()['pending'];

        if ($moved === 0 && $failed === 0) {
            return back()->with('status', 'Every file is already on the provider.');
        }

        $message = "Uploaded {$moved} file(s).".($remaining ? " {$remaining} still to go - press the button again." : ' That was the last of them.');

        return $failed
            ? back()->with('error', $message." {$failed} file(s) could not be uploaded; see the log for why.")
            : back()->with('status', $message);
    }

    /** Copy one batch back from the provider onto this server. */
    public function pull(): RedirectResponse
    {
        if (! $this->cdn->offloads()) {
            return back()->with('error', 'No storage provider is switched on, so there is nothing to fetch from.');
        }

        @set_time_limit(0);

        $moved = 0;
        $failed = 0;

        $batch = Media::where('on_cdn', true)
            ->where('has_local_copy', false)
            ->limit((int) config('cdn.batch_size', 25))
            ->get();

        foreach ($batch as $media) {
            try {
                foreach ($media->paths() as $path) {
                    $this->cdn->pull($path);
                }
            } catch (\Throwable $e) {
                report($e);
                $failed++;

                continue;
            }

            $media->update(['has_local_copy' => true]);
            $moved++;
        }

        $remaining = $this->cdn->progress()['remote_only'];

        if ($moved === 0 && $failed === 0) {
            return back()->with('status', 'Every file already has a copy on this server.');
        }

        $message = "Brought back {$moved} file(s).".($remaining ? " {$remaining} still to go - press the button again." : ' Every file now has a copy here.');

        return $failed
            ? back()->with('error', $message." {$failed} file(s) could not be fetched; see the log for why.")
            : back()->with('status', $message);
    }

    /**
     * The row for a provider slug, created on demand.
     *
     * Resolved by hand rather than by route binding so that a link followed
     * before the catalogue has been synced - after an update that added a
     * provider, say - opens the screen instead of answering 404.
     */
    private function connection(string $provider): CdnConnection
    {
        abort_if(blank(config("cdn.providers.{$provider}")), 404);

        return CdnConnection::firstOrCreate(['provider' => $provider]);
    }

    /** Files whose only copy is on the provider. */
    private function strandedCount(): int
    {
        return Media::where('on_cdn', true)->where('has_local_copy', false)->count();
    }
}
