<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PageCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/');
        Page::create(['title' => 'About us', 'slug' => 'about-cache', 'status' => 'published']);
    }

    public function test_off_by_default(): void
    {
        $this->get('/about-cache')->assertOk()->assertHeaderMissing('X-Page-Cache');
    }

    public function test_second_guest_visit_is_served_from_cache_with_their_own_csrf_token(): void
    {
        settings()->set('cache_enabled', true);

        $this->get('/about-cache')->assertOk()->assertHeader('X-Page-Cache', 'MISS');

        $this->flushSession();
        $this->startSession();
        $token = session()->token();

        $hit = $this->get('/about-cache')->assertOk()->assertHeader('X-Page-Cache', 'HIT');
        $hit->assertSee('content="'.$token.'"', false);
        $hit->assertDontSee('__RADIUS_CSRF_TOKEN__', false);
    }

    public function test_saving_content_retires_the_cached_page(): void
    {
        settings()->set('cache_enabled', true);
        $this->get('/about-cache')->assertHeader('X-Page-Cache', 'MISS');

        Page::where('slug', 'about-cache')->first()->update(['title' => 'About the team']);

        $this->get('/about-cache')->assertHeader('X-Page-Cache', 'MISS')->assertSee('About the team');
    }

    public function test_signed_in_visitors_flash_messages_and_private_pages_are_never_cached(): void
    {
        settings()->set('cache_enabled', true);

        $user = User::create([
            'name' => 'C', 'email' => 'c@example.test', 'password' => Hash::make('x'),
            'role' => User::ROLE_CUSTOMER, 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $this->actingAs($user)->withSession(['auth.two_factor_confirmed' => true])
            ->get('/about-cache')->assertHeaderMissing('X-Page-Cache');
        app('auth')->forgetGuards();
        $this->flushSession();

        $this->withSession(['_flash.old' => ['status'], 'status' => 'Thanks!'])
            ->get('/about-cache')->assertHeaderMissing('X-Page-Cache');
        $this->flushSession();

        $this->get('/login')->assertHeaderMissing('X-Page-Cache');
        $this->get('/about-cache?utm_source=x')->assertHeaderMissing('X-Page-Cache');
    }
}
