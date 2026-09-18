<?php

namespace App\Cms\Cdn\Adapters;

use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;

/**
 * A Flysystem adapter for the S3 REST API, spoken over the HTTP client.
 *
 * One adapter covers Amazon S3, DigitalOcean Spaces, Cloudflare R2, Google
 * Cloud Storage, Wasabi, Backblaze B2 and MinIO, because they all implement
 * the same handful of verbs. What differs between them - the endpoint, the
 * region, whether the bucket goes in the hostname or the path - is
 * configuration, not code.
 *
 * Objects are written without an ACL header. Modern S3 buckets have ACLs
 * disabled outright and reject x-amz-acl, and R2 and GCS never supported it,
 * so public access is the bucket owner's business: a bucket policy, an R2
 * custom domain, or allUsers on GCS. Sending the header would break more
 * providers than it would help.
 */
class S3CompatibleAdapter implements FilesystemAdapter
{
    private SignatureV4 $signer;

    /**
     * @param  string  $endpoint   base address, no trailing slash, no bucket
     * @param  bool  $pathStyle  true puts the bucket in the path, false in the host
     */
    public function __construct(
        private string $endpoint,
        private string $bucket,
        string $key,
        string $secret,
        private string $region = 'us-east-1',
        private bool $pathStyle = true,
        private string $prefix = '',
        private int $timeout = 30,
    ) {
        $this->endpoint = rtrim($endpoint, '/');
        $this->prefix = trim($prefix, '/');
        $this->signer = new SignatureV4($key, $secret, $region ?: 'us-east-1');
    }

    // Reads ---------------------------------------------------------------

    public function fileExists(string $path): bool
    {
        try {
            return $this->request('HEAD', $this->url($path))->successful();
        } catch (\Throwable $e) {
            throw UnableToCheckFileExistence::forLocation($path, $e);
        }
    }

    /**
     * Object stores have no directories, only key prefixes. Flysystem asks
     * this before creating one, and answering true keeps it from trying.
     */
    public function directoryExists(string $path): bool
    {
        return true;
    }

    public function read(string $path): string
    {
        $response = $this->request('GET', $this->url($path));

        if (! $response->successful()) {
            throw UnableToReadFile::fromLocation($path, $this->reason($response->body(), $response->status()));
        }

        return $response->body();
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    // Writes --------------------------------------------------------------

    public function write(string $path, string $contents, Config $config): void
    {
        $this->put($path, $contents, strlen($contents), hash('sha256', $contents), $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        // The signature covers a digest of the body, so the whole payload has
        // to be hashed before the first byte is sent. Anything not seekable is
        // spooled to a temp stream first rather than being read twice.
        if (! (stream_get_meta_data($contents)['seekable'] ?? false)) {
            $spooled = fopen('php://temp', 'w+b');
            stream_copy_to_stream($contents, $spooled);
            $contents = $spooled;
        }

        rewind($contents);
        $hash = hash_init('sha256');
        hash_update_stream($hash, $contents);
        $digest = hash_final($hash);

        $size = fstat($contents)['size'] ?? null;
        rewind($contents);

        $this->put($path, $contents, $size, $digest, $config);
    }

    private function put(string $path, mixed $body, ?int $size, string $digest, Config $config): void
    {
        $headers = ['Content-Type' => $config->get('mimetype') ?: $this->guessMime($path)];

        if ($size !== null) {
            $headers['Content-Length'] = (string) $size;
        }

        $response = $this->request('PUT', $this->url($path), $headers, $digest, $body);

        if (! $response->successful()) {
            throw UnableToWriteFile::atLocation($path, $this->reason($response->body(), $response->status()));
        }
    }

    public function delete(string $path): void
    {
        $response = $this->request('DELETE', $this->url($path));

        // 404 means the object is already gone, which is the outcome asked for.
        if (! $response->successful() && $response->status() !== 404) {
            throw UnableToDeleteFile::atLocation($path, $this->reason($response->body(), $response->status()));
        }
    }

    public function deleteDirectory(string $path): void
    {
        foreach ($this->listContents($path, true) as $item) {
            if ($item instanceof FileAttributes) {
                $this->delete($item->path());
            }
        }
    }

    /** No such thing on an object store; the key prefix appears with the file. */
    public function createDirectory(string $path, Config $config): void {}

    /**
     * Visibility is the bucket's business here, not ours - see the class
     * comment. Accepted silently so Flysystem's write path does not blow up.
     */
    public function setVisibility(string $path, string $visibility): void {}

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, visibility: 'public');
    }

    // Metadata ------------------------------------------------------------

    public function mimeType(string $path): FileAttributes
    {
        return $this->head($path, 'mimeType');
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->head($path, 'lastModified');
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->head($path, 'fileSize');
    }

