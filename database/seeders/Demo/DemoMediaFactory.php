<?php

namespace Database\Seeders\Demo;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Paints the placeholder images the demo content points at.
 *
 * Demo data is no use if every post and product renders a broken image, and
 * bundling a folder of stock photos would bloat the repository and drag a
 * licence along with it. So the images are drawn here with GD: a two-tone
 * gradient, a couple of shapes and the label across the middle.
 *
 * Everything lands under the `demo/` folder on the media disk, which is what
 * lets `cms:demo --remove` clean up without guessing.
 */
class DemoMediaFactory
{
    /** Every file this class writes lives here, so removal is a folder delete. */
    public const FOLDER = 'demo';

    /**
     * Background pairs, picked to stay legible behind white text.
     *
     * @var array<string, array{0: array{int, int, int}, 1: array{int, int, int}}>
     */
    private const PALETTES = [
        'espresso' => [[64, 41, 33], [148, 99, 66]],
        'sage' => [[47, 74, 61], [124, 164, 132]],
        'clay' => [[122, 61, 48], [206, 132, 92]],
        'indigo' => [[38, 45, 82], [96, 112, 186]],
        'slate' => [[43, 49, 56], [108, 122, 137]],
        'amber' => [[124, 82, 18], [222, 165, 62]],
        'teal' => [[26, 71, 76], [86, 156, 155]],
        'plum' => [[68, 38, 70], [146, 94, 148]],
    ];

    private string $disk;

    /** @var array<string, string> slug => stored path, so one run paints each file once. */
    private array $made = [];

    public function __construct()
    {
        $this->disk = (string) config('cms.media.disk', 'public');
    }

    public function canDraw(): bool
    {
        return extension_loaded('gd') && function_exists('imagejpeg');
    }

    /**
     * Returns the media-disk path for a demo image, drawing the file and
     * registering it in the media library the first time it is asked for.
     *
     * Returns null when GD is missing: the demo content is still worth having
     * without pictures, so callers simply store no featured image.
     */
    public function image(string $slug, string $label, string $palette = 'slate', int $width = 1600, int $height = 1000): ?string
    {
        if (! $this->canDraw()) {
            return null;
        }

        if (isset($this->made[$slug])) {
            return $this->made[$slug];
        }

        $path = self::FOLDER.'/'.$slug.'.jpg';
        $disk = Storage::disk($this->disk);

        if ($disk->exists($path)) {
            $conversions = $this->existingThumbnails($path);
        } else {
            $canvas = $this->draw($label, $palette, $width, $height);
            $disk->put($path, $this->encode($canvas));
            $conversions = $this->thumbnails($canvas, $path);
            imagedestroy($canvas);
        }

        Media::updateOrCreate(
            ['path' => $path],
            [
                'name' => $label,
                'file_name' => basename($path),
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
                'size' => $disk->size($path),
                'disk' => $this->disk,
                'width' => $width,
                'height' => $height,
                'alt' => $label,
                'title' => $label,
                'conversions' => $conversions ?: null,
            ]
        );

        return $this->made[$slug] = $path;
    }

    /** Deletes every file and media row this class is responsible for. */
    public function purge(): int
    {
        $removed = 0;

        Media::where('path', 'like', self::FOLDER.'/%')->each(function (Media $media) use (&$removed) {
            $media->deleteFiles();
            $media->delete();
            $removed++;
        });

        Storage::disk($this->disk)->deleteDirectory(self::FOLDER);

        return $removed;
    }

    private function draw(string $label, string $palette, int $width, int $height): \GdImage
    {
        [$from, $to] = self::PALETTES[$palette] ?? self::PALETTES['slate'];

        $canvas = imagecreatetruecolor($width, $height);

        // Diagonal gradient, one line at a time. Cheap, and at this size the
        // banding is invisible.
        $steps = $width + $height;

        for ($i = 0; $i < $steps; $i++) {
            $ratio = $i / max(1, $steps - 1);
            $colour = imagecolorallocate(
                $canvas,
                (int) round($from[0] + ($to[0] - $from[0]) * $ratio),
                (int) round($from[1] + ($to[1] - $from[1]) * $ratio),
                (int) round($from[2] + ($to[2] - $from[2]) * $ratio),
            );

            imageline($canvas, $i, 0, 0, $i, $colour);
        }

        $this->addShapes($canvas, $width, $height);
        $this->addLabel($canvas, $label, $width, $height);

        return $canvas;
    }

