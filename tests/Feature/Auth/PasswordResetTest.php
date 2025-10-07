<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    #[Test]
    public function request_reset_link_sends_notification()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/password/email', ['email' => $user->email])
            ->assertOk(); // atau 200/202 tergantung controller kamu

        Notification::assertSentTo($user, ResetPassword::class);
    }

    #[Test]
    public function can_reset_password_with_valid_token()
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPass123!')
        ]);

        /** @var \Illuminate\Auth\Passwords\PasswordBroker $broker */
        $broker = Password::broker();
        $token  = $broker->createToken($user);

        $payload = [
            'email'                 => $user->email,
            'token'                 => $token,
            'password'              => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ];

        $this->postJson('/api/password/reset', $payload)
            ->assertOk();

        $this->assertTrue(Hash::check('NewPass123!', $user->fresh()->password));
    }

    #[Test]
    public function reset_fails_with_invalid_token()
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass123!')]);

        $payload = [
            'email'                 => $user->email,
            'token'                 => 'token-salah',
            'password'              => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ];

        $this->postJson('/api/password/reset', $payload)
            ->assertStatus(400);

        $this->assertTrue(Hash::check('OldPass123!', $user->fresh()->password));
    }
}
