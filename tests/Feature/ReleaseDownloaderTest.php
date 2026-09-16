<?php

namespace Tests\Feature;

use App\Cms\Updates\ReleaseDownloader;
use App\Cms\Updates\ReleaseManifest;
use App\Cms\Updates\UpdateException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pins the behaviour of the core-update download: which archives it refuses,
 * and the exact words it uses when it does. The checks themselves are shared
 * with the theme marketplace, and these tests are what prove that sharing them
 * changed nothing for the updater - site owners see these messages verbatim.
 */
class ReleaseDownloaderTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = storage_path('framework/testing/release-downloader');
        File::deleteDirectory($this->workspace);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workspace);

        parent::tearDown();
    }

    private function manifest(array $overrides = []): ReleaseManifest
    {
        return ReleaseManifest::fromArray(array_merge([
            'version' => '9.9.9',
            'download' => 'https://releases.example.com/cms-9.9.9.zip',
            'sha256' => hash('sha256', 'release-bytes'),
        ], $overrides));
    }

    private function assertRefused(ReleaseManifest $manifest, string $message): void
    {
        try {
            app(ReleaseDownloader::class)->download($manifest, $this->workspace);
            $this->fail('The download was expected to be refused.');
        } catch (UpdateException $e) {
            $this->assertSame($message, $e->getMessage());
        }

        $this->assertSame([], is_dir($this->workspace) ? File::files($this->workspace) : [],
            'A refused download must not leave a file behind.');
    }

    public function test_a_verified_archive_is_written_and_its_path_returned(): void
    {
        Http::fake(['releases.example.com/*' => Http::response('release-bytes')]);

        $path = app(ReleaseDownloader::class)->download($this->manifest(), $this->workspace);

        $this->assertFileExists($path);
        $this->assertSame('release-bytes', file_get_contents($path));
        $this->assertMatchesRegularExpression('/release-9\.9\.9-[A-Za-z0-9]{8}\.zip$/', $path);
    }

    public function test_a_missing_download_address_is_refused(): void
    {
        $this->assertRefused($this->manifest(['download' => null]),
            'This release does not say where to download it from.');
    }

    public function test_a_malformed_download_address_is_refused(): void
    {
        $this->assertRefused($this->manifest(['download' => 'ftp://releases.example.com/x.zip']),
            'The download address for this release is not a valid web address.');
    }

    public function test_plain_http_is_refused(): void
    {
        $this->assertRefused($this->manifest(['download' => 'http://releases.example.com/x.zip']),
            'This release is offered over an insecure http:// address. Because the download becomes program '
            .'code on your server, only https:// is accepted.');
    }

    public function test_a_release_without_a_checksum_is_refused(): void
    {
        $this->assertRefused($this->manifest(['sha256' => null]),
            'This release does not publish a SHA-256 checksum, so there is no way to tell whether the '
            .'download arrived intact or was tampered with. The update was not applied.');
    }

    public function test_a_server_error_is_reported_with_its_status(): void
    {
        Http::fake(['releases.example.com/*' => Http::response('nope', 503)]);

        $this->assertRefused($this->manifest(), 'The download failed: the server answered with an error (503).');
    }

    public function test_an_empty_download_is_refused(): void
    {
        Http::fake(['releases.example.com/*' => Http::response('')]);

        $this->assertRefused($this->manifest(), 'The download produced an empty file.');
    }

    public function test_an_oversized_download_is_refused(): void
    {
        config(['updates.max_download_bytes' => 4]);
        Http::fake(['releases.example.com/*' => Http::response('release-bytes')]);

        $this->assertRefused($this->manifest(),
            'The downloaded release is larger than this site allows (0.0 MB). The update was not applied.');
    }

    public function test_a_checksum_mismatch_is_refused(): void
    {
        Http::fake(['releases.example.com/*' => Http::response('tampered-bytes')]);

        $this->assertRefused($this->manifest(),
            'The downloaded file does not match the checksum published for this release, so it was discarded. '
            .'The download may have been corrupted, or the file may have been replaced. Nothing on your site was changed.');
    }
}
