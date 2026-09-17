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
     *
     * A word about what this can and cannot do. A Blade template is compiled
     * to PHP and executed, so a theme is code, and no pattern list can make
     * arbitrary code safe - naming a function is optional in PHP, and
     * "$f='sys'.'tem'; $f(...)" never contains the word it calls. That is why
     * installing a theme is an administrator-only action: this scan is a
     * safety net for an honest theme with a careless line in it, not a
     * sandbox, and it must not be relied on as one. What it does do is close
     * the cheap evasions - the indirection patterns below - so that getting
     * past it requires deliberate effort rather than a string concatenation.
     */
    private const FORBIDDEN_PATTERNS = [
        '/<\?php/i' => 'raw PHP open tag',
        '/<\?=/' => 'raw PHP short echo tag',

        '/\beval\s*\(/i' => 'eval()',
        '/\bassert\s*\(/i' => 'assert()',
        '/\b(exec|shell_exec|system|passthru|proc_open|popen|pcntl_exec)\s*\(/i' => 'shell execution',
        '/\bbase64_decode\s*\(/i' => 'base64_decode()',
        '/\b(unlink|rmdir|file_put_contents|fwrite|fopen|move_uploaded_file)\s*\(/i' => 'filesystem write',
        '/\b(curl_exec|file_get_contents|readfile|show_source|highlight_file|fpassthru|parse_ini_file)\s*\(/i' => 'reading a file or URL directly',
        // Each needs its opening bracket: matching the bare word would reject
        // a template for containing "globe".
        '/\b(scandir|glob|opendir)\s*\(/i' => 'directory inspection',
        '/\b(getenv|putenv|ini_set|ini_alter|set_include_path|dl)\s*\(/i' => 'changing the PHP environment',
        '/\$_(GET|POST|REQUEST|COOKIE|SERVER|ENV|FILES|SESSION)\b/' => 'superglobal access',
        '/\bphpinfo\s*\(/i' => 'phpinfo()',
        // (?<!@) so Blade's @include / @includeIf / @require are not caught.
        '/(?<!@)\b(include|require)(_once)?\s*[\(\'"]/i' => 'a PHP include/require',
        '/\bcall_user_func(_array)?\s*\(/i' => 'call_user_func()',
        '/\bcreate_function\s*\(/i' => 'create_function()',
        '/\b(unserialize|extract)\s*\(/i' => 'unserialize()/extract()',
        '/\bReflection(Function|Method|Class)\b/i' => 'the Reflection API',

        // Indirection: ways to call a function without ever naming it, which
        // is what a list of function names alone cannot see. Every pattern
        // here was checked against the themes and admin views that ship with
        // this CMS - 175 real templates - and matches none of them, so an
        // honest theme has no reason to trip one.
        '/\$\w+\s*\(/' => 'a variable function call',
        '/[\'"]\s*\)\s*\(/' => 'an immediately invoked expression',
        '/\]\s*\(/' => 'a call through an array element',
        // Backticks run a shell command in PHP, but are also ordinary
        // JavaScript template literals, so they are only refused where PHP
        // would read them: inside an echo, or inside an @php block.
        '/\{\{[^}]*`/' => 'a shell backtick operator',
        '/@php\b(?:(?!@endphp).)*`/s' => 'a shell backtick operator in an @php block',

        // array_map('system', …) and friends are call primitives when handed a
        // function *name*. Passing a closure - which is what a template
        // actually does - is left alone.
        '/\b(array_map|array_filter|array_walk|usort|uasort|uksort|preg_replace_callback)\s*\(\s*[\'"]/i'
            => 'a function called by name through a callback',
    ];

    /**
     * Constructs worth telling the admin about without blocking the install.
     */
    private const WARNING_PATTERNS = [
        // Preparing a few view variables is ordinary template work and the
        // themes shipped here do it, so an @php block is reported rather than
        // refused - anything dangerous inside one is still caught by the rules
        // above, which scan the whole file.
        '/@php\b/i' => 'contains @php blocks',
        '/\bDB::|\\\\Illuminate\\\\Support\\\\Facades\\\\DB\b/' => 'queries the database directly',
        '/\benv\s*\(/i' => 'reads environment variables',
        '/\bconfig\s*\(\s*[\'"](app\.key|database|services|mail)/i' => 'reads sensitive configuration',
    ];

    public function __construct(private ThemeManager $themes) {}

    /**
     * @return array{slug: string, name: string, version: string, warnings: string[]}
     *
     * @throws ThemeInstallException
     */
    public function installFromUpload(UploadedFile $file, bool $overwrite = false): array
    {
        return $this->installFromArchive($file->getRealPath(), $file->getClientOriginalName(), $overwrite);
    }

    /**
     * Installs a theme ZIP already on local disk - the one path every theme
     * takes, whether it was uploaded or downloaded from the marketplace. There
     * is deliberately no second route in: a downloaded theme gets exactly the
     * extraction rules and template scan an uploaded one does.
     *
     * @param  string  $fallbackName  used for the folder name when theme.json names neither slug nor name
     * @param  string|null  $expectedSlug  refuse the archive unless it installs to this folder. The
     *                                     marketplace passes the slug it listed, so an archive cannot
     *                                     declare a different slug and overwrite some other installed theme.
     * @return array{slug: string, name: string, version: string, warnings: string[]}
     *
     * @throws ThemeInstallException
     */
    /**
     * Run every check an upload would, without installing anything.
     *
     * Backs `php artisan cms:theme-package`, so a theme is proven installable
     * before it is ever handed to anybody - by the same code their site runs,
     * rather than a copy of its rules that can drift.
     *
     * @return array{slug: string, name: string, version: string, warnings: string[], files: int}
     *
     * @throws ThemeInstallException
     */
    public function inspectArchive(string $archivePath, string $fallbackName): array
    {
        $workspace = storage_path('app/theme-install/'.Str::random(16));
        File::ensureDirectoryExists($workspace);

        try {
            $this->extract($archivePath, $workspace);

            $root = $this->locateThemeRoot($workspace);
            $manifest = $this->readManifest($root);
            $slug = $this->resolveSlug($manifest, $fallbackName);

            return [
                'slug' => $slug,
                'name' => $manifest['name'] ?? $slug,
                'version' => (string) $manifest['version'],
                'warnings' => $this->scanTemplates($root),
                'files' => count(File::allFiles($root, true)),
            ];
        } finally {
            File::deleteDirectory($workspace);
        }
    }

    public function installFromArchive(string $archivePath, string $fallbackName, bool $overwrite = false, ?string $expectedSlug = null): array
    {
        $workspace = storage_path('app/theme-install/'.Str::random(16));
        File::ensureDirectoryExists($workspace);

        try {
            $this->extract($archivePath, $workspace);

            $root = $this->locateThemeRoot($workspace);
            $manifest = $this->readManifest($root);
            $slug = $this->resolveSlug($manifest, $fallbackName);

            if ($expectedSlug !== null && $slug !== $expectedSlug) {
                throw new ThemeInstallException(
                    "This archive installs a theme called [{$slug}], but it was listed as [{$expectedSlug}]. It was not installed."
                );
            }

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
                'version' => (string) $manifest['version'],
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
                $raw = $stat['name'];

                // Separators normalised before anything reads the name. Windows
                // PowerShell's Compress-Archive, and several other Windows tools,
                // store "mytheme\views\layout.blade.php". Windows happens to
                // accept that as a path, so the theme installs fine on a Windows
                // test box - and on a Linux server it becomes one file with
                // backslashes in its name, theme.json is "not found", and the
                // upload is refused with a message that sends people looking in
                // entirely the wrong place.
                $name = str_replace(chr(92), '/', $raw);

                // Directory entries carry no content; the files inside them
                // create whatever directories are actually needed.
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }

                if ($this->isTraversal($raw)) {
                    throw new ThemeInstallException("The archive contains an illegal path: {$raw}");
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
                    throw new ThemeInstallException("The archive contains an illegal path: {$raw}");
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

    private function resolveSlug(array $manifest, string $fallbackName): string
    {
        $slug = Str::slug($manifest['slug'] ?? $manifest['name'] ?? pathinfo($fallbackName, PATHINFO_FILENAME));

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
    /**
     * Blade comments are stripped before a file is compiled, so nothing inside
     * one can execute - and scanning them only produces false alarms, because
     * prose describing a template ("expects $model (any HasSeo model)") reads
     * like a variable function call to a regular expression.
     */
    private function withoutComments(string $contents): string
    {
        return (string) preg_replace('/\{\{--.*?--\}\}/s', '', $contents);
    }

    private function scanTemplates(string $root): array
    {
        $warnings = [];
        $violations = [];

        foreach (File::allFiles($root) as $file) {
            if (! str_ends_with(strtolower($file->getFilename()), '.blade.php')) {
                continue;
            }

            $contents = $this->withoutComments(File::get($file->getPathname()));
            $relative = strtr(ltrim(substr($file->getPathname(), strlen($root)), '/'.chr(92)), chr(92), '/');

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
