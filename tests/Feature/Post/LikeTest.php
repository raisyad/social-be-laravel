<?php

namespace Tests\Feature\Post;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class LikeTest extends TestCase
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
    #[Test]
    public function can_like_and_unlike_post_and_counters_update()
    {
        $owner = User::factory()->create();
        $owner->profile()->create(['visibility' => 'public']);
        $post  = Post::factory()->for($owner)->create();

        $me = User::factory()->create();
        Sanctum::actingAs($me);

        // like (idempoten)
        $this->postJson("/api/posts/{$post->id}/like")
             ->assertOk()
             ->assertJsonPath('meta.message', 'Liked');

        $this->postJson("/api/posts/{$post->id}/like")->assertOk();

        $this->assertDatabaseHas('post_likes', [
            'user_id' => $me->id, 'post_id' => $post->id,
        ]);

        $this->assertEquals(1, $post->fresh()->likes_count);

        // unlike
        $this->deleteJson("/api/posts/{$post->id}/like")
             ->assertOk()
             ->assertJsonPath('meta.message', 'Unliked');

        $this->assertDatabaseMissing('post_likes', [
            'user_id' => $me->id, 'post_id' => $post->id,
        ]);
        $this->assertEquals(0, $post->fresh()->likes_count);
    }

    #[Test]
    public function cannot_like_private_post_if_not_follower()
    {
        $owner = User::factory()->create();
        $owner->profile()->create(['visibility' => 'private']);
        $post  = Post::factory()->for($owner)->create();

        $me = User::factory()->create();
        Sanctum::actingAs($me);

        $this->postJson("/api/posts/{$post->id}/like")
             ->assertStatus(403);
    }
}
