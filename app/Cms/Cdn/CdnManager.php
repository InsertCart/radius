<?php

namespace App\Cms\Cdn;

use App\Cms\Cdn\Adapters\FtpAdapter;
use App\Cms\Cdn\Adapters\S3CompatibleAdapter;
use App\Models\CdnConnection;
use App\Models\Media;
use Illuminate\Filesystem\FilesystemAdapter as LaravelAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\FilesystemAdapter as FlysystemAdapter;

/**
 * Decides where media files live and which address they are served from.
 *
 * Two questions that look like one. A pull CDN only changes the address:
 * the files never move, and switching it off is instant. Object storage
 * changes both: the bytes are uploaded and, unless the owner keeps a mirror,
 * deleted from this server afterwards.
 *
 * Every public page asks this class for a URL once per image, so the part that
 * answers that - the provider kind, the delivery address, the prefix - is
 * cached and memoised, and holds nothing secret. Credentials are only
 * decrypted when a file is actually being moved.
 *
 * Note that none of this consults the 'cdn' module toggle. That switch governs
 * the admin screens, as every module toggle does; it is not a kill switch for
 * storage, because flipping it on a site whose files live in a bucket would
 * break every image at once. Files stop being served from a provider when its
 * connection is switched off, which is a decision the admin screen refuses to
 * let an owner make until the files are back.
 */
class CdnManager
{
    private const CACHE_KEY = 'cms.cdn.delivery';
    private const SYNCED_KEY = 'cms.cdn.synced';
    private const CACHE_TTL = 86400;

    /**
     * The cheap facts needed to build a URL. Null means "not loaded yet";
     * an array with 'kind' => null means "loaded, nothing is switched on".
     */
    private ?array $delivery = null;

    private ?CdnConnection $active = null;

    private bool $activeLoaded = false;

    private ?LaravelAdapter $disk = null;

    private ?bool $tableExists = null;

    // What is switched on -------------------------------------------------

    /** Every provider in the catalogue, with its saved row. */
    public function providers(): array
    {
        $rows = $this->hasTable() ? CdnConnection::all()->keyBy('provider') : collect();
        $providers = [];

        foreach (config('cdn.providers', []) as $slug => $definition) {
            $providers[$slug] = $rows->get($slug) ?? new CdnConnection(['provider' => $slug]);
        }

        return $providers;
    }

    public function definition(string $provider): array
    {
        return config("cdn.providers.{$provider}", []);
    }

    /**
     * Creates a row for any provider that has none yet. Called by the admin
     * screen and by `php artisan cms:sync`.
     *
     * @return int Providers that gained a row.
     */
    public function sync(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        $existing = CdnConnection::pluck('provider')->flip();
        $created = 0;

        foreach (array_keys(config('cdn.providers', [])) as $slug) {
            if (! $existing->has($slug)) {
                CdnConnection::create(['provider' => $slug]);
                $created++;
            }
        }

        return $created;
    }

    /** The one enabled connection, if there is one. */
    public function active(): ?CdnConnection
    {
        if ($this->activeLoaded) {
            return $this->active;
        }

        $this->activeLoaded = true;

        if (! $this->hasTable()) {
            return $this->active = null;
        }

        return $this->active = CdnConnection::where('is_enabled', true)->first();
    }

    /** Whether media is being served from somewhere other than this server. */
    public function enabled(): bool
    {
        return $this->delivery()['kind'] !== null;
    }

    /** Whether files are actually uploaded away from this server. */
    public function offloads(): bool
    {
        $kind = $this->delivery()['kind'];

        return $kind !== null && $kind !== 'proxy';
    }

    /** Whether a mirror of every offloaded file stays on this server. */
    public function keepsLocalCopy(): bool
    {
        return (bool) $this->delivery()['keep_local'];
    }

    public function providerName(): ?string
    {
        return $this->delivery()['name'];
    }

    /** The disk uploads are written to before anything is offloaded. */
    public function originDisk(): string
    {
        return config('cdn.origin_disk') ?: config('cms.media.disk', 'public');
    }

    // Addressing ----------------------------------------------------------

    /**
     * The address for one media record, optionally a generated size.
     *
     * A file that has not reached the provider yet is served from here, which
     * is what makes enabling storage safe on a site with an existing library:
     * nothing breaks while the sync catches up.
     */
    public function urlForMedia(Media $media, ?string $conversion = null): string
    {
        $path = $conversion
            ? ($media->conversions[$conversion] ?? $media->path)
            : $media->path;

        $delivery = $this->delivery();

        if ($delivery['kind'] === null) {
            return $this->localUrl($media->disk, $path);
        }

        if ($delivery['kind'] === 'proxy') {
            return $this->rewriteHost($this->localUrl($media->disk, $path), $delivery['base']);
        }

        if ($media->on_cdn) {
            return $this->remoteUrl($path, $delivery);
        }

        return $this->localUrl($media->disk, $path);
    }

