<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentModerationTest extends TestCase
{
    use RefreshDatabase;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/');
        modules()->sync();
        modules()->enable('blog');
        $this->post = Post::create(['title' => 'Discussion']);
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => $role, 'email' => $role.'@example.test', 'password' => 'password',
            'role' => $role, 'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    public function test_comments_link_to_the_post_editor_by_slug(): void
    {
        $this->post->comments()->create(['body' => 'A reader comment']);
        $index = route('admin.comments.index');
        $editor = route('admin.posts.edit', $this->post->slug);
        $this->actingAs($this->user(User::ROLE_ADMIN))->get($index)
            ->assertOk()->assertSee('href="'.$editor.'"', false);
        $this->get($editor)->assertOk()->assertViewHas('post', fn (Post $post) => $post->is($this->post));

        $this->post->delete();
        $this->get($index)->assertOk()->assertSeeText('on a deleted post')
            ->assertDontSee($editor)->assertDontSee(route('admin.posts.edit', $this->post->id));
    }

    public function test_admin_can_edit_another_users_text_and_keep_its_author_and_status(): void
    {
        $author = $this->user(User::ROLE_CUSTOMER);
        $comment = $this->post->comments()->create(['user_id' => $author->id, 'body' => 'Original', 'status' => 'approved']);
        $index = route('admin.comments.index', ['status' => 'approved']);
        $action = route('admin.comments.update-body', $comment);
        $this->actingAs($this->user(User::ROLE_ADMIN))->get($index)
            ->assertOk()->assertSee('Edit comment')->assertSee($action)->assertSee('Original');
        $this->from($index)->patch($action, ['body' => 'x'])
            ->assertRedirect($index)->assertSessionHasErrorsIn('comment'.$comment->id, 'body');
        $this->get($index)->assertOk()->assertSee('at least 3 characters');
        $this->patch($action, ['body' => 'Corrected text', 'user_id' => null, 'status' => 'spam'])
            ->assertRedirect($index)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id, 'body' => 'Corrected text', 'user_id' => $author->id, 'status' => 'approved',
        ]);
    }

    public function test_staff_can_bulk_delete_selected_comments_and_their_replies(): void
    {
        $first = $this->post->comments()->create(['body' => 'First comment']);
        $reply = $this->post->comments()->create(['body' => 'Reply', 'parent_id' => $first->id]);
        $second = $this->post->comments()->create(['body' => 'Second comment']);
        $keep = $this->post->comments()->create(['body' => 'Keep this comment']);
        $index = route('admin.comments.index');
        $action = route('admin.comments.bulk-destroy');
        $this->actingAs($this->user(User::ROLE_EDITOR))->get($index)
            ->assertOk()->assertSee('Select all on this page')->assertSee('Delete selected')->assertDontSee('Edit comment');
        $this->patch(route('admin.comments.update-body', $first), ['body' => 'Changed'])->assertForbidden();
        $this->from($index)->delete($action)->assertSessionHasErrors('ids');
        $this->delete($action, ['ids' => [$first->id, 'invalid']])->assertSessionHasErrors('ids.1');
        $this->assertDatabaseCount('comments', 4);
        $this->delete($action, ['ids' => [$first->id, $second->id]])
            ->assertRedirect($index)->assertSessionHasNoErrors();
        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
        $this->assertModelMissing($reply);
        $this->assertModelExists($keep);
    }

    public function test_customer_cannot_edit_or_bulk_delete_comments(): void
    {
        $comment = $this->post->comments()->create(['body' => 'Original']);
        $this->actingAs($this->user(User::ROLE_CUSTOMER));
        $this->patch(route('admin.comments.update-body', $comment), ['body' => 'Changed'])->assertForbidden();
        $this->delete(route('admin.comments.bulk-destroy'), ['ids' => [$comment->id]])->assertForbidden();
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'body' => 'Original']);
    }

    public function test_deleting_the_last_page_returns_to_the_previous_page_of_the_same_filter(): void
    {
        $last = $this->post->comments()->create(['body' => 'Oldest comment', 'status' => 'spam']);
        for ($i = 1; $i <= 25; $i++) {
            $this->post->comments()->create(['body' => 'Newer comment', 'status' => 'spam']);
        }
        $secondPage = route('admin.comments.index', ['status' => 'spam', 'page' => 2]);
        $this->actingAs($this->user(User::ROLE_ADMIN))->get($secondPage)
            ->assertOk()->assertSee('Oldest comment')->assertDontSee('Newer comment');
        $this->from($secondPage)->delete(route('admin.comments.bulk-destroy'), ['ids' => [$last->id]])
            ->assertRedirect($secondPage);
        $this->followingRedirects()->get($secondPage)
            ->assertOk()->assertSee('Newer comment')->assertSee('Selected comments deleted.');
    }
}
