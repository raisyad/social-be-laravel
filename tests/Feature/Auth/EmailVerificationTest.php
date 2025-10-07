<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    #[Test]
    public function authenticated_user_can_request_verification_email()
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/email/verification-notification')
            ->assertAccepted(); // 202

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function clicking_signed_link_verifies_the_user()
    {
        $user = User::factory()->unverified()->create();

        // bikin signed URL sama seperti yang ada di route kamu:
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(120),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $this->getJson($url)->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    #[Test]
    public function wrong_hash_returns_400_and_does_not_verify()
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(120),
            ['id' => $user->id, 'hash' => sha1('salah@example.com')]
        );

        $this->getJson($url)->assertStatus(400);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
