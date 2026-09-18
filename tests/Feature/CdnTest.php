<?php

namespace Tests\Feature;

use App\Cms\Cdn\Adapters\SignatureV4;
use App\Cms\Cdn\CdnManager;
use App\Models\CdnConnection;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Media storage providers.
 *
 * The property that matters most is that nothing breaks halfway: a site whose
 * library is half uploaded must serve every image, from whichever of the two
 * places currently has it. Most of what follows is that one claim, tested from
 * several directions.
 */
class CdnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin');
    }

    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'owner@x.test'], [
            'name' => 'Owner', 'email' => 'owner@x.test', 'password' => Hash::make('password-123'),
            'role' => User::ROLE_ADMIN, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function media(string $name = 'photo', array $attributes = []): Media
    {
        return Media::create(array_merge([
            'name' => $name, 'file_name' => "{$name}.jpg", 'mime_type' => 'image/jpeg',
            'extension' => 'jpg', 'size' => 100, 'disk' => 'public', 'path' => "2026/09/{$name}.jpg",
        ], $attributes));
    }

    /** A connection with credentials, saved the way the admin screen saves them. */
    private function connect(string $provider, array $credentials, array $attributes = []): CdnConnection
    {
        $connection = CdnConnection::firstOrCreate(['provider' => $provider]);
        $connection->mergeCredentials($credentials);
        $connection->fill($attributes);
        $connection->save();

        app(CdnManager::class)->flush();

        return $connection;
    }

    private function enable(CdnConnection $connection): void
    {
        CdnConnection::where('is_enabled', true)->update(['is_enabled' => false]);
        $connection->update(['is_enabled' => true]);

        app(CdnManager::class)->flush();
    }

    // Serving addresses ---------------------------------------------------

    public function test_media_is_served_from_this_server_when_nothing_is_configured(): void
    {
        $this->assertStringContainsString('/storage/2026/09/photo.jpg', $this->media()->url);
    }

    public function test_a_pull_cdn_only_swaps_the_host_and_keeps_the_path(): void
    {
        $this->enable($this->connect('proxy', ['delivery_url' => 'https://cdn.example.com']));

        // Nothing was uploaded, so the path has to stay exactly as it was:
        // that is the address the CDN will fetch from this site.
        $this->assertSame('https://cdn.example.com/storage/2026/09/photo.jpg', $this->media()->url);
    }

    public function test_a_trailing_slash_on_the_cdn_address_does_not_double_up(): void
    {
        $this->enable($this->connect('proxy', ['delivery_url' => 'https://cdn.example.com/']));

        $this->assertSame('https://cdn.example.com/storage/2026/09/photo.jpg', $this->media()->url);
    }

    public function test_offloaded_files_are_served_from_the_bucket_and_pending_ones_from_here(): void
    {
        $this->enable($this->connect('s3', [
            'key' => 'AKIA', 'secret' => 'shh', 'region' => 'ap-south-1', 'bucket' => 'shop-media',
        ]));

        $moved = $this->media('moved', ['on_cdn' => true]);
        $waiting = $this->media('waiting');

        $this->assertSame('https://shop-media.s3.ap-south-1.amazonaws.com/2026/09/moved.jpg', $moved->url);
        $this->assertStringContainsString('/storage/2026/09/waiting.jpg', $waiting->url);
    }

    public function test_a_custom_domain_wins_over_the_bucket_address(): void
    {
        $this->enable($this->connect('s3', [
            'key' => 'AKIA', 'secret' => 'shh', 'region' => 'us-east-1', 'bucket' => 'b',
            'delivery_url' => 'https://images.example.com',
        ]));

        $this->assertSame(
            'https://images.example.com/2026/09/photo.jpg',
            $this->media('photo', ['on_cdn' => true])->url
        );
    }

    public function test_a_folder_prefix_appears_in_the_served_address(): void
    {
        $this->enable($this->connect('r2', [
            'account_id' => 'abc123', 'key' => 'k', 'secret' => 's', 'bucket' => 'b',
            'delivery_url' => 'https://cdn.example.com',
        ], ['path_prefix' => 'site-one/media']));

        $this->assertSame(
            'https://cdn.example.com/site-one/media/2026/09/photo.jpg',
            $this->media('photo', ['on_cdn' => true])->url
        );
    }

    public function test_conversions_follow_the_original_to_the_provider(): void
    {
        $this->enable($this->connect('s3', [
            'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'b',
        ]));

        $media = $this->media('photo', [
            'on_cdn' => true,
            'conversions' => ['thumb' => '2026/09/photo-thumb.jpg'],
        ]);

        $this->assertSame('https://b.s3.us-east-1.amazonaws.com/2026/09/photo-thumb.jpg', $media->conversionUrl('thumb'));

        // A size that was never generated falls back to the original, which is
        // on the provider too.
        $this->assertSame('https://b.s3.us-east-1.amazonaws.com/2026/09/photo.jpg', $media->conversionUrl('large'));
    }

    public function test_an_enabled_connection_with_no_address_is_ignored_rather_than_breaking_every_image(): void
    {
        // Half-configured: credentials but nowhere for a visitor to fetch from.
        $this->enable($this->connect('r2', ['account_id' => 'a', 'key' => 'k', 'secret' => 's', 'bucket' => 'b']));

        $this->assertFalse(app(CdnManager::class)->enabled());
        $this->assertStringContainsString('/storage/2026/09/photo.jpg', $this->media('photo', ['on_cdn' => true])->url);
    }

    public function test_settings_images_stay_local_until_the_whole_library_has_moved(): void
    {
        $this->enable($this->connect('s3', [
            'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'b',
        ]));

        $this->media('waiting');

        // media_url() has no record to consult, so it may only trust the
        // provider once nothing is pending.
        $this->assertStringContainsString('/storage/logo.png', media_url('logo.png'));

        Media::query()->update(['on_cdn' => true]);
        app(CdnManager::class)->flush();

        $this->assertSame('https://b.s3.us-east-1.amazonaws.com/logo.png', media_url('logo.png'));
    }

    public function test_an_absolute_address_pasted_into_a_settings_field_is_left_alone(): void
    {
        $this->enable($this->connect('proxy', ['delivery_url' => 'https://cdn.example.com']));

        $this->assertSame('https://other.example.org/logo.png', media_url('https://other.example.org/logo.png'));
    }

    // Uploading -----------------------------------------------------------

    public function test_an_upload_is_mirrored_when_a_local_copy_is_kept(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('', 200)]);

        $this->enable($this->connect('s3', [
            'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'b',
        ], ['keep_local' => true]));

        $media = app(\App\Cms\Media\MediaService::class)
            ->store(UploadedFile::fake()->image('photo.jpg', 40, 40));

        $this->assertTrue($media->on_cdn);
        $this->assertTrue($media->has_local_copy);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_the_local_copy_is_dropped_when_the_owner_asked_for_that(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('', 200)]);

        $this->enable($this->connect('s3', [
            'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'b',
        ], ['keep_local' => false]));

        $media = app(\App\Cms\Media\MediaService::class)
            ->store(UploadedFile::fake()->image('photo.jpg', 40, 40));

        $this->assertTrue($media->on_cdn);
        $this->assertFalse($media->has_local_copy);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_a_provider_that_is_down_does_not_fail_the_upload(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('<Error><Code>InternalError</Code></Error>', 500)]);

        $this->enable($this->connect('s3', [
            'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'b',
        ], ['keep_local' => false]));

        $media = app(\App\Cms\Media\MediaService::class)
            ->store(UploadedFile::fake()->image('photo.jpg', 40, 40));

        // The file is stored and serveable; the sync screen will catch it up.
        $this->assertFalse($media->on_cdn);
        $this->assertTrue($media->has_local_copy);
        Storage::disk('public')->assertExists($media->path);
        $this->assertStringContainsString('/storage/', $media->url);
    }

    public function test_deleting_a_file_removes_it_from_the_provider_as_well(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('', 204)]);

        $this->enable($this->connect('s3', [
            'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'b',
        ]));

        $media = $this->media('photo', [
            'on_cdn' => true,
            'conversions' => ['thumb' => '2026/09/photo-thumb.jpg'],
        ]);

        $media->deleteFiles();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '2026/09/photo.jpg'));

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '2026/09/photo-thumb.jpg'));
    }

    // The admin screen ----------------------------------------------------

    public function test_the_storage_screen_is_admin_only(): void
    {
        $editor = User::create([
            'name' => 'Editor', 'email' => 'editor@x.test', 'password' => Hash::make('password-123'),
            'role' => User::ROLE_EDITOR, 'status' => 'active', 'email_verified_at' => now(),
        ]);

        $this->actingAs($editor)->get('/admin/cdn')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/cdn')->assertOk();
    }

    public function test_saving_credentials_never_echoes_a_secret_back(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/cdn/s3', [
                'credentials' => ['key' => 'AKIAEXAMPLE', 'secret' => 'super-secret-value', 'region' => 'us-east-1', 'bucket' => 'b'],
                'keep_local' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin())
            ->get('/admin/cdn/s3')
            ->assertOk()
            ->assertDontSee('super-secret-value')
            // Values that are not secrets are shown, so they can be corrected.
            ->assertSee('AKIAEXAMPLE');

        $this->assertNotEmpty(CdnConnection::where('provider', 's3')->value('credentials'));
    }

    public function test_a_blank_secret_keeps_the_saved_one(): void
    {
        $this->actingAs($this->admin())->put('/admin/cdn/s3', [
            'credentials' => ['key' => 'k', 'secret' => 'original', 'region' => 'us-east-1', 'bucket' => 'b'],
        ]);

        $this->actingAs($this->admin())->put('/admin/cdn/s3', [
            'credentials' => ['key' => 'k2', 'secret' => '', 'region' => 'us-east-1', 'bucket' => 'b'],
        ]);

        $connection = CdnConnection::where('provider', 's3')->first();

        $this->assertSame('original', $connection->credential('secret'));
        $this->assertSame('k2', $connection->credential('key'));
    }

    public function test_a_half_configured_provider_cannot_be_switched_on(): void
    {
        $this->connect('s3', ['key' => 'k']);

        $this->actingAs($this->admin())
            ->post('/admin/cdn/s3/enable')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse(CdnConnection::where('provider', 's3')->value('is_enabled'));
    }

    public function test_switching_a_provider_on_switches_the_other_one_off(): void
    {
        $proxy = $this->connect('proxy', ['delivery_url' => 'https://cdn.example.com']);
        $this->enable($proxy);

        $this->connect('s3', ['key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'b']);

        $this->actingAs($this->admin())->post('/admin/cdn/s3/enable')->assertSessionHas('status');

        $this->assertFalse(CdnConnection::where('provider', 'proxy')->value('is_enabled'));
        $this->assertTrue(CdnConnection::where('provider', 's3')->value('is_enabled'));
    }

    public function test_a_provider_holding_the_only_copy_of_a_file_cannot_be_switched_off(): void
    {
        $connection = $this->connect('s3', ['key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'b']);
        $this->enable($connection);

        $this->media('stranded', ['on_cdn' => true, 'has_local_copy' => false]);

        $this->actingAs($this->admin())
            ->post('/admin/cdn/s3/disable')
            ->assertSessionHas('error');

        $this->assertTrue(CdnConnection::where('provider', 's3')->value('is_enabled'));
    }

    public function test_a_mirrored_provider_can_be_switched_off_freely(): void
    {
        $connection = $this->connect('s3', ['key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'b']);
        $this->enable($connection);

        $this->media('mirrored', ['on_cdn' => true, 'has_local_copy' => true]);

        $this->actingAs($this->admin())
            ->post('/admin/cdn/s3/disable')
            ->assertSessionHas('status');

        $this->assertFalse(CdnConnection::where('provider', 's3')->value('is_enabled'));
        $this->assertStringContainsString('/storage/', Media::first()->url);
    }

    public function test_the_sync_button_uploads_a_batch_and_reports_what_is_left(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('', 200)]);

        config(['cdn.batch_size' => 2]);

        $this->enable($this->connect('s3', [
            'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'b',
        ], ['keep_local' => true]));

        foreach (['a', 'b', 'c'] as $name) {
            Storage::disk('public')->put("2026/09/{$name}.jpg", 'x');
            $this->media($name);
        }

        $this->actingAs($this->admin())->post('/admin/cdn/push')->assertSessionHas('status');

        $this->assertSame(2, Media::where('on_cdn', true)->count());
        $this->assertSame(1, Media::where('on_cdn', false)->count());
    }

    public function test_the_screens_render_with_a_storage_provider_mid_sync(): void
    {
        $this->enable($this->connect('s3', [
            'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'b',
        ], ['keep_local' => false]));

        $this->media('moved', ['on_cdn' => true, 'has_local_copy' => false]);
        $this->media('waiting');

        $this->actingAs($this->admin())
            ->get('/admin/cdn')
            ->assertOk()
            ->assertSee('Upload 1 file(s) to Amazon S3')
            ->assertSee('Bring 1 file(s) back here');

        $this->actingAs($this->admin())
            ->get('/admin/cdn/s3')
            ->assertOk()
            ->assertSee('Files waiting');
    }

    public function test_the_media_library_says_where_files_are_served_from(): void
    {
        $this->enable($this->connect('proxy', ['delivery_url' => 'https://cdn.example.com']));

        $this->actingAs($this->admin())
            ->get('/admin/media')
            ->assertOk()
            ->assertSee('CDN in front of this server');
    }

    // Request signing -----------------------------------------------------

    public function test_the_signature_matches_the_worked_example_from_the_aws_documentation(): void
    {
        // "GET Object" from the S3 SigV4 header-signing documentation. AWS
        // publishes the exact signature this request must produce, so if the
        // canonicalisation ever drifts, this is what notices.
        $signer = new SignatureV4('AKIAIOSFODNN7EXAMPLE', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY', 'us-east-1');

        $headers = $signer->headers(
            'GET',
            'https://examplebucket.s3.amazonaws.com/test.txt',
            ['Range' => 'bytes=0-9'],
            SignatureV4::emptyPayloadHash(),
            gmmktime(0, 0, 0, 5, 24, 2013),
        );

        $this->assertSame(
            'AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request,'
            .' SignedHeaders=host;range;x-amz-content-sha256;x-amz-date,'
            .' Signature=f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41',
            $headers['Authorization']
        );

        // Host is covered by the signature but not sent twice.
        $this->assertArrayNotHasKey('Host', $headers);
    }

    public function test_a_space_in_a_file_name_survives_signing_and_addressing(): void
    {
        $this->enable($this->connect('s3', [
            'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'b',
        ]));

        $media = $this->media('x', ['on_cdn' => true, 'path' => '2026/09/my file.jpg']);

        $this->assertSame('https://b.s3.us-east-1.amazonaws.com/2026/09/my%20file.jpg', $media->url);
    }

    public function test_path_style_providers_put_the_bucket_in_the_path(): void
    {
        $adapter = new \App\Cms\Cdn\Adapters\S3CompatibleAdapter(
            endpoint: 'https://abc.r2.cloudflarestorage.com',
            bucket: 'media',
            key: 'k',
            secret: 's',
            region: 'auto',
            pathStyle: true,
            prefix: 'site',
        );

        $this->assertSame('https://abc.r2.cloudflarestorage.com/media/site/a/b.jpg', $adapter->url('a/b.jpg'));
    }

    public function test_virtual_host_providers_put_the_bucket_in_the_hostname(): void
    {
        $adapter = new \App\Cms\Cdn\Adapters\S3CompatibleAdapter(
            endpoint: 'https://nyc3.digitaloceanspaces.com',
            bucket: 'media',
            key: 'k',
            secret: 's',
            region: 'nyc3',
            pathStyle: false,
        );

        $this->assertSame('https://media.nyc3.digitaloceanspaces.com/a/b.jpg', $adapter->url('a/b.jpg'));
    }
}