    /**
     * The address for a bare media path - a logo or a share image held in the
     * settings table, which has no media record to consult.
     *
     * With nothing to ask, the answer has to be a rule: the provider is used
     * only once every file has reached it. Until then these paths are served
     * from this server, where they certainly still are.
     */
    public function urlForPath(string $path): string
    {
        $delivery = $this->delivery();
        $local = $this->localUrl($this->originDisk(), $path);

        return match (true) {
            $delivery['kind'] === null => $local,
            $delivery['kind'] === 'proxy' => $this->rewriteHost($local, $delivery['base']),
            $this->everythingSynced() => $this->remoteUrl($path, $delivery),
            default => $local,
        };
    }

    private function localUrl(?string $disk, string $path): string
    {
        return Storage::disk($disk ?: $this->originDisk())->url($path);
    }

    private function remoteUrl(string $path, array $delivery): string
    {
        $key = $delivery['prefix'] === '' ? $path : $delivery['prefix'].'/'.$path;

        return $delivery['base'].'/'.$this->encodePath(ltrim($key, '/'));
    }

    /**
     * Swaps the host of a local address for the CDN's, keeping the path. This
     * is exactly what a pull zone expects: it takes the path it was asked for,
     * fetches it from the origin, and remembers the answer.
     */
    private function rewriteHost(string $url, string $base): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if ($path === false || $path === null) {
            return $url;
        }

        $query = parse_url($url, PHP_URL_QUERY);

