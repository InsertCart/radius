<?php

namespace App\Cms\Cdn\Adapters;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;

/**
 * A Flysystem adapter over PHP's FTP extension.
 *
 * The escape hatch for hosts that sell nothing but disk space and an FTP
 * login. FTP only moves bytes - whatever web server publishes that directory
 * is the buyer's own arrangement, which is why an FTP connection insists on
 * being told the public address separately.
 *
 * The connection is opened lazily and kept for the life of the request, since
 * an offloaded upload writes an original plus three thumbnails and logging in
 * four times would be slow and rude.
 */
class FtpAdapter implements FilesystemAdapter
{
    /** @var resource|\FTP\Connection|null */
    private $connection = null;

    /** Remote directories already known to exist, so mkdir runs once each. */
    private array $ensured = [];

    public function __construct(
        private string $host,
        private string $username,
        private string $password,
        private int $port = 21,
        private string $root = '',
        private bool $ssl = true,
        private bool $passive = true,
        private int $timeout = 30,
    ) {
        $this->root = rtrim($root, '/');
    }

    public function __destruct()
    {
        if ($this->connection) {
            @ftp_close($this->connection);
        }
    }

    /** Whether this server can talk FTP at all. */
    public static function supported(): bool
    {
        return function_exists('ftp_connect');
    }

    /**
     * @return resource|\FTP\Connection
     *
     * @throws \RuntimeException when the login fails.
     */
    private function connection()
    {
        if ($this->connection) {
            return $this->connection;
        }

        if (! self::supported()) {
            throw new \RuntimeException("PHP's FTP extension is not enabled on this server, so FTP storage cannot be used. Ask your host to enable ext-ftp, or choose an S3-compatible provider instead.");
        }

        if ($this->ssl && ! function_exists('ftp_ssl_connect')) {
            throw new \RuntimeException("This server's FTP extension was built without TLS, so FTPS is unavailable. Ask your host to rebuild it with OpenSSL rather than sending your password in the clear.");
        }

        $connection = $this->ssl
            ? @ftp_ssl_connect($this->host, $this->port, $this->timeout)
            : @ftp_connect($this->host, $this->port, $this->timeout);

        if (! $connection) {
            throw new \RuntimeException("Could not reach {$this->host} on port {$this->port}.");
        }

        if (! @ftp_login($connection, $this->username, $this->password)) {
            @ftp_close($connection);

            throw new \RuntimeException('The FTP server refused that username and password.');
        }

        // Nearly every server behind a firewall needs passive mode, and it has
        // to be set after login or some servers ignore it.
        @ftp_pasv($connection, $this->passive);

        return $this->connection = $connection;
    }

    // Reads ---------------------------------------------------------------

