<?php

namespace App\Console\Commands;

use App\Cms\Cdn\CdnManager;
use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Moves the media library between this server and the configured provider.
 *
 * The admin screen does the same job a batch at a time, because a shared host
 * will kill a long request. This is the version for a library big enough that
 * clicking a button forty times is not a plan.
 */
class CdnSyncCommand extends Command
{
    protected $signature = 'cms:cdn-sync
                            {--pull : Bring files back to this server instead of uploading them}
                            {--limit=0 : Stop after this many files (0 means no limit)}
                            {--force : Do not ask before deleting local copies}';

    protected $description = 'Upload the media library to the configured storage provider, or bring it back';

    public function handle(CdnManager $cdn): int
    {
        if (! $cdn->offloads()) {
            $this->components->error('No storage provider is switched on, so there is nothing to sync.');
            $this->line('  Configure one under Admin -> Media storage first.');

            return self::FAILURE;
        }

        $this->components->info('Provider: '.$cdn->providerName());

        return $this->option('pull') ? $this->pull($cdn) : $this->push($cdn);
    }

    private function push(CdnManager $cdn): int
    {
        $keepLocal = $cdn->keepsLocalCopy();
        $query = Media::where('on_cdn', false);
        $total = $this->total($query);

        if ($total === 0) {
            $this->components->info('Every file is already on the provider.');

            return self::SUCCESS;
        }

        if (! $keepLocal && ! $this->option('force') && ! $this->confirmDeletion($total)) {
            return self::FAILURE;
        }

        $origin = Storage::disk($cdn->originDisk());
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $failed = [];

        $done = 0;

        $query->chunkById(50, function ($batch) use ($cdn, $origin, $keepLocal, $bar, &$failed, &$done, $total) {
            foreach ($batch as $media) {
                if ($done >= $total) {
                    return false;
                }

                $done++;

                try {
                    foreach ($media->paths() as $path) {
                        $cdn->push($path);
                    }
                } catch (\Throwable $e) {
                    $failed[$media->path] = $e->getMessage();
                    $bar->advance();

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
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $cdn->flushProgress();

        return $this->report($total, $failed, 'uploaded');
    }

    private function pull(CdnManager $cdn): int
    {
        $query = Media::where('on_cdn', true)->where('has_local_copy', false);
        $total = $this->total($query);

        if ($total === 0) {
            $this->components->info('Every file already has a copy on this server.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $failed = [];

        $done = 0;

        $query->chunkById(50, function ($batch) use ($cdn, $bar, &$failed, &$done, $total) {
            foreach ($batch as $media) {
                if ($done >= $total) {
                    return false;
                }

                $done++;

                try {
                    foreach ($media->paths() as $path) {
                        $cdn->pull($path);
                    }

                    $media->update(['has_local_copy' => true]);
                } catch (\Throwable $e) {
                    $failed[$media->path] = $e->getMessage();
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        return $this->report($total, $failed, 'brought back');
    }

    /**
     * Deleting this server's copy is the one irreversible thing here, and a
     * typo in a bucket name is not a good way to discover that.
     */
    private function confirmDeletion(int $total): bool
    {
        $this->components->warn("This connection does not keep local copies, so {$total} file(s) will be deleted from this server once they are uploaded.");

        if ($this->confirm('Continue?', false)) {
            return true;
        }

        $this->line('  Nothing was changed. Turn on "Keep a copy of every file on this server" first if you would rather mirror.');

        return false;
    }

    /**
     * How many rows this run will touch.
     *
     * --limit is applied by counting rather than by limiting the query,
     * because chunkById owns the query's own limit and would quietly ignore
     * one set here.
     */
    private function total($query): int
    {
        $available = (clone $query)->count();
        $limit = (int) $this->option('limit');

        return $limit > 0 ? min($limit, $available) : $available;
    }

    private function report(int $total, array $failed, string $verb): int
    {
        $done = $total - count($failed);

        $this->components->info("{$done} file(s) {$verb}.");

        if ($failed === []) {
            return self::SUCCESS;
        }

        $this->components->error(count($failed).' file(s) failed:');

        foreach (array_slice($failed, 0, 10, true) as $path => $message) {
            $this->line("  <fg=red>{$path}</> — {$message}");
        }

        if (count($failed) > 10) {
            $this->line('  … and '.(count($failed) - 10).' more. Run the command again to retry them.');
        }

        return self::FAILURE;
    }
}