        return $base.'/'.ltrim($path, '/').($query ? '?'.$query : '');
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    /**
     * The URL-shaped facts about the active connection, memoised for the
     * request and cached between them. Nothing here is a secret.
     *
     * @return array{kind: ?string, base: string, prefix: string, keep_local: bool, name: ?string}
     */
    public function delivery(): array
    {
        if ($this->delivery !== null) {
            return $this->delivery;
        }

        $empty = ['kind' => null, 'base' => '', 'prefix' => '', 'keep_local' => true, 'name' => null];

        if (! $this->hasTable()) {
            return $this->delivery = $empty;
        }

        return $this->delivery = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () use ($empty) {
            $connection = CdnConnection::where('is_enabled', true)->first();

            // An enabled connection with no address to serve from would turn
            // every image on the site into a broken one. Treat it as off.
            if (! $connection || blank($base = $connection->deliveryUrl())) {
                return $empty;
            }

            return [
                'kind' => $connection->kind(),
                'base' => rtrim($base, '/'),
                'prefix' => $connection->prefix(),
                'keep_local' => (bool) $connection->keep_local,
                'name' => $connection->name(),
            ];
        });
    }

    // Moving files --------------------------------------------------------

    /** The remote filesystem, built from the active connection. */
    public function disk(): ?LaravelAdapter
    {
        if ($this->disk) {
            return $this->disk;
        }

        $connection = $this->active();

        if (! $connection || ! $connection->offloads() || ! $connection->isConfigured()) {
            return null;
        }

        return $this->disk = $this->diskFor($connection);
    }

    /**
     * Builds a filesystem for any connection, configured or not, so the admin
     * screen can test credentials before switching them on.
     *
     * @throws \RuntimeException when the provider cannot be built at all.
     */
    public function diskFor(CdnConnection $connection): LaravelAdapter
    {
        $adapter = $this->adapterFor($connection);

        return new LaravelAdapter(
            new Flysystem($adapter, ['visibility' => 'public']),
            $adapter,
            ['url' => $connection->deliveryUrl(), 'throw' => true],
        );
    }

    private function adapterFor(CdnConnection $connection): FlysystemAdapter
    {
        $timeout = (int) config('cdn.timeout', 30);

        return match ($connection->kind()) {
            's3' => new S3CompatibleAdapter(
                endpoint: (string) $connection->endpoint(),
                bucket: (string) $connection->credential('bucket'),
                key: (string) $connection->credential('key'),
                secret: (string) $connection->credential('secret'),
                region: $connection->region(),
                pathStyle: $connection->usesPathStyle(),
                prefix: $connection->prefix(),
                timeout: $timeout,
            ),
            'ftp' => new FtpAdapter(
                host: (string) $connection->credential('host'),
                username: (string) $connection->credential('username'),
                password: (string) $connection->credential('password'),
                port: (int) $connection->credential('port', 21),
                root: trim((string) $connection->credential('root', ''), '/')
                    .($connection->prefix() ? '/'.$connection->prefix() : ''),
                ssl: $connection->credential('ssl', 'yes') === 'yes',
                passive: $connection->credential('passive', 'yes') === 'yes',
                timeout: $timeout,
            ),
            default => throw new \RuntimeException("The [{$connection->provider}] provider does not store files."),
        };
    }

    /**
     * Copies one path from this server to the provider.
     *
     * @throws \Throwable when the upload fails - the caller decides whether a
     *                    failed offload should abort anything.
     */
    public function push(string $path): void
    {
        $remote = $this->disk();
        $origin = Storage::disk($this->originDisk());

        if (! $remote) {
            throw new \RuntimeException('No media storage provider is switched on.');
        }

        $stream = $origin->readStream($path);

        if (! $stream) {
            throw new \RuntimeException("The file [{$path}] is missing from this server.");
        }

        try {
            $remote->writeStream($path, $stream, ['mimetype' => $origin->mimeType($path) ?: null]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /** Copies one path back from the provider onto this server. */
    public function pull(string $path): void
    {
        $remote = $this->disk();

        if (! $remote) {
            throw new \RuntimeException('No media storage provider is switched on.');
        }

        $stream = $remote->readStream($path);

        if (! $stream) {
            throw new \RuntimeException("The file [{$path}] is missing from the provider.");
        }

        try {
            Storage::disk($this->originDisk())->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /** Best effort: a file left behind on a bucket is litter, not a failure. */
    public function forget(string $path): void
    {
        try {
            $this->disk()?->delete($path);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // Progress ------------------------------------------------------------

    /** Files still to upload, and files with no copy left on this server. */
    public function progress(): array
    {
        if (! $this->hasTable()) {
            return ['total' => 0, 'pending' => 0, 'offloaded' => 0, 'remote_only' => 0];
        }

        return [
            'total' => Media::count(),
            'pending' => Media::where('on_cdn', false)->count(),
            'offloaded' => Media::where('on_cdn', true)->count(),
            'remote_only' => Media::where('on_cdn', true)->where('has_local_copy', false)->count(),
        ];
    }

    /** Whether every file in the library has reached the provider. */
    public function everythingSynced(): bool
    {
        if (! $this->hasTable()) {
            return false;
        }

        return Cache::remember(
            self::SYNCED_KEY,
            300,
            fn () => ! Media::where('on_cdn', false)->exists()
        );
    }

    /**
     * Writes a small file, reads it back and deletes it. The only honest way
     * to answer "are these credentials right?" before a buyer discovers the
     * answer through a library full of broken images.
     *
     * @return array{ok: bool, message: string, url: ?string}
     */
    public function test(CdnConnection $connection): array
    {
        if (! $connection->offloads()) {
            return blank($connection->deliveryUrl())
                ? ['ok' => false, 'message' => 'Enter the CDN address first.', 'url' => null]
                : ['ok' => true, 'message' => 'Nothing is uploaded with this provider, so there is nothing to test. Save it, then open an image from the media library and check the address it is served from.', 'url' => null];
        }

        if ($missing = $connection->missingFields()) {
            return ['ok' => false, 'message' => 'Still empty: '.implode(', ', $missing).'.', 'url' => null];
        }

        $path = 'radius-connection-test-'.bin2hex(random_bytes(6)).'.txt';
        $body = 'Written by Radius at '.now()->toIso8601String();

        try {
            $disk = $this->diskFor($connection);
            $disk->write($path, $body);

            if ($disk->get($path) !== $body) {
                return ['ok' => false, 'message' => 'The file was uploaded but came back different. That usually means the bucket name or prefix points somewhere unexpected.', 'url' => null];
            }

            $disk->delete($path);
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => $this->explain($e), 'url' => null];
        }

        $base = $connection->deliveryUrl();

        return [
            'ok' => true,
            'message' => 'Uploaded, read back and deleted a test file successfully.',
            'url' => $base ? rtrim($base, '/').'/' : null,
        ];
    }

    /** Turns a provider's error into something a site owner can act on. */
    private function explain(\Throwable $e): string
    {
        $message = $e->getPrevious()?->getMessage() ?: $e->getMessage();

        return match (true) {
            str_contains($message, 'SignatureDoesNotMatch') => 'The provider rejected the signature, which almost always means the secret key is wrong or has a stray space in it.',
            str_contains($message, 'InvalidAccessKeyId') => 'The provider does not recognise that access key.',
            str_contains($message, 'NoSuchBucket') => 'That bucket does not exist at this endpoint. Check the name, the region, and the address style.',
            str_contains($message, 'AccessDenied') => 'The credentials are valid but that account may not write to this bucket.',
            default => $message,
        };
    }

    // Cache ---------------------------------------------------------------

    public function flush(): void
    {
        $this->delivery = null;
        $this->active = null;
        $this->activeLoaded = false;
        $this->disk = null;

        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::SYNCED_KEY);

        // Every cached page embeds media addresses that have just changed.
        app(\App\Cms\Support\PageCache::class)->flush();
    }

    /** Only the cached progress count, busted after each batch of uploads. */
    public function flushProgress(): void
    {
        Cache::forget(self::SYNCED_KEY);
    }

    private function hasTable(): bool
    {
        // Only a positive answer is remembered: during installation this is
        // resolved before the migrations run, and caching that first "no"
        // would make the rest of the request agree with it.
        if ($this->tableExists === true) {
            return true;
        }

        try {
            return $this->tableExists = Schema::hasTable('cdn_connections');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