    public function fileExists(string $path): bool
    {
        try {
            return @ftp_size($this->connection(), $this->location($path)) >= 0;
        } catch (\Throwable $e) {
            throw UnableToCheckFileExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $connection = $this->connection();
            $current = @ftp_pwd($connection);

            if (! @ftp_chdir($connection, $this->location($path))) {
                return false;
            }

            @ftp_chdir($connection, $current);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function read(string $path): string
    {
        $stream = $this->readStream($path);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return (string) $contents;
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');

        try {
            $ok = @ftp_fget($this->connection(), $stream, $this->location($path), FTP_BINARY);
        } catch (\Throwable $e) {
            fclose($stream);

            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }

        if (! $ok) {
            fclose($stream);

            throw UnableToReadFile::fromLocation($path, 'The FTP server would not send the file.');
        }

        rewind($stream);

        return $stream;
    }

    // Writes --------------------------------------------------------------

    public function write(string $path, string $contents, Config $config): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $contents);
        rewind($stream);

        try {
            $this->writeStream($path, $stream, $config);
        } finally {
            fclose($stream);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            $connection = $this->connection();
            $this->ensureDirectory(dirname($path));

            if (! @ftp_fput($connection, $this->location($path), $contents, FTP_BINARY)) {
                throw new \RuntimeException('The FTP server rejected the upload. Check that the account may write to this directory.');
            }
        } catch (\Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function delete(string $path): void
    {
        try {
            $connection = $this->connection();

            // Already gone is the outcome asked for, not a failure.
            if (@ftp_size($connection, $this->location($path)) < 0) {
                return;
            }

            if (! @ftp_delete($connection, $this->location($path))) {
                throw new \RuntimeException('The FTP server refused to delete the file.');
            }
        } catch (\Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        foreach ($this->listContents($path, false) as $item) {
            $item instanceof DirectoryAttributes
                ? $this->deleteDirectory($item->path())
                : $this->delete($item->path());
        }

        @ftp_rmdir($this->connection(), $this->location($path));
        unset($this->ensured[trim($path, '/')]);
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->ensureDirectory($path);
        } catch (\Throwable $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage());
        }
    }

    /**
     * Creates every missing segment of a path. FTP has no mkdir -p, and a
     * server that answers "already exists" is telling us what we wanted.
     */
    private function ensureDirectory(string $directory): void
    {
        $directory = trim(str_replace('\\', '/', $directory), '/.');

        if ($directory === '' || isset($this->ensured[$directory])) {
            return;
        }

        $connection = $this->connection();
        $walked = '';

        foreach (explode('/', $directory) as $segment) {
            $walked = ltrim($walked.'/'.$segment, '/');

            if (isset($this->ensured[$walked])) {
                continue;
            }

            $remote = $this->location($walked);

            if (! @ftp_chdir($connection, $remote)) {
                @ftp_mkdir($connection, $remote);
            }

            $this->ensured[$walked] = true;
        }

        // chdir above leaves the session inside the last directory; every
        // other call uses absolute paths, but be tidy about it anyway.
        if ($this->root !== '') {
            @ftp_chdir($connection, $this->root);
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        @ftp_chmod($this->connection(), $visibility === 'public' ? 0644 : 0600, $this->location($path));
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, visibility: 'public');
    }

    // Metadata ------------------------------------------------------------

    public function mimeType(string $path): FileAttributes
    {
        $mime = (new \League\MimeTypeDetection\ExtensionMimeTypeDetector)->detectMimeTypeFromPath($path);

        if (! $mime) {
            throw UnableToRetrieveMetadata::mimeType($path, 'The file extension is not one we recognise.');
        }

        return new FileAttributes($path, mimeType: $mime);
    }

    public function lastModified(string $path): FileAttributes
    {
        $time = @ftp_mdtm($this->connection(), $this->location($path));

        if ($time < 0) {
            throw UnableToRetrieveMetadata::lastModified($path, 'The FTP server does not report modification times.');
        }

        return new FileAttributes($path, lastModified: $time);
    }

    public function fileSize(string $path): FileAttributes
    {
        $size = @ftp_size($this->connection(), $this->location($path));

        if ($size < 0) {
            throw UnableToRetrieveMetadata::fileSize($path, 'The FTP server did not report a size.');
        }

        return new FileAttributes($path, fileSize: $size);
    }

    /** @return iterable<FileAttributes|DirectoryAttributes> */
    public function listContents(string $path, bool $deep): iterable
    {
        $connection = $this->connection();
        $listing = @ftp_rawlist($connection, '-aln '.$this->location($path));

        if ($listing === false) {
            return;
        }

        foreach ($listing as $line) {
            $entry = $this->parseListing($line);

            if ($entry === null) {
                continue;
            }

            [$name, $isDirectory, $size, $time] = $entry;
            $child = trim($path.'/'.$name, '/');

            if ($isDirectory) {
                yield new DirectoryAttributes($child, lastModified: $time);

                if ($deep) {
                    yield from $this->listContents($child, true);
                }

                continue;
            }

            yield new FileAttributes($child, fileSize: $size, lastModified: $time);
        }
    }

    /**
     * Unix-style rawlist output. Windows FTP servers answer in a different
     * shape; listing is only used by maintenance commands, so an
     * unrecognised line is skipped rather than guessed at.
     *
     * @return array{0: string, 1: bool, 2: ?int, 3: ?int}|null
     */
    private function parseListing(string $line): ?array
    {
        $parts = preg_split('/\s+/', trim($line), 9);

        if ($parts === false || count($parts) < 9) {
            return null;
        }

        $name = $parts[8];

        if ($name === '.' || $name === '..') {
            return null;
        }

        return [$name, str_starts_with($parts[0], 'd'), (int) $parts[4], null];
    }

    // Moves ---------------------------------------------------------------

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $connection = $this->connection();
            $this->ensureDirectory(dirname($destination));

            if (! @ftp_rename($connection, $this->location($source), $this->location($destination))) {
                throw new \RuntimeException('The FTP server refused to rename the file.');
            }
        } catch (\Throwable $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        // FTP has no server-side copy: the bytes come here and go back.
        try {
            $stream = $this->readStream($source);
            $this->writeStream($destination, $stream, $config);
            fclose($stream);
        } catch (\Throwable $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    /** The absolute remote path of a media path. */
    private function location(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return $this->root === '' ? $path : $this->root.'/'.$path;
    }
}
