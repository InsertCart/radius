<?php

namespace Tests\Feature;

use App\Models\LayoutPreset;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BuilderSavedSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/admin'); // burn the first request (stale base path)
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => 'U', 'email' => $role.'@x.test', 'password' => Hash::make('password123'),
            'role' => $role, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function section(string $widgetType, array $settings): array
    {
        return [
            'type' => 'section', 'id' => 's1', 'settings' => [],
            'elements' => [[
                'type' => 'column', 'id' => 'c1', 'settings' => [],
                'elements' => [[
                    'type' => 'widget', 'id' => 'w1',
                    'widgetType' => $widgetType, 'settings' => $settings,
                ]],
            ]],
        ];
    }

    public function test_a_section_can_be_saved_and_is_returned_with_its_data(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->postJson('/admin/builder/presets', [
                'name' => 'Hero banner',
                'data' => $this->section('heading', ['text' => 'Hello']),
            ])
            ->assertOk()
            ->assertJsonPath('preset.name', 'Hero banner')
            ->assertJsonPath('preset.data.type', 'section');

        $preset = LayoutPreset::sole();
        $this->assertSame('section', $preset->type);
        $this->assertSame('Hello', $preset->data['elements'][0]['elements'][0]['settings']['text']);
    }

    public function test_only_a_section_can_be_saved(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->postJson('/admin/builder/presets', [
                'name' => 'Loose widget',
                'data' => ['type' => 'widget', 'widgetType' => 'heading', 'elements' => []],
            ])
            ->assertUnprocessable();

        $this->assertSame(0, LayoutPreset::count());
    }

    public function test_an_editor_cannot_smuggle_raw_html_in_through_a_saved_section(): void
    {
        $this->actingAs($this->user(User::ROLE_EDITOR))
            ->postJson('/admin/builder/presets', [
                'name' => 'Sneaky',
                'data' => $this->section('html', ['html' => '<script>alert(1)</script>']),
            ])
            ->assertOk();

        $stored = json_encode(LayoutPreset::sole()->data);
        $this->assertStringNotContainsString('<script>', $stored);
    }

    public function test_saved_sections_are_offered_in_every_editor_not_just_where_they_were_made(): void
    {
        LayoutPreset::create([
            'name' => 'Hero 2', 'type' => 'section', 'data' => $this->section('heading', ['text' => 'Hi']),
        ]);

        $page = Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published']);

        $html = $this->actingAs($this->user(User::ROLE_ADMIN))
            ->get(route('admin.builder.edit', ['page', $page->id]))
            ->assertOk()
            ->getContent();

        preg_match('/window\.CB_BOOT = (.*?);<\/script>/s', $html, $match);
        $boot = json_decode($match[1] ?? 'null', true);

        $this->assertSame('Hero 2', $boot['presets'][0]['name'] ?? null);
        $this->assertSame('Hi', $boot['presets'][0]['data']['elements'][0]['elements'][0]['settings']['text'] ?? null);
    }

    public function test_the_builder_home_lists_saved_sections_with_a_delete_button(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)->get('/admin/builder')
            ->assertOk()
            ->assertSee('click the folder icon to save it');

        $preset = LayoutPreset::create([
            'name' => 'Pricing strip', 'type' => 'section', 'data' => $this->section('heading', []),
        ]);

        $this->actingAs($admin)->get('/admin/builder')
            ->assertOk()
            ->assertSee('Pricing strip')
            ->assertSee('/admin/builder/presets/'.$preset->id, false);
    }

    public function test_a_saved_section_can_be_deleted(): void
    {
        $preset = LayoutPreset::create([
            'name' => 'Old', 'type' => 'section', 'data' => $this->section('heading', []),
        ]);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->delete('/admin/builder/presets/'.$preset->id)
            ->assertRedirect();

        $this->assertModelMissing($preset);
    }
}
