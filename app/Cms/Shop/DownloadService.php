<?php

namespace App\Cms\Shop;

use App\Cms\Media\UploadGuard;
use App\Cms\Media\UploadRejected;
use App\Models\OrderItem;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The files behind digital products.
 *
 * These are kept apart from the media library on purpose. Media is served
 * straight off the web server from a folder inside the web root, which is
 * exactly right for images and exactly wrong for something a customer has paid
 * for: anyone who learned the address would have the file, order or no order.
 *
 * So a paid download lives on a disk with no public URL at all, under a name
 * nobody could guess, and the only way to it is send(), which is called after
 * the order has been checked. Even if the storage folder were somehow exposed
 * by a misconfigured server, the random component in the path means the
 * address still cannot be worked out from the product page.
 */
class DownloadService
{
    public function __construct(private UploadGuard $guard) {}

    public function disk(): Filesystem
    {
        return Storage::disk(config('cms.downloads.disk', 'private'));
    }

    /**
     * Store an uploaded file and return what belongs on the product row.
     *
     * @return array{digital_file: string, digital_name: string, digital_size: int}
     *
     * @throws UploadRejected when the file fails a safety check.
     */
    public function store(UploadedFile $file): array
    {
        $extension = $this->guard->inspect($file, UploadGuard::CONTEXT_DOWNLOAD);

        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        // The path is derived, never taken from the upload: an attacker-chosen
        // filename has no business steering where a file lands. The random
        // suffix is what makes the path unguessable.
        $stored = Str::limit(Str::slug($original) ?: 'download', 60, '')
            .'-'.Str::random(24).'.'.$extension;

        $directory = trim(config('cms.downloads.directory', 'downloads'), '/')
            .'/'.now()->format('Y/m');

        $this->disk()->putFileAs($directory, $file, $stored);

        return [
            'digital_file' => $directory.'/'.$stored,
            // Kept so the customer receives "Design Pack.zip" rather than the
            // storage name, which would be meaningless to them.
            'digital_name' => Str::limit($original, 180, '').'.'.$extension,
            'digital_size' => (int) $file->getSize(),
        ];
    }

    /**
     * Remove a stored file, unless somebody has bought it.
     *
     * Order lines keep the exact path they were sold, so a customer who paid
     * last year still gets the file they paid for rather than whatever the
     * seller has uploaded since. That only works if replacing a product's
     * download leaves the superseded file where it is.
     *
     * Missing files are not an error.
     */
    public function delete(?string $path): void
    {
        if (blank($path) || ! $this->owns($path) || $this->isSold($path)) {
            return;
        }

        $this->disk()->delete($path);
    }

    /** Whether any order line was sold this exact file. */
    public function isSold(string $path): bool
    {
        return OrderItem::where('digital_file', $path)->exists();
    }

    public function exists(?string $path): bool
    {
        return filled($path) && $this->owns($path) && $this->disk()->exists($path);
    }

    /**
     * Send a purchased file to the customer.
     *
     * Always as an attachment, and always with an explicit content type of
     * application/octet-stream: a browser must download the file, never render
     * it. Anything else would let a seller's HTML or SVG run on this site's
     * own origin, with the customer's session attached.
     */
    public function send(OrderItem $item): StreamedResponse
    {
        $name = $item->digital_name ?: basename($item->digital_file);

        return $this->disk()->download($item->digital_file, $this->safeDownloadName($name), [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * Paths on this disk always sit under the downloads directory.
     *
     * A stored path only ever comes from store(), so this should always hold.
     * It is checked anyway, because the value travels through the database and
     * a path that escaped the directory would read arbitrary files off the
     * server - the one mistake here that would really matter.
     */
    private function owns(string $path): bool
    {
        $directory = trim(config('cms.downloads.directory', 'downloads'), '/');

        return ! str_contains($path, '..')
            && ! str_starts_with($path, '/')
            && ! preg_match('#^[a-zA-Z]:#', $path)
            && str_starts_with(str_replace('\\', '/', $path), $directory.'/');
    }

    /** Strip anything from the customer-facing filename that could confuse a client. */
    private function safeDownloadName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F"\\\\\/:*?<>|]+/', '', $name);

        return trim($name) ?: 'download';
    }

    /** Validation rules for the product form's file field. */
    public function uploadRules(): array
    {
        $allowed = $this->guard->allowedExtensions(UploadGuard::CONTEXT_DOWNLOAD);

        if (in_array('jpg', $allowed, true)) {
            $allowed[] = 'jpeg';
        }

        return [
            'file',
            'extensions:'.implode(',', array_unique($allowed)),
            'max:'.$this->maxUploadKb(),
        ];
    }

    /** @return string[] */
    public function allowedExtensions(): array
    {
        return $this->guard->allowedExtensions(UploadGuard::CONTEXT_DOWNLOAD);
    }

    /**
     * The largest upload that will actually succeed.
     *
     * PHP enforces its own ceiling before any of this code runs, and when a
     * post exceeds it the request simply arrives empty - no error, no file.
     * Showing the configured number when PHP will not honour it sends sellers
     * chasing an upload that was never going to work, so the smaller of the
     * three wins.
     */
    public function maxUploadKb(): int
    {
        $limits = [(int) config('cms.downloads.max_upload_kb', 262144)];

        foreach (['upload_max_filesize', 'post_max_size'] as $directive) {
            $bytes = $this->iniBytes($directive);

            if ($bytes > 0) {
                $limits[] = intdiv($bytes, 1024);
            }
        }

        return min($limits);
    }

    /** php.ini sizes are written as "2M", "512K" or plain bytes. */
    private function iniBytes(string $directive): int
    {
        $value = trim((string) ini_get($directive));

        if ($value === '') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
