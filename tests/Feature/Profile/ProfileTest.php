<?php

namespace Tests\Feature\Profile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pastikan Sanctum berjalan sebagai token, bukan cookie/session
        config(['sanctum.stateful' => []]);
        config(['sanctum.guard' => ['sanctum']]);
    }
    /**
     * A basic feature test example.
     */

    #[Test]
    public function guest_can_view_public_profile()
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'full_name'  => 'John Doe',
            'bio'        => 'Halo!',
            'visibility' => 'public',
        ]);

        $this->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.username', $user->username);
    }

    #[Test]
    public function guest_cannot_view_private_profile()
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'visibility' => 'private',
        ]);

        $this->getJson("/api/users/{$user->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function follower_can_view_private_profile()
    {
        $owner   = User::factory()->create();
        $viewer  = User::factory()->create();

        $owner->profile()->create(['visibility' => 'private']);
        // jadikan viewer sebagai follower
        $owner->followers()->attach($viewer->id);

        Sanctum::actingAs($viewer);

        $this->getJson("/api/users/{$owner->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $owner->id);
    }

    #[Test]
    public function me_can_update_own_profile_text_fields()
    {
        $me = User::factory()->create();
        $me->profile()->create([
            'full_name' => null,
            'bio'       => null,
        ]);

        Sanctum::actingAs($me);

        $payload = [
            'full_name' => 'Jane Roe',
            'bio'       => 'Bio baru',
            'gender'    => 'female',
        ];

        $this->putJson('/api/me/profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Jane Roe')
            ->assertJsonPath('data.bio', 'Bio baru')
            ->assertJsonPath('data.gender', 'female');

        $this->assertDatabaseHas('user_profiles', [
            'user_id'   => $me->id,
            'full_name' => 'Jane Roe',
            'bio'       => 'Bio baru',
            'gender'    => 'female',
        ]);
    }

    #[Test]
    public function me_can_update_avatar_and_cover_images()
    {
        Storage::fake('public');

        $me = User::factory()->create();
        $me->profile()->create();

        Sanctum::actingAs($me);

        $payload = [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 500, 500),
            'cover'  => UploadedFile::fake()->image('cover.png', 1200, 300),
        ];

        $res = $this->putJson('/api/me/profile', $payload)
            ->assertOk();

        // Pastikan path tersimpan & file eksis di storage
        $avatarPath = $res->json('data.avatar_url');
        $coverPath  = $res->json('data.cover_url');

        // Kalau resource mengembalikan full URL, ekstrak path-nya dulu jika perlu.
        // asumsikan menyimpan RELATIVE PATH ke DB.
        $this->assertTrue(Storage::disk('public')->exists($avatarPath));
        $this->assertTrue(Storage::disk('public')->exists($coverPath));
    }

    #[Test]
    public function can_follow_and_unfollow_user()
    {
        $me    = User::factory()->create();
        $other = User::factory()->create();

        Sanctum::actingAs($me);

        // FOLLOW
        $this->postJson("/api/users/{$other->id}/follow")
            ->assertOk()
            ->assertJsonPath('meta.message', 'Followed');

        $this->assertDatabaseHas('user_follows', [
            'follower_id' => $me->id,
            'followee_id' => $other->id,
        ]);

        // UNFOLLOW
        $this->deleteJson("/api/users/{$other->id}/follow")
            ->assertOk()
            ->assertJsonPath('meta.message', 'Unfollowed');

        $this->assertDatabaseMissing('user_follows', [
            'follower_id' => $me->id,
            'followee_id' => $other->id,
        ]);
    }

    #[Test]
    public function cannot_follow_self_or_duplicate_follow()
    {
        $me = User::factory()->create();
        Sanctum::actingAs($me);

        // follow diri sendiri -> 422
        $this->postJson("/api/users/{$me->id}/follow")
            ->assertStatus(422);

        // follow user lain 2x -> 200 lalu 200/409 tergantung implementasi, tapi DB tetap unik
        $other = User::factory()->create();

        $this->postJson("/api/users/{$other->id}/follow")->assertOk();
        $this->postJson("/api/users/{$other->id}/follow")->assertOk();

        // pastikan hanya sekali di DB
        $this->assertDatabaseCount('user_follows', 1);
    }
}
