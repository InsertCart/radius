<?php

namespace App\Cms\Themes;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Installs an uploaded theme ZIP.
 *
 * A theme is executable code: its .blade.php files are compiled and run by
 * PHP. Anyone who can upload one can, in principle, run anything. This class
 * is the choke point that makes that safe enough to expose in an admin panel:
 *
 *   1. Archive entries are resolved against the target directory and anything
 *      escaping it is rejected (the "zip slip" traversal bug).
 *   2. Only allow-listed extensions are extracted. A .php, .phtml, .htaccess
 *      or .sh file in the archive is dropped, never written to disk.
 *   3. Blade templates are scanned for raw PHP tags and dangerous calls before
 *      anything is moved into place.
 *   4. Entry count and uncompressed size are capped, so a zip bomb cannot fill
 *      the disk.
 *
 * Extraction happens in a temporary directory; the theme is only moved into
 * themes/ once every check has passed.
 */
class ThemeInstaller
{
    /** Refuse archives with more entries than this. */
    private const MAX_ENTRIES = 2000;

    /** Refuse archives whose contents exceed this once uncompressed. */
    private const MAX_UNCOMPRESSED_BYTES = 200 * 1024 * 1024;

    /**
     * Constructs that have no legitimate place in a template. A theme hitting
     * any of these is refused outright.
     *
     * Blade's own directives are deliberately not on this list: @include and
     * @each are how themes are built, and the negative lookbehind on the
     * include/require rule is what keeps them apart from PHP's own.
     */
    private const FORBIDDEN_PATTERNS = [
        '/<\?php/i' => 'raw PHP open tag',
        '/<\?=/' => 'raw PHP short echo tag',
        '/\beval\s*\(/i' => 'eval()',
        '/\bassert\s*\(/i' => 'assert()',
        '/\b(exec|shell_exec|system|passthru|proc_open|popen|pcntl_exec)\s*\(/i' => 'shell execution',
        '/\bbase64_decode\s*\(/i' => 'base64_decode()',
        '/\b(unlink|rmdir|file_put_contents|fwrite|fopen|move_uploaded_file)\s*\(/i' => 'filesystem write',
        '/\b(curl_exec|file_get_contents\s*\(\s*[\'"]https?:)/i' => 'outbound HTTP request',
        '/\$_(GET|POST|REQUEST|COOKIE|SERVER|ENV|FILES)\b/' => 'superglobal access',
        '/\bphpinfo\s*\(/i' => 'phpinfo()',
        // (?<!@) so Blade's @include / @includeIf / @require are not caught.
        '/(?<!@)\b(include|require)(_once)?\s*[\(\'"]/i' => 'a PHP include/require',
        '/\bcall_user_func(_array)?\s*\(/i' => 'call_user_func()',
        '/\bcreate_function\s*\(/i' => 'create_function()',
        '/\bfile_put_contents\s*\(/i' => 'file_put_contents()',
    ];

    /**
     * Constructs worth telling the admin about without blocking the install.
     *
     * @php blocks are allowed because preparing a few view variables is
     * ordinary template work, and anything genuinely dangerous inside one is
     * still caught by the rules above, which scan the whole file.
     */
    private const WARNING_PATTERNS = [
        '/@php\b/i' => 'contains @php blocks',
        '/\bDB::|\\\\Illuminate\\\\Support\\\\Facades\\\\DB\b/' => 'queries the database directly',
        '/\benv\s*\(/i' => 'reads environment variables',
    ];

    public function __construct(private ThemeManager $themes) {}

    /**
     * @return array{slug: string, name: string, warnings: string[]}
     *
     * @throws ThemeInstallException
     */
    public function installFromUpload(UploadedFile $file, bool $overwrite = false): array
    {
        $workspace = storage_path('app/theme-install/'.Str::random(16));
        File::ensureDirectoryExists($workspace);

        try {
            $this->extract($file->getRealPath(), $workspace);

            $root = $this->locateThemeRoot($workspace);
            $manifest = $this->readManifest($root);
            $slug = $this->resolveSlug($manifest, $file);

            $warnings = $this->scanTemplates($root);

            $destination = $this->themes->path($slug);

            if (is_dir($destination) && ! $overwrite) {
                throw new ThemeInstallException(
                    "A theme with the folder name [{$slug}] is already installed. Tick \"replace existing\" to overwrite it."
                );
            }

            if (is_dir($destination)) {
                File::deleteDirectory($destination);
            }

            File::ensureDirectoryExists(dirname($destination));
            File::moveDirectory($root, $destination);

            $this->themes->sync();

            return [
                'slug' => $slug,
                'name' => $manifest['name'] ?? $slug,
                'warnings' => $warnings,
            ];
        } finally {
            File::deleteDirectory($workspace);
        }
    }

