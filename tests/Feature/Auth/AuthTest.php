<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use Illuminate\Support\Facades\Auth;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */

    #[Test]
    public function register_fails_with_validation_errors()
    {
        $res = $this->postJson('/api/register', [
            'username' => '',     // kosong
            'password' => 'Soc123', // < 8
            'password_confirmation' => 'Soc123',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'password']);
    }

    #[Test]
    public function register_success_creates_user_and_returns_token()
    {
        $res = $this->postJson('/api/register', [
            'username' => 'socialAccount',
            'email'    => 'social@example.com',
            'password' => '@_*Social123',
            'password_confirmation' => '@_*Social123',
            'device_name' => 'Samsung-SM-A107F',
        ]);

        $res->assertCreated()
            ->assertJsonStructure([
                'data' => ['user' => ['id', 'username', 'email'], 'token', 'token_type'],
                'meta' => ['message'],
            ]);

        $this->assertDatabaseHas('users', ['username' => 'socialAccount', 'email' => 'social@example.com']);
    }

    #[Test]
    public function login_fails_with_wrong_password()
    {
        User::factory()->create([
            'username' => 'socialAccount',
            'email'    => 'social@example.com',
            'password' => Hash::make('@_*Social123'),
        ]);

        $res = $this->postJson('/api/login', [
            'identifier'  => 'socialAccount',
            'password'    => 'Wrong-pass111', // salah
            'device_name' => 'Samsung-SM-A107F',
        ]);

        $res->assertStatus(401)
            ->assertJsonPath('message', 'The provided credentials are incorrect.');
    }

    #[Test]
    public function login_success_returns_token()
    {
        $user = User::factory()->create([
            'username' => 'socialAccount',
            'email'    => 'social@example.com',
            'password' => Hash::make('@_*Social123'),
        ]);

        $res = $this->postJson('/api/login', [
            'identifier' => 'socialAccount',
            'password' => '@_*Social123',
            'device_name' => 'Samsung-SM-A107F',
        ]);

        $res->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user'        => ['id', 'username', 'email'],
                    'token',
                    'token_type',
                ],
                'meta' => ['message'],
            ]);

        // via email
        $res = $this->postJson('/api/login', [
            'identifier' => 'social@example.com',
            'password'   => '@_*Social123',
        ]);
        $res->assertOk();
    }

    #[Test]
    public function me_requires_authentication()
    {
        $this->getJson('/api/userSelf')->assertStatus(401);
    }

    #[Test]
    public function me_returns_current_user_profile()
    {
        $user = User::factory()->create();

        // Jika guard pakai sanctum:
        Sanctum::actingAs($user);

        $this->getJson('/api/userSelf')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    #[Test]
    public function logout_returns_200_and_message_and_revokes_current_token()
    {
        $user = User::factory()->create();
        $plainToken = $user->createToken('test-device')->plainTextToken;
        $tokenId = explode('|', $plainToken)[0];

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);

        // Logout dengan token tadi
        $this->withHeader('Authorization', "Bearer {$plainToken}")
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('meta.message', 'Logged out');

        // Pastikan token terhapus
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);

        // Bersihkan context auth & session supaya tidak "nyangkut"
        Auth::forgetGuards();      // reset semua guard yang tersimpan di container
        session()->flush();

        $this->withHeader('Authorization', "Bearer {$plainToken}")
            ->getJson('/api/userSelf')
            ->assertStatus(401);
    }
}
