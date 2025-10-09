<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PostComment;

class PostCommentPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function update(User $user, PostComment $comment): bool
    {
        return $user->id === $comment->user_id;
    }

    public function delete(User $user, PostComment $comment): bool
    {
        return $user->id === $comment->user_id || $user->id === $comment->post->user_id;
        // opsional: pemilik post juga boleh hapus komentar di post-nya
    }
}
