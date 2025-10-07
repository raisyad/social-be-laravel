<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/userSelf',        [AuthController::class, 'userSelf'])->middleware('auth:sanctum');
    Route::post('/logout',   [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/tokens',    [AuthController::class, 'tokens']);
    Route::delete('/tokens/{id}', [AuthController::class, 'revoke']);
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
