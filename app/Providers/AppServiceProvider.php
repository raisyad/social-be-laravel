<?php

namespace App\Providers;

use Laravel\Sanctum\Sanctum;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
    }
}