    /** A few translucent circles, so the images are not flat rectangles. */
    private function addShapes(\GdImage $canvas, int $width, int $height): void
    {
        $light = imagecolorallocatealpha($canvas, 255, 255, 255, 108);
        $dark = imagecolorallocatealpha($canvas, 0, 0, 0, 112);

        imagefilledellipse($canvas, (int) ($width * 0.82), (int) ($height * 0.24), (int) ($width * 0.42), (int) ($width * 0.42), $light);
        imagefilledellipse($canvas, (int) ($width * 0.12), (int) ($height * 0.88), (int) ($width * 0.30), (int) ($width * 0.30), $dark);
        imagefilledellipse($canvas, (int) ($width * 0.62), (int) ($height * 0.92), (int) ($width * 0.16), (int) ($width * 0.16), $light);
    }

    /**
     * GD's built-in fonts top out around 15px, which is unreadable on a
     * 1600px canvas. So the text is drawn small and scaled up: slightly soft
     * edges, but it reads at any size and needs no font file shipped.
     */
    private function addLabel(\GdImage $canvas, string $label, int $width, int $height): void
    {
        $text = strtoupper(mb_substr($label, 0, 34));
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);

        if ($textWidth < 1) {
            return;
        }

        // The strip carries a real alpha channel. imagecolortransparent() is
        // a palette feature and is ignored when resampling a truecolor image,
        // which is what leaves a solid box behind the text.
        $strip = imagecreatetruecolor($textWidth, $textHeight);
        imagealphablending($strip, false);
        imagesavealpha($strip, true);

        // Transparent *white* rather than transparent black: the resample
        // blends edge pixels towards this colour, and white leaves no halo.
        imagefilledrectangle($strip, 0, 0, $textWidth, $textHeight, imagecolorallocatealpha($strip, 255, 255, 255, 127));
        imagestring($strip, $font, 0, 0, $text, imagecolorallocatealpha($strip, 255, 255, 255, 0));

        // Scale to roughly 70% of the canvas width, keeping the aspect ratio.
        $targetWidth = (int) ($width * 0.7);
        $scale = $targetWidth / $textWidth;
        $targetHeight = (int) round($textHeight * $scale);

        // A soft band behind the label, so white text stays readable over the
        // light end of the gradient.
        $band = (int) round($targetHeight * 0.9);
        imagefilledrectangle(
            $canvas,
            0,
            (int) (($height - $targetHeight) / 2) - $band,
            $width,
            (int) (($height + $targetHeight) / 2) + $band,
            imagecolorallocatealpha($canvas, 0, 0, 0, 98)
        );

        imagecopyresampled(
            $canvas,
            $strip,
            (int) (($width - $targetWidth) / 2),
            (int) (($height - $targetHeight) / 2),
            0,
            0,
            $targetWidth,
            $targetHeight,
            $textWidth,
            $textHeight
        );

        imagedestroy($strip);
    }

    /**
     * Matches MediaService's naming so admin thumbnails resolve the same way
     * they do for a real upload.
     *
     * @return array<string, string>
     */
    private function thumbnails(\GdImage $canvas, string $path): array
    {
        $disk = Storage::disk($this->disk);
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        $conversions = [];

        foreach (config('cms.media.thumbnails', []) as $name => [$maxWidth, $maxHeight]) {
            if ($width <= $maxWidth && $height <= $maxHeight) {
                continue; // Never upscale, the same rule the media service follows.
            }

            $scale = min($maxWidth / $width, $maxHeight / $height);
            $resized = imagescale($canvas, (int) round($width * $scale), (int) round($height * $scale));

            if ($resized === false) {
                continue;
            }

            $conversionPath = $this->conversionPath($path, $name);
            $disk->put($conversionPath, $this->encode($resized, 82));
            imagedestroy($resized);

            $conversions[$name] = $conversionPath;
        }

        return $conversions;
    }

    /** @return array<string, string> */
    private function existingThumbnails(string $path): array
    {
        $disk = Storage::disk($this->disk);
        $conversions = [];

        foreach (array_keys(config('cms.media.thumbnails', [])) as $name) {
            $conversionPath = $this->conversionPath($path, $name);

            if ($disk->exists($conversionPath)) {
                $conversions[$name] = $conversionPath;
            }
        }

        return $conversions;
    }

    private function conversionPath(string $path, string $name): string
    {
        $info = pathinfo($path);

        return $info['dirname'].'/'.$info['filename'].'-'.$name.'.'.$info['extension'];
    }

    private function encode(\GdImage $image, int $quality = 88): string
    {
        ob_start();
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }
}