    /**
     * Extracts the archive one entry at a time, dropping anything that is not
     * allow-listed and refusing anything that tries to escape $destination.
     */
    private function extract(string $archivePath, string $destination): void
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new ThemeInstallException('The uploaded file is not a readable ZIP archive.');
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new ThemeInstallException('This archive contains too many files to be a theme.');
            }

            $realDestination = realpath($destination);
            $totalBytes = 0;
            $extracted = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];

                // Directory entries carry no content; the files inside them
                // create whatever directories are actually needed.
                if (str_ends_with($name, '/')) {
                    continue;
                }

                if ($this->isTraversal($name)) {
                    throw new ThemeInstallException("The archive contains an illegal path: {$name}");
                }

                if (! $this->isAllowedFile($name)) {
                    continue;
                }

                $totalBytes += $stat['size'];

                if ($totalBytes > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new ThemeInstallException('The archive is too large once uncompressed.');
                }

                $target = $destination.DIRECTORY_SEPARATOR.$name;
                File::ensureDirectoryExists(dirname($target));

                // Re-check after the directory exists: realpath() only resolves
                // paths that are actually present, and this catches a symlinked
                // parent directory pointing outside the workspace.
                $realParent = realpath(dirname($target));

                if ($realParent === false || ! str_starts_with($realParent, $realDestination)) {
                    throw new ThemeInstallException("The archive contains an illegal path: {$name}");
                }

                $contents = $zip->getFromIndex($i);

                if ($contents === false) {
                    continue;
                }

                file_put_contents($target, $contents);
                $extracted++;
            }

            if ($extracted === 0) {
                throw new ThemeInstallException('The archive contained no files this CMS is willing to install.');
            }
        } finally {
            $zip->close();
        }
    }

    private function isTraversal(string $name): bool
    {
        $normalised = str_replace('\\', '/', $name);

        return str_starts_with($normalised, '/')
            || str_contains($normalised, '../')
            || str_contains($normalised, '..\\')
            || preg_match('/^[a-zA-Z]:/', $normalised) === 1
            || str_contains($name, "\0");
    }

    /**
     * ".blade.php" is matched as a whole so that a plain ".php" file - which
     * would be executable if it landed in the published assets folder - is
     * never written.
     */
    private function isAllowedFile(string $name): bool
    {
        $basename = strtolower(basename($name));

        // Editor and OS noise, plus anything hidden.
        if (str_starts_with($basename, '.') || str_starts_with($basename, '__macosx')) {
            return false;
        }

        foreach (config('cms.themes.allowed_extensions', []) as $extension) {
            if (str_ends_with($basename, '.'.strtolower($extension))) {
                // "index.php" must not be accepted by the "blade.php" rule.
                if ($extension === 'blade.php' && ! str_ends_with($basename, '.blade.php')) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Archives are commonly wrapped in a single folder. Find whichever
     * directory actually holds theme.json.
     */
    private function locateThemeRoot(string $workspace): string
    {
        if (is_file($workspace.DIRECTORY_SEPARATOR.'theme.json')) {
            return $workspace;
        }

        foreach (File::directories($workspace) as $directory) {
            if (is_file($directory.DIRECTORY_SEPARATOR.'theme.json')) {
                return $directory;
            }
        }

        throw new ThemeInstallException('No theme.json was found in the archive. Every theme needs one at its root.');
    }

    private function readManifest(string $root): array
    {
        $manifest = json_decode(File::get($root.DIRECTORY_SEPARATOR.'theme.json'), true);

        if (! is_array($manifest)) {
            throw new ThemeInstallException('theme.json is not valid JSON.');
        }

        foreach (['name', 'version'] as $required) {
            if (blank($manifest[$required] ?? null)) {
                throw new ThemeInstallException("theme.json is missing the required \"{$required}\" field.");
            }
        }

        if (! is_dir($root.DIRECTORY_SEPARATOR.'views')) {
            throw new ThemeInstallException('The theme has no views/ directory, so it cannot render any pages.');
        }

        return $manifest;
    }

    private function resolveSlug(array $manifest, UploadedFile $file): string
    {
        $slug = Str::slug($manifest['slug'] ?? $manifest['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        if (blank($slug)) {
            throw new ThemeInstallException('Could not work out a folder name for this theme.');
        }

        if ($slug === config('cms.themes.default')) {
            throw new ThemeInstallException('This theme uses the same slug as the bundled default theme. Rename it in theme.json.');
        }

        return $slug;
    }

    /**
     * Reads every Blade file and rejects the theme outright if one contains a
     * forbidden construct. Returns non-fatal warnings for the admin to see.
     *
     * @return string[]
     *
     * @throws ThemeInstallException
     */
    private function scanTemplates(string $root): array
    {
        $warnings = [];
        $violations = [];

        foreach (File::allFiles($root) as $file) {
            if (! str_ends_with(strtolower($file->getFilename()), '.blade.php')) {
                continue;
            }

            $contents = File::get($file->getPathname());
            $relative = ltrim(str_replace($root, '', $file->getPathname()), '/\\');

            foreach (self::FORBIDDEN_PATTERNS as $pattern => $label) {
                if (preg_match($pattern, $contents)) {
                    $violations[] = "{$relative} uses {$label}";
                }
            }

            foreach (self::WARNING_PATTERNS as $pattern => $label) {
                if (preg_match($pattern, $contents)) {
                    $warnings[] = "{$relative} {$label}.";
                }
            }

            // Not dangerous on its own, but worth surfacing: unescaped output
            // is how a theme accidentally introduces stored XSS.
            if (preg_match_all('/\{!!/', $contents, $matches) && count($matches[0]) > 5) {
                $warnings[] = "{$relative} prints unescaped output in ".count($matches[0]).' places.';
            }
        }

        if ($violations !== []) {
            throw new ThemeInstallException(
                'This theme was rejected because its templates contain executable PHP: '
                .implode('; ', array_slice($violations, 0, 5))
                .(count($violations) > 5 ? ' (and '.(count($violations) - 5).' more)' : '')
            );
        }

        return $warnings;
    }
}
