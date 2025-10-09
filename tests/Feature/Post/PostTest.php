<?php

namespace Tests\Feature\Post;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class PostTest extends TestCase
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
    public function can_list_posts_of_a_public_user()
    {
        $owner = User::factory()->create();
        $owner->profile()->create(['visibility' => 'public']);

        $posts = Post::factory()->count(3)->for($owner)->create();

        $this->getJson("/api/users/{$owner->id}/posts")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function cannot_list_posts_of_private_user_if_not_follower()
    {
        $owner = User::factory()->create();
        $owner->profile()->create(['visibility' => 'private']);

        $this->getJson("/api/users/{$owner->id}/posts")
            ->assertStatus(403);
    }

    #[Test]
    public function follower_can_list_posts_of_private_user()
    {
        $owner  = User::factory()->create();
        $viewer = User::factory()->create();

        $owner->profile()->create(['visibility' => 'private']);
        $owner->followers()->attach($viewer->id, [
            'status' => 'accepted',
            'approved_at' => now(),
        ]);

        Post::factory()->count(2)->for($owner)->create();

        Sanctum::actingAs($viewer);

        $this->getJson("/api/users/{$owner->id}/posts")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function me_can_list_my_posts()
    {
        $me = User::factory()->create();
        Post::factory()->count(4)->for($me)->create();

        Sanctum::actingAs($me);

        $this->getJson('/api/me/posts')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    #[Test]
    public function me_can_create_post_with_media()
    {
        Storage::fake('public');

        $me = User::factory()->create();
        Sanctum::actingAs($me);

        $payload = [
            'content' => 'Hello world',
            'media'   => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.png'),
            ],
        ];

        $res = $this->postJson('/api/me/posts', $payload)
            ->assertCreated()
            ->assertJsonPath('data.content', 'Hello world');

        // Pastikan media tersimpan
        $postId = $res->json('data.id');
        $this->assertDatabaseHas('posts', ['id' => $postId, 'user_id' => $me->id]);

        $this->assertDatabaseCount('post_media', 2);

        // Cek file ada
        $media = PostMedia::where('post_id', $postId)->get();
        foreach ($media as $m) {
            $this->assertTrue(Storage::disk('public')->exists($m->media_url));
        }
    }

    #[Test]
    public function me_can_update_post_change_text_add_and_remove_media()
    {
        Storage::fake('public');

        $me   = User::factory()->create();
        $post = Post::factory()->for($me)->create(['content' => 'Old']);

        // media awal
        $firstPath = UploadedFile::fake()->image('x.jpg')->store('posts', 'public');
        $m1 = $post->media()->create([
            'media_url'  => $firstPath,
            'media_type' => 'image',
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($me);

        $payload = [
            'content'           => 'New content',
            'remove_media_ids'  => [$m1->id],
            'media'             => [ UploadedFile::fake()->image('y.jpg') ],
        ];

        $res = $this->putJson("/api/me/posts/{$post->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.content', 'New content');

        // media lama hilang, media baru ada
        $this->assertDatabaseMissing('post_media', ['id' => $m1->id]);
        $this->assertDatabaseCount('post_media', 1);

        $newMediaPath = PostMedia::where('post_id', $post->id)->first()->media_url;
        $this->assertTrue(Storage::disk('public')->exists($newMediaPath));
    }

    #[Test]
    public function user_cannot_update_or_delete_someone_elses_post()
    {
        $owner  = User::factory()->create();
        $intruder = User::factory()->create();

        $post = Post::factory()->for($owner)->create();

        Sanctum::actingAs($intruder);

        $this->putJson("/api/me/posts/{$post->id}", ['content' => 'hack'])
            ->assertStatus(403);

        $this->deleteJson("/api/me/posts/{$post->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function me_can_delete_own_post()
    {
        $me = User::factory()->create();
        $post = Post::factory()->for($me)->create();

        Sanctum::actingAs($me);

        $this->deleteJson("/api/me/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('meta.message', 'Post deleted');

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }
}
