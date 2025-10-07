<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use App\Models\User;

class EmailVerificationController extends Controller
{
    // Kirim ulang email verifikasi ke user yang login
    public function send(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['meta' => ['message' => 'Email already verified']], 200);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['meta' => ['message' => 'Verification link sent']], 202);
    }

    // Endpoint yang dikunjungi dari link email
    public function verify(Request $request, $id, $hash)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Validasi hash dari email sekarang
        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link'], 400);
        }

        if ($user->hasVerifiedEmail()) {
            // opsional: redirect ke FE atau balas JSON
            return response()->json(['meta' => ['message' => 'Email already verified']]);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        // Jika ini SPA, bisa redirect ke FE:
        // return redirect(config('app.frontend_url') . '/verify/success');
        return response()->json(['meta' => ['message' => 'Email verified']]);
    }
}
