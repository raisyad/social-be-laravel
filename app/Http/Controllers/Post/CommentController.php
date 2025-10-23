<?php

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Post;
use App\Models\PostComment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Policies\ProfilePolicy;

class CommentController extends Controller
{
    use AuthorizesRequests;

    // GET /api/posts/{post}/comments
    public function index(Request $request, Post $post)
    {
        // bila post milik user private, hormati sama seperti like
        $owner = $post->user;
        $viewer = $request->user();
        if (!app(ProfilePolicy::class)->view($viewer, $owner)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $comments = $post->comments()
            ->with(['user:id,username', 'replies.user:id,username'])
            ->roots()
            ->latest('id')
            ->paginate(10);

        return response()->json([
            'data' => $comments->items(),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page'    => $comments->lastPage(),
            ],
        ]);
    }

    // POST /api/posts/{post}/comments
    public function store(Request $request, Post $post)
    {
        $request->validate([
            'content' => ['required','string','max:500'],
            'parent_comment_id' => ['nullable','integer','exists:post_comments,id'],
        ]);

        $owner  = $post->user;
        $viewer = $request->user();
        if (!app(ProfilePolicy::class)->view($viewer, $owner)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $comment = DB::transaction(function () use ($request, $post, $viewer) {
            $c = PostComment::create([
                'post_id' => $post->id,
                'user_id' => $viewer->id,
                'parent_comment_id' => $request->integer('parent_comment_id') ?: null,
                'content' => (string) $request->input('content'),
            ]);

            $post->increment('comments_count');
            return $c;
        });

        activity()->useLog('comment')
        ->causedBy($request->user())
        ->performedOn($post)
        ->withProperties(['comment_id' => $comment->id])
        ->log('comment.created');

        return response()->json(['data' => $comment->load('user:id,username')], 201);
    }

    // PUT /api/comments/{comment}
    public function update(Request $request, PostComment $comment)
    {
        $this->authorize('update', $comment);

        $request->validate(['content' => ['required','string','max:500']]);

        $comment->update(['content' => (string) $request->input('content')]);

        activity()->useLog('comment')
        ->causedBy($request->user())
        ->performedOn($comment)
        ->log('comment.updated');

        return response()->json(['data' => $comment->fresh()->load('user:id,username')]);
    }

    // DELETE /api/comments/{comment}
    public function destroy(Request $request, PostComment $comment)
    {
        $this->authorize('delete', $comment);

        DB::transaction(function () use ($comment, $request) {
            $comment->delete(); // soft delete

            // Race condition
            $post = Post::lockForUpdate()->find($comment->post_id);
            if ($post && $post->comments_count > 0) {
                $post->decrement('comments_count');
            }

            // jaga agar tidak minus
            DB::table('posts')
            ->where('id', $comment->post_id)
            ->update([
                'comments_count' => DB::raw('CASE WHEN comments_count > 0 THEN comments_count - 1 ELSE 0 END'),
                'updated_at'     => now(),
            ]);

            activity()->useLog('comment')
            ->causedBy($request->user())
            ->performedOn($comment)
            ->log('comment.deleted');
        });

        return response()->json(['meta' => ['message' => 'Comment deleted']]);
    }
}
