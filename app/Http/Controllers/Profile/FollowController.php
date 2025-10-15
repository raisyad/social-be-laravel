<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FollowController extends Controller
{
    // GET /api/me/follow-requests
    public function requests(Request $request)
    {
        $me = $request->user();

        // siapa saja yang minta follow saya & pending
        $rows = DB::table('user_follows')
            ->join('users', 'users.id', '=', 'user_follows.follower_id')
            ->where('user_follows.followee_id', $me->id)
            ->where('user_follows.status', 'pending')
            ->orderByDesc('user_follows.created_at')
            ->get([
                'users.id as follower_id',
                'users.username',
                'user_follows.created_at',
            ]);

        return response()->json(['data' => $rows]);
    }

    // POST /api/users/{user}/follow
    public function follow(Request $request, User $user)
    {
        abort_if($request->user()->id === $user->id, 422, 'Cannot follow yourself');

        $status = $user->isPrivate() ? 'pending' : 'accepted';

        $request->user()->followings()->syncWithoutDetaching([
            $user->id => ['status' => $status, 'approved_at' => $status === 'accepted' ? now() : null],
        ]);

        activity()->useLog('follow')
        ->causedBy($request->user())
        ->performedOn($user) // user yang di-follow
        ->log('follow.requested_or_followed');

        return response()->json([
            'meta' => ['message' => $status === 'pending' ? 'Follow request sent' : 'Followed'],
        ]);
    }

    // DELETE /api/users/{user}/follow
    public function unfollow(Request $request, User $user)
    {
        $request->user()->followings()->detach($user->id);

        activity()->useLog('follow')
        ->causedBy($request->user())
        ->performedOn($user)
        ->log('follow.unfollowed');

        return response()->json(['meta' => ['message' => 'Unfollowed']]);
    }

    // POST /api/me/follow-requests/{follower}/accept
    public function accept(Request $request, User $follower)
    {
        $me = $request->user();

        $updated = DB::table('user_follows')
            ->where('follower_id', $follower->id)
            ->where('followee_id', $me->id)
            ->where('status', 'pending')
            ->update(['status' => 'accepted', 'approved_at' => now()]);

        activity()->useLog('follow')
        ->causedBy($request->user())       // owner yang menerima
        ->performedOn($follower)           // yang meminta
        ->log('follow.accepted');

        abort_unless($updated, 404, 'Request not found');

        return response()->json(['meta' => ['message' => 'Request accepted']]);
    }

    // POST /api/me/follow-requests/{follower}/reject
    public function reject(Request $request, User $follower)
    {
        $me = $request->user();

        $deleted = DB::table('user_follows')
            ->where('follower_id', $follower->id)
            ->where('followee_id', $me->id)
            ->where('status', 'pending')
            ->delete();

        activity()->useLog('follow')
        ->causedBy($request->user())
        ->performedOn($follower)
        ->log('follow.rejected');


        abort_unless($deleted, 404, 'Request not found');

        return response()->json(['meta' => ['message' => 'Request rejected']]);
    }
}
