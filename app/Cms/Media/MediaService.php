<?php

namespace App\Cms\Media;

use App\Cms\Cdn\CdnManager;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

/**
 * Stores uploads and generates the resized copies the front end serves.
 *
 * Every file passes the UploadGuard first, so what reaches the disk has a name
 * and contents that agree and carries no code. Raster images are then
 * re-encoded, which drops EXIF metadata (including the GPS position phones
 * embed) and anything smuggled after the image data.
 *
 * Image processing is optional. Without the GD extension uploads still work -
 * files are stored as they arrive, just without thumbnails - rather than the
 * whole media library failing because one extension is missing.
 *
 * Every upload is written to this server first and thumbnailed here, even when
 * a storage provider is configured. Resizing needs a real local path, and four
 * round trips to a bucket per upload would make the library painful to use.
 * Offloading happens once, at the end, when there is something finished to
 * send.
 */
class MediaService
{
    private ?ImageManager $images = null;

    private ?bool $gdAvailable = null;

    public function __construct(
        private UploadGuard $guard,
        private CdnManager $cdn,
    ) {}

    /** The disk uploads are written to before anything is offloaded. */
    public function disk(): string
    {
        return config('cms.media.disk', 'public');
    }

    /** Whether thumbnails and re-encoding can run on this server. */
    public function canProcessImages(): bool
    {
        return $this->gdAvailable ??= extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    /**
     * Built on first use rather than in the constructor, so listing or
     * browsing the library never depends on image support being present.
     */
    private function images(): ?ImageManager
    {
        if (! $this->canProcessImages()) {
            return null;
        }

        // Auto-orientation applies the EXIF rotation before the metadata is
        // stripped, so phone photos do not come out sideways.
        return $this->images ??= new ImageManager(new GdDriver, autoOrientation: true);
    }

    /**
     * @throws UploadRejected when the file fails a safety check.
     */
    public function store(UploadedFile $file, ?string $folder = null, ?int $userId = null): Media
    {
        // Name and contents must agree; the result is the only extension the
        // file will ever be stored under.
        $extension = $this->guard->inspect($file);
        $mime = $this->guard->detectMime($file);

        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        // The stored name is derived, never taken from the upload: a filename
        // is attacker-controlled and has no business steering a disk path.
        $safeName = Str::slug($original) ?: 'file';
        $fileName = Str::limit($safeName, 60, '').'-'.Str::random(8).'.'.$extension;

        $directory = trim($folder ?: now()->format('Y/m'), '/');
        $path = $directory.'/'.$fileName;

        $disk = Storage::disk($this->disk());
        $disk->putFileAs($directory, $file, $fileName);

        $media = new Media([
            'name' => Str::limit($original, 190, ''),
            'file_name' => $fileName,
            'mime_type' => $mime,
            'extension' => $extension,
            'size' => $file->getSize(),
            'disk' => $this->disk(),
            'path' => $path,
            'uploaded_by' => $userId,
        ]);

        if ($this->guard->isRaster($extension) || $extension === 'gif') {
            $this->processImage($media, $path, $extension);
        }

        $media->save();

        $this->offload($media);

        return $media;
    }

    /**
     * Pushes a finished upload to the storage provider, and drops this
     * server's copy if the owner chose not to keep one.
     *
     * A failure here is logged and swallowed. The file is already stored and
     * already serveable from this server, so a bucket having a bad afternoon
     * should not turn a successful upload into an error message - the sync
     * screen will pick it up later.
     */
    private function offload(Media $media): void
    {
        if (! $this->cdn->offloads()) {
            return;
        }

        $disk = Storage::disk($this->disk());

        try {
            foreach ($media->paths() as $path) {
                $this->cdn->push($path);
            }
        } catch (\Throwable $e) {
            Log::warning('[media] Could not send an upload to the storage provider; it stays on this server.', [
                'path' => $media->path,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $media->on_cdn = true;

        if (! $this->cdn->keepsLocalCopy()) {
            foreach ($media->paths() as $path) {
                $disk->delete($path);
            }

            $media->has_local_copy = false;
        }

        $media->save();

        // A path that was pending a moment ago no longer is.
        $this->cdn->flushProgress();
    }

    /**
     * Re-encode, measure and thumbnail an image.
     *
     * Any failure here - a corrupt file, an image too large for the memory
     * limit - leaves the original stored untouched rather than failing the
     * upload. The file has already passed the guard, so it is safe as it is.
     */
    private function processImage(Media $media, string $path, string $extension): void
    {
        $manager = $this->images();

        if (! $manager) {
            $this->measureWithoutGd($media, $path);

            return;
        }

        $disk = Storage::disk($this->disk());

        try {
            $image = $manager->read($disk->get($path));

            $media->width = $image->width();
            $media->height = $image->height();

            // GIFs are left alone: re-encoding would drop their animation.
            if ($this->guard->isRaster($extension)) {
                $disk->put($path, (string) $image->encodeByExtension($extension, quality: 88));
                $media->size = $disk->size($path);
            }

            $conversions = [];

            foreach (config('cms.media.thumbnails', []) as $name => [$width, $height]) {
                // Never upscale: a 300px logo should not become a blurry 1600px one.
                if ($image->width() <= $width && $image->height() <= $height) {
                    continue;
                }

                $resized = $manager->read($disk->get($path))->scaleDown($width, $height);
                $conversionPath = $this->conversionPath($path, $name);

                $disk->put($conversionPath, (string) $resized->encodeByExtension($extension, quality: 82));
                $conversions[$name] = $conversionPath;
            }

            $media->conversions = $conversions ?: null;
        } catch (\Throwable $e) {
            Log::warning('[media] Image processing failed; the original was kept.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Dimensions are still worth recording when thumbnails cannot be made. */
    private function measureWithoutGd(Media $media, string $path): void
    {
        $size = @getimagesize(Storage::disk($this->disk())->path($path));

        if ($size) {
            [$media->width, $media->height] = $size;
        }
    }

    private function conversionPath(string $path, string $name): string
    {
        $info = pathinfo($path);

        return $info['dirname'].'/'.$info['filename'].'-'.$name.'.'.$info['extension'];
    }

    public function delete(Media $media): void
    {
        $media->deleteFiles();
        $media->delete();
    }

    /** Validation rules for an upload field, driven by config. */
    public function uploadRules(bool $imagesOnly = false): array
    {
        $allowed = $this->guard->allowedExtensions();

        if ($imagesOnly) {
            $allowed = array_values(array_intersect($allowed, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg']));
        }

        // jpeg is normalised to jpg internally, but people upload both.
        if (in_array('jpg', $allowed, true)) {
            $allowed[] = 'jpeg';
        }

        return [
            'file',
            'extensions:'.implode(',', array_unique($allowed)),
            'max:'.config('cms.media.max_upload_kb', 10240),
        ];
    }
}
