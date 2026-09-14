<?php

namespace App\Cms\Media;

use Illuminate\Http\UploadedFile;

/**
 * Decides whether an uploaded file may be stored, and under which extension.
 *
 * The rule is that the name and the contents have to agree. Checking only one
 * of them is not enough:
 *
 *  - Trusting the name lets a script be uploaded as "photo.jpg".
 *  - Trusting the contents - which is what Laravel's `mimes` rule does - lets a
 *    file that merely *starts* with JPEG bytes be stored as "page.html", and a
 *    browser will then run whatever follows those bytes as HTML on this site.
 *
 * So the extension must be on the allowlist, the detected type must be one
 * that extension is allowed to contain, and the file is stored under the
 * extension that was checked - never under whatever the client sent.
 */
class UploadGuard
{
    /**
     * For each allowed extension, the MIME types its contents may be detected
     * as. Several appear more than once because libmagic reports office
     * formats inconsistently across versions: a .docx is a zip archive
     * underneath and older systems say so.
     */
    private const COMPATIBLE = [
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'avif' => ['image/avif'],
        'svg' => ['image/svg+xml', 'image/svg', 'text/xml', 'application/xml', 'text/plain', 'text/html'],
        'ico' => ['image/vnd.microsoft.icon', 'image/x-icon'],
        'pdf' => ['application/pdf'],
        'mp4' => ['video/mp4', 'application/mp4'],
        'webm' => ['video/webm', 'audio/webm'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'doc' => ['application/msword', 'application/CDFV2', 'application/vnd.ms-office', 'application/x-ole-storage'],
        'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/vnd.ms-office', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],

        // Formats below are only offered for paid downloads. They are never
        // rendered by a browser on this site - a download is always sent as an
        // attachment from a disk with no public URL - so the risk they carry
        // is different from anything in the media library.
        'ppt' => ['application/vnd.ms-powerpoint', 'application/CDFV2', 'application/vnd.ms-office', 'application/x-ole-storage'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'rar' => ['application/x-rar', 'application/x-rar-compressed', 'application/vnd.rar'],
        '7z' => ['application/x-7z-compressed'],
        'gz' => ['application/gzip', 'application/x-gzip', 'application/x-tar'],
        'epub' => ['application/epub+zip', 'application/zip'],
        'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg', 'application/octet-stream'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4', 'application/octet-stream'],
        'ogg' => ['audio/ogg', 'video/ogg', 'application/ogg'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'application/csv', 'text/plain'],
        'rtf' => ['application/rtf', 'text/rtf'],
        'psd' => ['image/vnd.adobe.photoshop', 'application/x-photoshop', 'application/octet-stream'],
    ];

    /**
     * Where an upload is going, which decides the allowlist it is judged by.
     *
     * The media library is the strict one, because its files are served from
     * the web root by the web server itself. Paid downloads can be looser
     * about formats without being looser about safety.
     */
    public const CONTEXT_MEDIA = 'media';

    public const CONTEXT_DOWNLOAD = 'download';

    /** Raster formats: these are re-encoded to strip metadata and payloads. */
    public const RASTER = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(private SvgSanitizer $svg) {}

    /**
     * Check a file. Returns the extension it must be stored under.
     *
     * @throws UploadRejected with a message safe to show the uploader.
     */
    public function inspect(UploadedFile $file, string $context = self::CONTEXT_MEDIA): string
    {
        if (! $file->isValid()) {
            throw new UploadRejected('The upload did not complete. Please try again.');
        }

        $allowed = $this->allowedExtensions($context);
        $extension = $this->normaliseExtension($file->getClientOriginalExtension());

        if (! in_array($extension, $allowed, true)) {
            throw new UploadRejected(
                'Files of type ".'.$extension.'" cannot be uploaded. Allowed: '.implode(', ', $allowed).'.'
            );
        }

        // A double extension like "photo.php.jpg" is a classic trick for
        // servers that pick a handler from any extension in the name. It is a
        // web-root concern, so it does not apply to downloads - and refusing
        // "plugin.php.zip" there would reject a perfectly ordinary product.
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        if ($context !== self::CONTEXT_DOWNLOAD
            && preg_match('/\.(php\d?|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp|shtml|htaccess|exe|bat|cmd)$/i', $base)) {
            throw new UploadRejected('That filename is not allowed.');
        }

        $detected = $this->detectMime($file);

        if (! in_array($detected, self::COMPATIBLE[$extension] ?? [], true)) {
            throw new UploadRejected(
                'This file\'s contents do not match its ".'.$extension.'" extension, so it was not uploaded.'
            );
        }

        // A paid download is a product, and its bytes are the thing being
        // sold. Source code is a perfectly normal thing to sell, and the file
        // is never executed or rendered by this site - it sits on a disk with
        // no URL and leaves only as an attachment - so it is neither scanned
        // for code nor rewritten. Doing either would damage what the seller
        // uploaded without making anybody safer.
        if ($context === self::CONTEXT_DOWNLOAD) {
            return $extension;
        }

        $this->scanForCode($file, $extension);

        if ($extension === 'svg') {
            $this->svg->cleanFile($file->getRealPath());
        }

        return $extension;
    }

    /** The detected type, recorded on the media row instead of the client's claim. */
    public function detectMime(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if ($path && function_exists('finfo_open')) {
            $info = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($info, $path);
            finfo_close($info);

            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }

        return strtolower((string) $file->getMimeType());
    }

    /** @return string[] */
    public function allowedExtensions(string $context = self::CONTEXT_MEDIA): array
    {
        $configured = array_map([$this, 'normaliseExtension'], $context === self::CONTEXT_DOWNLOAD
            ? config('cms.downloads.allowed_extensions', [])
            : config('cms.media.allowed_mimes', []));

        // Only extensions this class knows how to verify can ever be allowed,
        // whatever the config says, so a config typo cannot open a hole.
        return array_values(array_unique(array_intersect($configured, array_keys(self::COMPATIBLE))));
    }

    public function isRaster(string $extension): bool
    {
        return in_array($extension, self::RASTER, true);
    }

    private function normaliseExtension(?string $extension): string
    {
        $extension = strtolower(trim((string) $extension));

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    /**
     * Refuse anything carrying a PHP open tag.
     *
     * Such a file cannot run from the uploads folder - the web server refuses
     * to execute anything there - but it has no business being stored either,
     * and keeping it out means a future server misconfiguration cannot turn
     * an old upload into a backdoor.
     *
     * Only the unambiguous "<?php" is matched in binary files: shorter
     * sequences occur by chance in large images and would reject real photos.
     */
    private function scanForCode(UploadedFile $file, string $extension): void
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if (! $handle) {
            throw new UploadRejected('The file could not be read.');
        }

        $textual = $extension === 'svg';
        $carry = '';

        try {
            while (! feof($handle)) {
                // Read in chunks, keeping the tail of the previous chunk so a
                // marker split across the boundary is still caught.
                $chunk = $carry.fread($handle, 1 << 16);

                if (stripos($chunk, '<?php') !== false || ($textual && str_contains($chunk, '<?='))) {
                    throw new UploadRejected('This file contains program code and cannot be uploaded.');
                }

                $carry = substr($chunk, -8);
            }
        } finally {
            fclose($handle);
        }
    }
}
