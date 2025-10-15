<?php

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Post;
use App\Models\PostMedia;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\PostResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PostController extends Controller
{
    use AuthorizesRequests;
    // GET /api/users/{user}/posts  (profil orang)
    public function indexByUser(Request $request, $userId)
    {
        $user   = \App\Models\User::with('profile')->findOrFail($userId);
        // $viewer = auth('sanctum')->user(); // bisa null
        $viewer = $request->user();

        if ($user->isPrivate()) {
            if (! $viewer) {
                return response()->json([
                    'data' => [],
                    'meta' => ['message' => 'This profile is private'],
                ], 403);
            }

            if ($viewer->id !== $user->id) {
                $isFollower = DB::table('user_follows')
                    ->where('follower_id', $viewer->id)
                    ->where('followee_id', $user->id)
                    ->where('status', 'accepted')
                    ->exists();

                if (! $isFollower) {
                    return response()->json([
                        'data' => [],
                        'meta' => ['message' => 'This profile is private'],
                    ], 403);
                }
            }
        }

        $posts = \App\Models\Post::with('media')
            ->where('user_id', $user->id)
            ->latest('id')
            ->paginate(10);

        return \App\Http\Resources\PostResource::collection($posts);
    }

    // GET /api/me/posts (punya sendiri)
    public function myIndex(Request $request)
    {
        $posts = Post::with('media')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return PostResource::collection($posts);
    }

    public function index(Request $request, User $user)
    {
        $viewer = $request->user(); // may be null

        if ($user->isPrivate()) {
            if (!$viewer) {
                return response()->json([
                    'data' => [],
                    'meta' => ['message' => 'This profile is private'],
                ], 403);
            }

            if ($viewer->id !== $user->id) {
                $isFollower = DB::table('user_follows')
                    ->where('follower_id', $viewer->id)
                    ->where('followee_id', $user->id)
                    ->where('status', 'accepted')
                    ->exists();

                if (!$isFollower) {
                    return response()->json([
                        'data' => [],
                        'meta' => ['message' => 'This profile is private'],
                    ], 403);
                }
            }
        }

        $posts = \App\Models\Post::with('media')
            ->where('user_id', $user->id)
            ->latest('id')
            ->paginate(12);

        return \App\Http\Resources\PostResource::collection($posts);
    }

    // POST /api/me/posts
    public function store(StorePostRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $post = Post::create([
                'user_id' => $request->user()->id,
                'content' => (string) $request->input('content'),
            ]);

            // upload media (optional)
            if ($request->hasFile('media')) {
                $order = 1;
                foreach ($request->file('media') as $file) {
                    $path = $file->store('posts', 'public');
                    PostMedia::create([
                        'post_id'    => $post->id,
                        'media_url'  => $path,
                        'media_type' => $file->getClientOriginalExtension() === 'mp4' ? 'video' : 'image',
                        'sort_order' => $order++,
                    ]);
                }
            }

            activity()->useLog('post')
            ->causedBy($request->user())
            ->performedOn($post)
            ->withProperties(['media_count' => $post->media()->count()])
            ->log('post.created');

            return (new PostResource($post->load('media')))->response()->setStatusCode(201);
        });
    }

    // PUT /api/me/posts/{post}
    public function update(UpdatePostRequest $request, Post $post)
    {
        $this->authorize('update', $post);

        return DB::transaction(function () use ($request, $post) {
            if ($request->filled('content')) {
                $post->update(['content' => (string) $request->input('content')]);
            }

            // hapus media tertentu
            foreach ((array) $request->input('remove_media_ids', []) as $id) {
                $media = $post->media()->whereKey($id)->first();
                if ($media) {
                    // optional: juga hapus file fisik kalau path lokal
                    // Storage::disk('public')->delete(str_replace(Storage::disk('public')->url(''), '', $media->media_url));
                    $media->delete();
                }
            }

            // tambah media baru
            if ($request->hasFile('media')) {
                $order = (int) ($post->media()->max('sort_order') ?? 0) + 1;
                foreach ($request->file('media') as $file) {
                    $path = $file->store('posts', 'public');
                    $post->media()->create([
                        'media_url'  => $path,
                        'media_type' => $file->getClientOriginalExtension() === 'mp4' ? 'video' : 'image',
                        'sort_order' => $order++,
                    ]);
                }
            }

            activity()->useLog('post')
            ->causedBy($request->user())
            ->performedOn($post)
            ->withProperties([
                'removed_media_ids' => $id,
                'added_media_count' => count($request->file('media', [])),
            ])
            ->log('post.updated');

            return new PostResource($post->load('media'));
        });
    }

    // DELETE /api/me/posts/{post}
    public function destroy(Request $request, Post $post)
    {
        // $this->authorize('delete', $post);


        // $post->delete();

        // return response()->json(['meta' => ['message' => 'Post deleted']]);

        $this->authorize('delete', $post);

        DB::transaction(function () use ($post) {
            foreach ($post->media as $m) {
                // Optional: hapus file fisik jika path lokal
                // Storage::disk('public')->delete($m->media_url);
                $m->delete(); // ini hard delete row media
            }
            $post->delete(); // soft delete post
        });

        activity()->useLog('post')
        ->causedBy($request->user())
        ->performedOn($post)
        ->log('post.deleted');

        return response()->json(['meta' => ['message' => 'Post deleted']]);
    }
}
