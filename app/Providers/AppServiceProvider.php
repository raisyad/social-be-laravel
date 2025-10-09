<?php

namespace App\Providers;

use Laravel\Sanctum\Sanctum;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Batasi login attempts
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;
            return [
                Limit::perMinute(5)->by($email.$request->ip())->response(function () {
                    return response()->json([
                        'error' => [
                            'code'    => 'TOO_MANY_REQUESTS',
                            'message' => 'Too many attempts. Try again soon.',
                        ]
                    ], 429);
                }),
            ];
        });
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $email = urlencode($notifiable->getEmailForPasswordReset());
            // arahkan ke route bernama password.reset plus query email
            return route('password.reset', ['token' => $token]) . "?email={$email}";
        });

        Validator::extend('username', function ($attribute, $value) {
            return is_string($value)
                && preg_match('/^(?=.{3,30}$)[A-Za-z0-9._-]+$/', $value);
        }, 'The :attribute may only contain letters, numbers, dots, underscores, and hyphens, length 3-30.');
    }
}
