<?php

namespace App\Cms\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fetches an archive to local disk and proves it is the file that was
 * published.
 *
 * Shared by the core updater and the theme marketplace, because both download
 * something that becomes code on this server and both need exactly the same
 * guarantees. Keeping one copy matters more than it looks: a fix applied to one
 * of two near-identical downloaders silently misses the other.
 *
 * Streamed to a file rather than held in memory: a full release runs to tens of
 * megabytes, and reading that into a string would exhaust the memory limit on
 * the shared hosting this CMS is most often installed on.
 *
 * The checksum is the point of this class. "The download finished" is not the
 * same question as "is this the file the seller published".
 */
class VerifiedDownload
{
    /**
     * @param  array{
     *     require_https?: bool,
     *     require_checksum?: bool,
     *     max_bytes?: int,
     *     timeout?: int,
     *     allowed_hosts?: string[],
     *     block_private_hosts?: bool,
     * }  $options
     * @return string absolute path to the verified archive
     *
     * @throws DownloadRefused
     */
    public function fetch(?string $url, ?string $sha256, string $directory, string $filenamePrefix, array $options = []): string
    {
        $options += [
            'require_https' => true,
            'require_checksum' => true,
            'max_bytes' => 0,
            'timeout' => 900,
            'allowed_hosts' => [],
            'block_private_hosts' => false,
        ];

        $this->assertUrl($url, $options);

        if ($options['require_checksum'] && blank($sha256)) {
            throw new DownloadRefused(DownloadRefused::MISSING_CHECKSUM);
        }

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new DownloadRefused(DownloadRefused::WORKSPACE);
        }

        $path = $directory.DIRECTORY_SEPARATOR.$filenamePrefix.'-'.Str::random(8).'.zip';

        $this->stream($url, $path, $options);
        $this->assertSize($path, (int) $options['max_bytes']);
        $this->assertChecksum($sha256, $path);

        return $path;
    }

    /** @throws DownloadRefused */
    private function assertUrl(?string $url, array $options): void
    {
        if (blank($url)) {
            throw new DownloadRefused(DownloadRefused::MISSING_URL);
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true) || blank(parse_url($url, PHP_URL_HOST))) {
            throw new DownloadRefused(DownloadRefused::INVALID_URL);
        }

        // Plain HTTP means anyone between here and the server can replace the
        // archive with their own, and it would then run on this site.
        if ($scheme !== 'https' && $options['require_https']) {
            throw new DownloadRefused(DownloadRefused::INSECURE_URL);
        }

        $this->assertHostAllowed($url, $options);
    }

    /**
     * Where the address comes from a third-party document - a marketplace
     * catalogue - whoever controls that document chooses what this server
     * fetches. Refusing internal addresses stops it being used to probe the
     * host's own network or a cloud metadata endpoint.
     *
     * Checked for every redirect hop as well as the first address, since a
     * public URL that answers "302 -> http://169.254.169.254/" would otherwise
     * walk straight past a check made only at the start. What this cannot close
     * is DNS that answers differently between the check and the connection; the
     * response is written to a file and never shown back, which keeps what
     * could be learned that way small.
     *
     * @throws DownloadRefused
     */
    private function assertHostAllowed(string $url, array $options): void
    {
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
        $allowed = array_map('strtolower', array_filter((array) $options['allowed_hosts']));

        if ($allowed !== [] && ! in_array($host, $allowed, true)) {
            throw new DownloadRefused(DownloadRefused::BLOCKED_HOST, ['host' => $host]);
        }

        if (! $options['block_private_hosts']) {
            return;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (@gethostbynamel($host) ?: []);

        foreach ($addresses as $address) {
            $public = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

            if ($public === false) {
                throw new DownloadRefused(DownloadRefused::BLOCKED_HOST, ['host' => $host]);
            }
        }
    }

    /** @throws DownloadRefused */
    private function stream(string $url, string $path, array $options): void
    {
        $guzzle = ['sink' => $path];

        if ($options['allowed_hosts'] !== [] || $options['block_private_hosts']) {
            $guzzle['allow_redirects'] = [
                'max' => 5,
                'protocols' => $options['require_https'] ? ['https'] : ['http', 'https'],
                'on_redirect' => function ($request, $response, $uri) use ($options) {
                    $this->assertHostAllowed((string) $uri, $options);
                },
            ];
        }

        try {
            $response = Http::timeout((int) $options['timeout'])
                ->withUserAgent(config('cms.name', 'CMS').'/'.cms_version())
                ->withOptions($guzzle)
                ->get($url);
        } catch (\Throwable $e) {
            @unlink($path);

            // A redirect to a refused host surfaces here, wrapped by Guzzle.
            for ($cause = $e; $cause; $cause = $cause->getPrevious()) {
                if ($cause instanceof DownloadRefused) {
                    throw $cause;
                }
            }

            throw new DownloadRefused(DownloadRefused::UNREACHABLE);
        }

        if ($response->failed()) {
            @unlink($path);

            throw new DownloadRefused(DownloadRefused::HTTP_ERROR, ['status' => $response->status()]);
        }

        if (! is_file($path) || filesize($path) === 0) {
            @unlink($path);

            throw new DownloadRefused(DownloadRefused::EMPTY_FILE);
        }
    }

    /** @throws DownloadRefused */
    private function assertSize(string $path, int $max): void
    {
        $size = (int) filesize($path);

        if ($max > 0 && $size > $max) {
            @unlink($path);

            throw new DownloadRefused(DownloadRefused::TOO_LARGE, ['bytes' => $size]);
        }
    }

    /** @throws DownloadRefused */
    private function assertChecksum(?string $sha256, string $path): void
    {
        if (blank($sha256)) {
            return;
        }

        $actual = hash_file('sha256', $path);

        // hash_equals rather than ===: comparison time should not depend on
        // how much of the hash matched.
        if (! is_string($actual) || ! hash_equals(strtolower($sha256), $actual)) {
            @unlink($path);

            throw new DownloadRefused(DownloadRefused::CHECKSUM_MISMATCH);
        }
    }
}
