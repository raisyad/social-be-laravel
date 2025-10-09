<?php

namespace Tests\Feature\Post;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use App\Models\PostComment;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => []]);
        config(['sanctum.guard' => ['sanctum']]);
    }

    /**
     * A basic feature test example.
     */
    public function can_list_create_update_delete_comments_and_count_is_correct()
    {
        $owner = User::factory()->create();
        $owner->profile()->create(['visibility' => 'public']);
        $post  = Post::factory()->for($owner)->create();

        $me = User::factory()->create();
        Sanctum::actingAs($me);

        // list awal kosong (GET publik)
        $this->getJson("/api/posts/{$post->id}/comments")
             ->assertOk()
             ->assertJsonCount(0, 'data');

        // create
        $c = $this->postJson("/api/posts/{$post->id}/comments", [
                'content' => 'First!',
            ])->assertCreated()
              ->json('data');

        $this->assertDatabaseHas('post_comments', [
            'id' => $c['id'], 'post_id' => $post->id, 'user_id' => $me->id,
        ]);
        $this->assertEquals(1, $post->fresh()->comments_count);

        // update
        $this->putJson("/api/comments/{$c['id']}", ['content' => 'Edited'])
             ->assertOk()
             ->assertJsonPath('data.content', 'Edited');

        // delete
        $this->deleteJson("/api/comments/{$c['id']}")
             ->assertOk()
             ->assertJsonPath('meta.message', 'Comment deleted');

        $this->assertSoftDeleted('post_comments', ['id' => $c['id']]);
        $this->assertEquals(0, $post->fresh()->comments_count);
    }

    #[Test]
    public function cannot_comment_private_post_if_not_follower()
    {
        $owner = User::factory()->create();
        $owner->profile()->create(['visibility' => 'private']);
        $post  = Post::factory()->for($owner)->create();

        $me = User::factory()->create();
        Sanctum::actingAs($me);

        $this->postJson("/api/posts/{$post->id}/comments", [
            'content' => 'hello',
        ])->assertStatus(403);
    }

    #[Test]
    public function only_owner_can_update_or_delete_comment()
    {
        $owner = User::factory()->create();
        $owner->profile()->create(['visibility' => 'public']);
        $post  = Post::factory()->for($owner)->create();

        $author = User::factory()->create();
        Sanctum::actingAs($author);

        $c = PostComment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'content' => 'mine',
        ]);

        // user lain
        $intruder = User::factory()->create();
        Sanctum::actingAs($intruder);

        $this->putJson("/api/comments/{$c->id}", ['content' => 'hack'])->assertStatus(403);
        $this->deleteJson("/api/comments/{$c->id}")->assertStatus(403);

        // owner comment boleh
        Sanctum::actingAs($author);
        $this->deleteJson("/api/comments/{$c->id}")->assertOk();
    }
}
