<?php

namespace Tests\Feature;

use App\Cms\Updates\BackupService;
use App\Cms\Updates\ReleaseArchive;
use App\Cms\Updates\ReleaseDownloader;
use App\Cms\Updates\ReleaseManifest;
use App\Cms\Updates\UpdateApplier;
use App\Cms\Updates\UpdateException;
use App\Cms\Updates\UpdateHoldingPage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The page has to work with no framework behind it, so it is run here the way
 * public/index.php runs it: required by a bare PHP process.
 *
 * Everything happens in a throwaway project folder. These tests replace files
 * and would otherwise close the developer's own site.
 */
class UpdateHoldingPageTest extends TestCase
{
    private string $root;

    private UpdateHoldingPage $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/holding-'.getmypid().'-'.uniqid());
        File::ensureDirectoryExists($this->root.'/storage/app');
        File::ensureDirectoryExists($this->root.'/storage/framework/views');

        $this->app->setBasePath($this->root);
        $this->app->useStoragePath($this->root.'/storage');

        config([
            'cms.install_lock' => $this->root.'/storage/installed',
            'view.compiled' => $this->root.'/storage/framework/views',
        ]);

        $this->page = app(UpdateHoldingPage::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    /** What a visitor receives, or THROUGH when the request carries on to the site. */
    private function visit(?string $pass = null, string $accept = 'text/html'): string
    {
        $code = '$_SERVER["HTTP_ACCEPT"] = '.var_export($accept, true).';';

        if ($pass !== null) {
            $code .= '$_COOKIE['.var_export(UpdateHoldingPage::COOKIE, true).'] = '.var_export($pass, true).';';
        }

        $code .= 'require '.var_export($this->page->path(), true).'; echo "THROUGH";';

        $process = new Process([PHP_BINARY, '-r', $code]);
        $process->mustRun();

        return $process->getOutput();
    }

    /** An updater whose download holds one file, without any network or ZIP. */
    private function applier(): UpdateApplier
    {
        $this->mock(ReleaseDownloader::class)
            ->shouldReceive('download')->andReturn($this->root.'/release.zip');

        $archive = $this->mock(ReleaseArchive::class);
        $archive->shouldReceive('verify');
        $archive->shouldReceive('isWritablePath')->andReturn(true);
        $archive->shouldReceive('extract')->andReturnUsing(function (string $zip, string $stage) {
            File::ensureDirectoryExists($stage.'/app');
            File::put($stage.'/app/New.php', '<?php');

            return ['files' => ['app/New.php']];
        });

        $this->mock(BackupService::class)
            ->shouldReceive('directory')->andReturn($this->root.'/backups');

        return app(UpdateApplier::class);
    }

    private function manifest(): ReleaseManifest
    {
        return ReleaseManifest::fromArray(['version' => '99.0.0', 'download' => 'https://example.test/r.zip']);
    }

    public function test_visitors_are_held_back_with_a_503_page(): void
    {
        $this->page->open();

        $this->assertTrue($this->page->active());

        $output = $this->visit();

        $this->assertStringContainsString('We are updating this site', $output);
        $this->assertStringNotContainsString('THROUGH', $output);
    }

    public function test_api_clients_are_answered_in_json(): void
    {
        $this->page->open();

        $body = json_decode($this->visit(accept: 'application/json'), true);

        $this->assertSame('updating', $body['error']);
    }

    public function test_the_person_running_the_update_gets_through(): void
    {
        $pass = $this->page->open();

        $this->assertSame('THROUGH', $this->visit($pass));
        $this->assertStringNotContainsString('THROUGH', $this->visit('wrong-pass'));
    }

    public function test_an_abandoned_page_removes_itself(): void
    {
        $this->page->open();

        File::put($this->page->path(), preg_replace(
            '/time\(\) >= \d+/',
            'time() >= '.(time() - 1),
            File::get($this->page->path()),
        ));

        $this->assertSame('THROUGH', $this->visit());
        $this->assertFileDoesNotExist($this->page->path());
    }

    public function test_a_site_taken_down_on_purpose_is_left_alone(): void
    {
        File::put($this->page->path(), '<?php // from php artisan down');

        $this->assertNull($this->page->open());

        $this->page->close();

        $this->assertSame('<?php // from php artisan down', File::get($this->page->path()));
    }

    public function test_the_page_stays_up_after_the_files_are_swapped(): void
    {
        $this->applier()->apply($this->manifest(), backupDatabase: false);

        $this->assertFileExists($this->root.'/app/New.php');
        $this->assertTrue($this->page->active());
    }

    public function test_an_update_that_changed_nothing_reopens_the_site(): void
    {
        // A directory where the file belongs makes the very first copy fail.
        File::ensureDirectoryExists($this->root.'/app/New.php');

        try {
            $this->applier()->apply($this->manifest(), backupDatabase: false);
            $this->fail('The update should have stopped.');
        } catch (UpdateException) {
            $this->assertFalse($this->page->active());
        }
    }

    public function test_finishing_the_update_reopens_the_site(): void
    {
        $applier = $this->applier();
        $applier->apply($this->manifest(), backupDatabase: false);

        Artisan::shouldReceive('call')->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('');

        $applier->finalize();

        $this->assertFalse($this->page->active());
    }

    public function test_rolling_back_reopens_the_site(): void
    {
        $applier = $this->applier();
        $applier->apply($this->manifest(), backupDatabase: false);

        Artisan::shouldReceive('call')->andReturn(0);

        $applier->rollback();

        $this->assertFalse($this->page->active());
        $this->assertFileDoesNotExist($this->root.'/app/New.php');
    }
}
