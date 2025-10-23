<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use App\Http\Resources\UserProfileResource;
use App\Http\Resources\PostResource;
use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    // GET /api/profiles/{username}
    public function show(Request $request, User $user)
    {
        $viewer = $request->user();

        $canSee = true;
        Log::info('viewer', ['id' => optional($viewer)->id]);
        Log::info('owner',  ['id' => $user->id]);
        if ($user->isPrivate()) {
            $canSee = $viewer && (
                $viewer->id === $user->id ||
                DB::table('user_follows')
                    ->where('follower_id', $viewer->id)
                    ->where('followee_id', $user->id)
                    ->where('status', 'accepted')
                    ->exists()
            );
        }
        $pair = DB::table('user_follows')
            ->where('follower_id', optional($viewer)->id)
            ->where('followee_id', $user->id)
            ->first();
        Log::info('pivot', (array) $pair);

        if (! $canSee) {
            return response()->json([
                'data' => [
                    'id'             => $user->id,
                    'username'       => $user->username,
                    'avatar_url'     => optional($user->profile)->avatar_url,
                    'is_private'     => true,
                    'viewer_can_see' => false,
                ],
                'counts' => [
                    'followers' => $user->followers()->wherePivot('status','accepted')->count(),
                    'following' => $user->followings()->wherePivot('status','accepted')->count(),
                    'posts'     => \App\Models\Post::where('user_id',$user->id)->count(),
                ],
            ], 403);
        }

        $user->load('profile');

        return response()->json([
            'data' => [
                'id'             => $user->id,
                'username'       => $user->username,
                'full_name'      => optional($user->profile)->full_name,
                'bio'            => optional($user->profile)->bio,
                'gender'         => optional($user->profile)->gender,
                'birth_date'     => optional($user->profile)->birth_date,
                'avatar_url'     => optional($user->profile)->avatar_url,
                'cover_url'      => optional($user->profile)->cover_url,
                'is_private'     => $user->isPrivate(),
                'viewer_can_see' => true,
            ],
            'counts' => [
                'followers' => $user->followers()->wherePivot('status','accepted')->count(),
                'following' => $user->followings()->wherePivot('status','accepted')->count(),
                'posts'     => \App\Models\Post::where('user_id',$user->id)->count(),
            ],
        ]);
    }

    // GET /api/profiles/{username}/posts?limit=12&page=1
    public function posts(string $username, Request $request)
    {
        $limit = (int) $request->integer('limit', 12);

        $user = User::where('username', $username)->firstOrFail();

        $posts = $user->posts()
            ->with('media')
            ->latest()
            ->paginate($limit);

        return PostResource::collection($posts)
            ->additional(['meta' => ['owner' => $username]]);
    }

    // PUT /api/me/profile (auth)
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();

        $data = $request->only(['full_name','bio','birthdate','gender']);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->storeAs(
                'avatars',
                \Illuminate\Support\Str::uuid().'.'.$request->file('avatar')->extension(),
                'public'
            );
            $data['avatar_url'] = $path; // ← SIMPAN path relatif disk
        }

        if ($request->hasFile('cover')) {
            $path = $request->file('cover')->storeAs(
                'covers',
                \Illuminate\Support\Str::uuid().'.'.$request->file('cover')->extension(),
                'public'
            );
            $data['cover_url'] = $path; // ← SIMPAN path relatif disk
        }

        \App\Models\UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        $profile = $user->load('profile')->profile;

        activity()->useLog('profile')
        ->causedBy($request->user())
        ->performedOn($user->profile)
        ->withProperties([
            'changes' => $user->profile->getChanges(),
        ])->log('profile.updated');

        // Kembalikan field yang diharapkan test:
        return response()->json([
            'data' => [
                'user_id'    => $user->id,
                'full_name'  => $profile->full_name,
                'bio'        => $profile->bio,
                'gender'     => $profile->gender,
                'birth_date' => $profile->birth_date,
                'avatar_url' => $profile->avatar_url,
                'cover_url'  => $profile->cover_url,
            ],
            'meta' => ['message' => 'Profile updated'],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['profile']);
        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'profile' => $user->profile,
                    'is_private' => $user->isPrivate(),
                ],
            ],
        ]);
    }
}
