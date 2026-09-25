<?php

namespace Tests\Feature;

use App\Cms\Embeds\Embed;
use App\Cms\Embeds\EmbedManager;
use App\Cms\Support\HtmlSanitizer;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pasting a link on a line of its own turns it into the thing it points at.
 *
 * The cases that matter most are the ones that must NOT happen: a link written
 * into a sentence stays a link, an unrecognised address is left alone, and
 * turning the feature off leaves published posts readable rather than broken.
 */
class ContentEmbedsTest extends TestCase
{
    use RefreshDatabase;

    private EmbedManager $embeds;

    protected function setUp(): void
    {
        parent::setUp();

        // Boots the CMS, so settings and the active theme are available.
        $this->get('/');

        $this->embeds = app(EmbedManager::class);
    }

    private function article(string $content): Post
    {
        return Post::create([
            'title' => 'A post with an embed',
            'slug' => 'a-post-with-an-embed',
            'content' => $content,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Owner', 'email' => 'owner@embeds.test', 'password' => Hash::make('password-123'),
            'role' => User::ROLE_ADMIN, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    // Recognising links ----------------------------------------------------

    public function test_a_youtube_watch_link_becomes_a_player(): void
    {
        $embed = $this->embeds->resolve('https://www.youtube.com/watch?v=XCbuZpwqx-c');

        $this->assertNotNull($embed);
        $this->assertSame('youtube', $embed->provider);
        $this->assertSame(Embed::RATIO, $embed->kind);

        // Privacy is on by default, so the no-cookie host is used.
        $this->assertStringContainsString('youtube-nocookie.com/embed/XCbuZpwqx-c', (string) $embed->src);
    }

    public function test_every_shape_of_youtube_link_finds_the_same_video(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v=XCbuZpwqx-c',
            'https://youtu.be/XCbuZpwqx-c',
            'https://m.youtube.com/watch?v=XCbuZpwqx-c',
            'https://www.youtube.com/embed/XCbuZpwqx-c',
            'https://www.youtube.com/live/XCbuZpwqx-c',
            'https://www.youtube.com/watch?feature=share&v=XCbuZpwqx-c',
        ] as $url) {
            $this->assertStringContainsString(
                '/embed/XCbuZpwqx-c',
                (string) $this->embeds->resolve($url)?->src,
                $url.' was not recognised.'
            );
        }
    }

    public function test_a_start_time_is_carried_into_the_player(): void
    {
        $this->assertStringContainsString(
            'start=90',
            (string) $this->embeds->resolve('https://youtu.be/XCbuZpwqx-c?t=1m30s')?->src
        );

        $this->assertStringContainsString(
            'start=42',
            (string) $this->embeds->resolve('https://www.youtube.com/watch?v=XCbuZpwqx-c&t=42')?->src
        );
    }

    public function test_a_short_is_given_a_phone_shaped_box(): void
    {
        $embed = $this->embeds->resolve('https://www.youtube.com/shorts/XCbuZpwqx-c');

        $this->assertSame('9-16', $embed?->ratio);
        $this->assertSame(400, $embed?->maxWidth);
    }

    public function test_the_social_providers_are_recognised(): void
    {
        $expected = [
            'https://www.instagram.com/p/C1abcdEfGhi/' => 'instagram',
            'https://www.instagram.com/reel/C1abcdEfGhi/' => 'instagram',
            'https://x.com/jack/status/20' => 'x',
            'https://twitter.com/jack/status/1234567890123456789' => 'x',
            'https://www.tiktok.com/@someone/video/7123456789012345678' => 'tiktok',
            'https://www.facebook.com/someone/posts/1234567890' => 'facebook',
            'https://www.linkedin.com/posts/alice_a-title-activity-7180000000000000000-AbCd' => 'linkedin',
            'https://www.reddit.com/r/php/comments/abc123/a_title/' => 'reddit',
            'https://www.pinterest.com/pin/1234567890/' => 'pinterest',
        ];

        foreach ($expected as $url => $provider) {
            $this->assertSame($provider, $this->embeds->resolve($url)?->provider, $url.' was not recognised.');
        }
    }

    public function test_the_media_and_document_providers_are_recognised(): void
    {
        $expected = [
            'https://vimeo.com/347119375' => 'vimeo',
            'https://www.dailymotion.com/video/x8abcde' => 'dailymotion',
            'https://www.twitch.tv/videos/123456789' => 'twitch',
            'https://www.loom.com/share/0123456789abcdef0123456789abcdef' => 'loom',
            'https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT' => 'spotify',
            'https://soundcloud.com/artist/track-name' => 'soundcloud',
            'https://codepen.io/alice/pen/PNaGbb' => 'codepen',
            'https://gist.github.com/octocat/6cad326836d38bd3a7ae' => 'gist',
            'https://www.figma.com/design/abc123/My-File' => 'figma',
            'https://docs.google.com/document/d/1AbCdEfGhIjKlMnOp/edit' => 'google_docs',
            'https://www.google.com/maps/place/Eiffel+Tower/@48.8584,2.2945,17z' => 'google_maps',
            'https://www.openstreetmap.org/#map=15/51.5074/-0.1278' => 'openstreetmap',
            'https://cdn.example.com/talk.mp4' => 'media_file',
            'https://cdn.example.com/episode.mp3' => 'media_file',
        ];

        foreach ($expected as $url => $provider) {
            $this->assertSame($provider, $this->embeds->resolve($url)?->provider, $url.' was not recognised.');
        }
    }

    public function test_an_unrecognised_address_resolves_to_nothing(): void
    {
        $this->assertNull($this->embeds->resolve('https://example.com/a-blog-post'));
        $this->assertNull($this->embeds->resolve('not a url at all'));

        // A link the provider owns but which names no video: a bare channel
        // page on Vimeo, or a maps search with nothing to centre on.
        $this->assertNull($this->embeds->resolve('https://vimeo.com/channels/staffpicks'));
        $this->assertNull($this->embeds->resolve('https://maps.app.goo.gl/abcdef'));
    }

    // Rewriting content ----------------------------------------------------

    public function test_a_link_alone_in_a_paragraph_is_replaced(): void
    {
        $html = $this->embeds->rewrite('<p>https://www.youtube.com/watch?v=XCbuZpwqx-c</p>');

        $this->assertStringContainsString('cms-embed--youtube', $html);
        $this->assertStringContainsString('youtube-nocookie.com/embed/XCbuZpwqx-c', $html);
        $this->assertStringNotContainsString('<p>https://', $html);
    }

    public function test_the_editors_autolinked_form_is_replaced_too(): void
    {
        $url = 'https://www.youtube.com/watch?v=XCbuZpwqx-c';

        $html = $this->embeds->rewrite('<p><a href="'.$url.'">'.$url.'</a></p>');

        $this->assertStringContainsString('cms-embed--youtube', $html);
    }

    public function test_a_link_with_words_around_it_is_left_alone(): void
    {
        $html = '<p>Watch https://www.youtube.com/watch?v=XCbuZpwqx-c tonight.</p>';

        $this->assertSame($html, $this->embeds->rewrite($html));
    }

    public function test_a_link_an_author_wrote_over_their_own_words_is_left_alone(): void
    {
        $html = '<p><a href="https://www.youtube.com/watch?v=XCbuZpwqx-c">https://vimeo.com/347119375</a></p>';

        $this->assertSame($html, $this->embeds->rewrite($html));
    }

    public function test_content_with_nothing_to_embed_comes_back_unchanged(): void
    {
        foreach ([
            '<p>Nothing here but words.</p>',
            '<p>https://example.com/a-blog-post</p>',
            '',
        ] as $html) {
            $this->assertSame($html, $this->embeds->rewrite($html));
        }
    }

    public function test_a_body_that_is_only_a_link_is_replaced(): void
    {
        $html = $this->embeds->rewrite('https://www.youtube.com/watch?v=XCbuZpwqx-c');

        $this->assertStringStartsWith('<figure class="cms-embed', $html);
    }

    /**
     * WordPress stores an embed block as a figure wrapping the bare link. The
     * importer strips the block markers, so this is the shape that arrives.
     */
    public function test_an_imported_wordpress_embed_block_is_replaced_whole(): void
    {
        $html = $this->embeds->rewrite(
            '<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">'
            .'https://vimeo.com/347119375</div></figure>'
        );

        $this->assertStringContainsString('player.vimeo.com/video/347119375', $html);
        $this->assertStringNotContainsString('wp-block-embed', $html);
    }

    public function test_several_links_in_one_body_are_all_replaced(): void
    {
        $html = $this->embeds->rewrite(
            '<p>https://www.youtube.com/watch?v=XCbuZpwqx-c</p>'
            .'<p>Then this one.</p>'
            .'<p>https://vimeo.com/347119375</p>'
        );

        $this->assertStringContainsString('cms-embed--youtube', $html);
        $this->assertStringContainsString('cms-embed--vimeo', $html);
        $this->assertStringContainsString('<p>Then this one.</p>', $html);
    }

    public function test_a_providers_script_is_sent_once_however_many_embeds_use_it(): void
    {
        $html = $this->embeds->rewrite(
            '<p>https://x.com/jack/status/20</p><p>https://x.com/jack/status/21</p>'
        );

        $this->assertSame(2, substr_count($html, 'twitter-tweet'));
        $this->assertSame(1, substr_count($html, 'platform.twitter.com/widgets.js'));
    }

    public function test_iframes_are_lazy_so_a_post_full_of_videos_still_loads(): void
    {
        $html = $this->embeds->rewrite('<p>https://vimeo.com/347119375</p>');

        $this->assertStringContainsString('loading="lazy"', $html);
    }

    // Settings -------------------------------------------------------------

    public function test_turning_embeds_off_leaves_the_link_in_place(): void
    {
        settings()->set('embeds_enabled', false);

        $html = '<p>https://www.youtube.com/watch?v=XCbuZpwqx-c</p>';

        $this->assertSame($html, $this->embeds->rewrite($html));
    }

    public function test_choosing_providers_excludes_the_rest(): void
    {
        settings()->set('embeds_providers', ['vimeo']);

        $this->assertNull($this->embeds->resolve('https://www.youtube.com/watch?v=XCbuZpwqx-c'));
        $this->assertNotNull($this->embeds->resolve('https://vimeo.com/347119375'));
    }

    public function test_turning_privacy_off_uses_the_ordinary_player(): void
    {
        settings()->set('embeds_privacy', false);

        $this->assertStringContainsString(
            'www.youtube.com/embed/',
            (string) $this->embeds->resolve('https://www.youtube.com/watch?v=XCbuZpwqx-c')?->src
        );
    }

    // On the public site ---------------------------------------------------

    public function test_a_published_post_shows_the_player(): void
    {
        $post = $this->article('<p>Have a look at this.</p><p>https://www.youtube.com/watch?v=XCbuZpwqx-c</p>');

        $this->get($post->url())
            ->assertOk()
            ->assertSee('youtube-nocookie.com/embed/XCbuZpwqx-c', false)
            ->assertSee('cms-embed--youtube', false);
    }

    public function test_a_published_page_shows_the_player(): void
    {
        $page = Page::create([
            'title' => 'A page with an embed',
            'slug' => 'a-page-with-an-embed',
            'content' => '<p>https://vimeo.com/347119375</p>',
            'status' => 'published',
        ]);

        $this->get($page->url())
            ->assertOk()
            ->assertSee('player.vimeo.com/video/347119375', false);
    }

    public function test_the_stored_content_is_still_the_link_the_author_typed(): void
    {
        $admin = $this->admin();
        $url = 'https://www.youtube.com/watch?v=XCbuZpwqx-c';

        $this->actingAs($admin)->post(route('admin.posts.store'), [
            'title' => 'A post with a video',
            'content' => '<p>'.$url.'</p>',
            'status' => 'published',
        ])->assertRedirect();

        $post = Post::where('title', 'A post with a video')->firstOrFail();

        // What is saved stays editable: the editor shows a link, not an iframe.
        $this->assertStringContainsString($url, $post->content);
        $this->assertStringNotContainsString('<iframe', $post->content);
    }

    // Sanitising -----------------------------------------------------------

    public function test_a_hand_written_iframe_survives_only_for_a_known_provider(): void
    {
        $sanitizer = app(HtmlSanitizer::class);

        $kept = $sanitizer->clean('<p><iframe src="https://www.instagram.com/p/ABC123/embed/"></iframe></p>');
        $this->assertStringContainsString('instagram.com/p/ABC123/embed/', $kept);

        $dropped = $sanitizer->clean('<p><iframe src="https://evil.example.com/steal"></iframe></p>');
        $this->assertStringNotContainsString('iframe', $dropped);
    }

    public function test_a_site_can_allow_an_extra_frame_host(): void
    {
        config(['embeds.frame_hosts' => ['video.internal.example']]);

        // A fresh instance, because the merged list is worked out once and kept.
        $html = (new HtmlSanitizer)->clean('<iframe src="https://video.internal.example/v/1"></iframe>');

        $this->assertStringContainsString('video.internal.example', $html);
    }
}