    private function head(string $path, string $attribute): FileAttributes
    {
        $response = $this->request('HEAD', $this->url($path));

        if (! $response->successful()) {
            throw UnableToRetrieveMetadata::create($path, $attribute, 'HTTP '.$response->status());
        }

        return new FileAttributes(
            path: $path,
            fileSize: (int) $response->header('Content-Length') ?: null,
            lastModified: strtotime((string) $response->header('Last-Modified')) ?: null,
            mimeType: $response->header('Content-Type') ?: null,
        );
    }

    /** @return iterable<FileAttributes|DirectoryAttributes> */
    public function listContents(string $path, bool $deep): iterable
    {
        $continuation = null;
        $prefix = trim($this->key($path), '/');

        do {
            $query = ['list-type' => '2', 'prefix' => $prefix === '' ? '' : $prefix.'/'];

            if (! $deep) {
                $query['delimiter'] = '/';
            }

            if ($continuation) {
                $query['continuation-token'] = $continuation;
            }

            $response = $this->request('GET', $this->bucketUrl().'?'.$this->query($query));

            if (! $response->successful()) {
                return;
            }

            $xml = @simplexml_load_string($response->body());

            if ($xml === false) {
                return;
            }

            foreach ($xml->CommonPrefixes ?? [] as $folder) {
                yield new DirectoryAttributes($this->stripPrefix(rtrim((string) $folder->Prefix, '/')));
            }

            foreach ($xml->Contents ?? [] as $object) {
                $key = (string) $object->Key;

                // The prefix placeholder some tools create for a "folder".
                if (str_ends_with($key, '/')) {
                    continue;
                }

                yield new FileAttributes(
                    path: $this->stripPrefix($key),
                    fileSize: (int) $object->Size,
                    lastModified: strtotime((string) $object->LastModified) ?: null,
                );
            }

            $continuation = ((string) ($xml->IsTruncated ?? 'false')) === 'true'
                ? (string) $xml->NextContinuationToken
                : null;
        } while ($continuation);
    }

    // Moves ---------------------------------------------------------------

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (\Throwable $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        // Server-side copy: the bytes never travel through this server.
        $response = $this->request('PUT', $this->url($destination), [
            'x-amz-copy-source' => '/'.$this->bucket.'/'.$this->key($source),
        ]);

        if (! $response->successful()) {
            throw UnableToCopyFile::fromLocationTo($source, $destination,
                new \RuntimeException($this->reason($response->body(), $response->status())));
        }
    }

    // Addressing ----------------------------------------------------------

    /** The full key of a path, including the configured prefix. */
    public function key(string $path): string
    {
        $path = ltrim($path, '/');

        return $this->prefix === '' ? $path : $this->prefix.'/'.$path;
    }

    private function stripPrefix(string $key): string
    {
        return $this->prefix !== '' && str_starts_with($key, $this->prefix.'/')
            ? substr($key, strlen($this->prefix) + 1)
            : $key;
    }

    public function bucketUrl(): string
    {
        return $this->pathStyle
            ? $this->endpoint.'/'.$this->bucket
            : $this->hostWithBucket();
    }

    /** Puts the bucket in front of the endpoint's hostname. */
    private function hostWithBucket(): string
    {
        $parts = parse_url($this->endpoint);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$this->bucket.'.'.$host.$port.($parts['path'] ?? '');
    }

    public function url(string $path): string
    {
        return $this->bucketUrl().'/'.$this->encodeKey($this->key($path));
    }

    private function encodeKey(string $key): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $key)));
    }

    private function query(array $pairs): string
    {
        return implode('&', array_map(
            fn ($k, $v) => rawurlencode($k).'='.rawurlencode((string) $v),
            array_keys($pairs),
            $pairs
        ));
    }

    // Transport -----------------------------------------------------------

    private function request(string $method, string $url, array $headers = [], ?string $payloadHash = null, mixed $body = null)
    {
        $signed = $this->signer->headers(
            $method,
            $url,
            $headers,
            $payloadHash ?? SignatureV4::emptyPayloadHash()
        );

        $request = Http::withHeaders($signed)
            ->timeout($this->timeout)
            ->connectTimeout(min($this->timeout, 10))
            ->withoutRedirecting();

        return $body === null
            ? $request->send($method, $url)
            : $request->send($method, $url, ['body' => $body]);
    }

    /** Pulls the human part out of S3's XML error body. */
    private function reason(string $body, int $status): string
    {
        $xml = @simplexml_load_string($body);

        if ($xml !== false && isset($xml->Message)) {
            return trim((string) $xml->Code.': '.(string) $xml->Message);
        }

        return 'The storage provider answered HTTP '.$status.'.';
    }

    private function guessMime(string $path): string
    {
        $detector = new \League\MimeTypeDetection\ExtensionMimeTypeDetector;

        return $detector->detectMimeTypeFromPath($path) ?: 'application/octet-stream';
    }
}
