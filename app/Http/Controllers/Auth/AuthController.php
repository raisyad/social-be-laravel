<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        // Registration logic here
        $user = User::create([
            'username' => $request->string('username'),
            'email'    => $request->string('email'),
            'password' => Hash::make($request->string('password')),
        ]);

        event(new Registered($user)); // untuk kebutuhan emailVerify

        activity()->useLog('auth')->causedBy($user)->withProperties([
        'ip' => $request->ip(),
        ])->log('auth.registered');

        // ======= Generate Token ======= -> IF DID NOT SET DEVICE NAME, USE UUID
        $tokenName = $request->string('device_name') ?: 'api-'.Str::uuid();
        $token = $user->createToken($tokenName, ['*'])->plainTextToken;

        return response()->json([
            'data' => [
                'user'       => new UserResource($user),
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
            'meta' => ['message' => 'Registration successful'],
        ], 201);
    }

    public function login(LoginRequest $request) {
        $identifier = $request->string('identifier');
        $column = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($column, $identifier)->first();

        if (!$user || !Hash::check($request->string('password'), $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.'
            ], 401);
        }

        // single-session policy (hapus semua token lama)
        $user->tokens()->delete();

        $tokenName = $request->string('device_name') ?: 'api-'.Str::uuid();
        $token = $user->createToken($tokenName, ['*'])->plainTextToken;

        $user->forceFill([
            'last_login_at' => now(),
            'login_ip'      => $request->ip(),
            'user_agent'    => substr((string)$request->header('User-Agent'), 0, 500),
        ])->save();

        activity()->useLog('auth')->causedBy($user)->withProperties([
        'ip' => $request->ip(),
        ])->log('auth.logged_in');

        return response()->json([
            'data' => [
                'id'         => $user->id,
                'user'       => new UserResource($user),
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
            'meta' => ['message' => 'Login successful'],
        ]);
    }

    public function userSelf(Request $request)
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        $request->user()->tokens()->delete();

        activity()->useLog('auth')->causedBy($request->user())->withProperties([
        'ip' => $request->ip(),
        ])->log('auth.logged_out');

        return response()->json([
            'meta' => ['message' => 'Logged out'],
        ]);
    }

    // list & revoke token lain (device management)
    public function tokens(Request $request)
    {
        return response()->json([
            'data' => $request->user()->tokens()->get(['id','username','last_used_at','created_at']),
        ]);
    }

    public function revoke(Request $request, $id)
    {
        $token = $request->user()->tokens()->where('id', $id)->firstOrFail();
        $token->delete();

        return response()->json(['meta' => ['message' => 'Token revoked']]);
    }
}
