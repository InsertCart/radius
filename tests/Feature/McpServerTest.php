<?php

namespace Tests\Feature;

use App\Models\McpToken;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cms.install_lock' => __FILE__]);
        $this->get('/');
        modules()->sync();
        $this->assertFalse(modules()->enabled('mcp'));
        modules()->enable('mcp');
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function issue(string $access): string
    {
        $this->actingAs($this->admin)->post(route('admin.mcp.store'), ['name' => 'Assistant', 'access' => $access])
            ->assertRedirect(route('admin.mcp.index'));
        $secret = session('mcp_secret');
        $this->get(route('admin.mcp.index'))->assertOk()->assertSee($secret);
        $this->get(route('admin.mcp.index'))->assertDontSee($secret);

        return $secret;
    }

    private function rpc(string $method, array $params = [], array $headers = []): TestResponse
    {
        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params], $headers);
    }

    private function callTool(string $name, array $args = []): TestResponse
    {
        return $this->rpc('tools/call', ['name' => $name, 'arguments' => (object) $args]);
    }

    private function toolData(string $tool, array $args = []): array
    {
        $response = $this->callTool($tool, $args)->assertOk()->assertJsonMissingPath('error')->assertJsonMissingPath('result.isError');

        return json_decode($response->json('result.content.0.text'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_initialization_discovery_and_access_boundaries(): void
    {
        $secret = $this->issue('read');
        $this->rpc('ping')->assertUnauthorized(); // Admin session alone.
        $this->withToken($secret);
        $this->rpc('initialize', ['protocolVersion' => '2025-06-18'])
            ->assertOk()->assertJsonPath('result.protocolVersion', '2025-06-18');
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])->assertStatus(202);
        $this->getJson('/mcp')->assertStatus(405);
        $this->rpc('ping', [], ['Origin' => 'https://other.example'])->assertForbidden();
        $this->rpc('ping', [], ['MCP-Protocol-Version' => 'unknown'])->assertBadRequest();
        $this->rpc('tools/list')->assertJsonCount(3, 'result.tools')->assertJsonMissing(['name' => 'create_post']);
        $this->callTool('create_post', ['title' => 'Denied'])->assertJsonPath('error.code', -32602);
        $this->assertDatabaseCount('posts', 0);

        modules()->disable('blog');
        $this->rpc('ping')->assertNotFound();
        modules()->enable('mcp');
        $this->delete(route('admin.mcp.destroy', McpToken::sole()))->assertRedirect();
        $this->rpc('ping')->assertUnauthorized();
    }

    public function test_post_lifecycle_and_analysis(): void
    {
        $this->withToken($this->issue('write'));
        $this->rpc('tools/list')->assertJsonCount(5, 'result.tools');
        $post = $this->toolData('create_post', ['title' => 'First article', 'content' => '<p data-private="x">First draft</p>']);
        $id = $post['id'];
        $this->assertSame('draft', $post['status']);
        $this->assertDatabaseHas('posts', ['id' => $id, 'content' => '<p>First draft</p>', 'author_id' => $this->admin->id]);
        $this->callTool('edit_post', ['id' => $id, 'author_id' => 999])->assertJsonPath('result.isError', true);
        $this->callTool('edit_post', ['id' => $id, 'status' => 'invalid'])->assertJsonPath('result.isError', true);
        $this->callTool('edit_post', ['id' => $id])->assertJsonPath('result.isError', true);
        $this->callTool('get_post', ['id' => 9999])->assertJsonPath('result.isError', true);
        $this->toolData('edit_post', ['id' => $id, 'title' => 'Edited article', 'status' => 'published']);
        $edited = $this->toolData('get_post', ['id' => $id]);
        $this->assertSame('Edited article', $edited['title']);
        $this->assertSame('first-article', $edited['slug']);
        $this->assertSame('<p>First draft</p>', $edited['content']);
        $this->assertNotNull($edited['published_at']);
        $this->toolData('create_post', ['title' => 'Second article']);
        $page = $this->toolData('list_posts', ['search' => 'article', 'limit' => 1]);
        $this->assertCount(1, $page['posts']);
        $this->assertSame($id, $this->toolData('list_posts', ['before_id' => $page['before_id']])['posts'][0]['id']);
        Post::whereKey($id)->increment('views', 8);
        $this->assertSame(2, $this->toolData('analyze_posts')['posts']);
        $this->assertSame(8, $this->toolData('analyze_posts')['views']);
        $this->assertSame(8, $this->toolData('analyze_posts', ['id' => $id])['views']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'post.updated', 'user_id' => $this->admin->id]);
        $this->admin->update(['status' => 'suspended']);
        $this->rpc('tools/list')->assertUnauthorized();
    }
}
