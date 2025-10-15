<?php

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Post;

class LikeController extends Controller
{
    public function index(Request $request, Post $post)
    {
        $viewer = $request->user();      // boleh null
        $owner  = $post->user;

        // Hormati privasi
        if (! app(\App\Policies\ProfilePolicy::class)->view($viewer, $owner)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Ambil langsung users dari pivot, tidak perlu ->with('user')
        $likers = $post->likers()
            ->select('users.id', 'users.username')
            ->orderByDesc('post_likes.id')   // stabil
            ->paginate(10);

        return response()->json([
            'data' => collect($likers->items())->map(fn ($u) => [
                'id'       => $u->id,
                'username' => $u->username,
            ]),
            'meta' => [
                'current_page' => $likers->currentPage(),
                'last_page'    => $likers->lastPage(),
                'per_page'     => $likers->perPage(),
                'total'        => $likers->total(),
            ],
        ]);
    }

    // POST /api/posts/{post}/like
    public function like(Request $request, Post $post)
    {
        $viewer = $request->user();

        // hormati privacy: jika tidak boleh view pemilik, dilarang like
        $owner = $post->user;
        if (! app(\App\Policies\ProfilePolicy::class)->view($viewer, $owner)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($viewer->id === $post->user_id) {
            return response()->json(['message' => 'You cannot like your own post'], 422);
        }

        DB::transaction(function () use ($viewer, $post) {
            $attached = DB::table('post_likes')->where([
                'user_id' => $viewer->id,
                'post_id' => $post->id,
            ])->exists();

            if (! $attached) {
                DB::table('post_likes')->insert([
                    'user_id'    => $viewer->id,
                    'post_id'    => $post->id,
                    'created_at' => now(),
                ]);
                $post->increment('likes_count');
            }
        });

        activity()->useLog('like')
        ->causedBy($request->user())
        ->performedOn($post)
        ->log('post.liked');

        return response()->json(['meta' => ['message' => 'Liked']]);
    }

    // DELETE /api/posts/{post}/like
    public function unlike(Request $request, Post $post)
    {
        $viewer = $request->user();

        DB::transaction(function () use ($viewer, $post) {
            $deleted = DB::table('post_likes')->where([
                'user_id' => $viewer->id,
                'post_id' => $post->id,
            ])->delete();

            if ($deleted) {
                $post->decrement('likes_count');
            }
        });

        activity()->useLog('like')
        ->causedBy($request->user())
        ->performedOn($post)
        ->log('post.unliked');

        return response()->json(['meta' => ['message' => 'Unliked']]);
    }
}
