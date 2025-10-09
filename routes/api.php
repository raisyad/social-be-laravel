<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Profile\FollowController;
use App\Http\Controllers\Post\PostController;
use App\Http\Controllers\Profile\PrivacyController;
use App\Http\Controllers\Post\LikeController;
use App\Http\Controllers\Post\CommentController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:login');

// ==== Profile ====
Route::get('/users/{user}', [ProfileController::class, 'show']);

// // posts milik user tertentu
// Posts  timeline profil user lain
Route::get('/users/{user}/posts', [PostController::class, 'indexByUser']);

// Views Liked Post
Route::get('/posts/{post}/likes', [LikeController::class, 'index']);

Route::get ('/posts/{post}/comments', [CommentController::class, 'index']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/userSelf',        [AuthController::class, 'userSelf'])->middleware('auth:sanctum');
    Route::post('/logout',   [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/tokens',    [AuthController::class, 'tokens']);
    Route::delete('/tokens/{id}', [AuthController::class, 'revoke']);


    // Accepted followers only
    // hanya owner yang boleh terima/ tolak request
    Route::post('/follow-requests/{follower}/accept', [FollowController::class, 'accept']);
    Route::post('/follow-requests/{follower}/reject', [FollowController::class, 'reject']);
    // toggle visibility
    Route::patch('/me/profile/visibility', [PrivacyController::class, 'update']);

    // daftar follow-requests (yang minta follow saya & pending)
    Route::get('/me/follow-requests', [FollowController::class, 'requests']);


    // Profile
    Route::post('/users/{user}/follow',   [FollowController::class, 'follow']);
    Route::delete('/users/{user}/follow', [FollowController::class, 'unfollow']);
    Route::get('/me/profile', [ProfileController::class, 'me']);
    Route::put('/me/profile', [ProfileController::class, 'update']); // kamu sudah punya


    // Posts (punya sendiri)
    Route::get('/me/posts',                [PostController::class, 'myIndex']);
    Route::post('/me/posts',               [PostController::class, 'store']);
    Route::put('/me/posts/{post}',         [PostController::class, 'update']);
    Route::delete('/me/posts/{post}',      [PostController::class, 'destroy']);



    // Likes - Comment
    Route::post   ('/posts/{post}/like',   [LikeController::class, 'like']);
    Route::delete ('/posts/{post}/like',   [LikeController::class, 'unlike']);

    Route::post   ('/posts/{post}/comments', [CommentController::class, 'store']);
    Route::put    ('/comments/{comment}',    [CommentController::class, 'update']);
    Route::delete ('/comments/{comment}',    [CommentController::class, 'destroy']);
});

Route::middleware(['auth:sanctum','verified'])->group(function () {
    Route::get('/only-verified', fn() => ['ok' => true]);
});

// link verifikasi (di-klik dari email)
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')     // TANPA auth
    ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
    ->middleware('auth:sanctum');

Route::post('/password/email', [PasswordResetController::class, 'sendResetLink'])
    ->middleware('throttle:6,1');
Route::post('/password/reset', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:6,1');
Route::get('/reset-password/{token}', function (Request $request, $token) {
    return response()->json([
        'meta' => [
            'message' => 'Your password will be reset'
        ],
        'token'   => $token,
        'email'   => $request->query('email'),
    ]);
})->name('password.reset');



// Comment
// Route::get('/posts/{post}/comments', [CommentController::class, 'index']);
