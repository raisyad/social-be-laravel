<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProfilePolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function view(?User $viewer, User $owner): bool
    {
        // public → boleh
        if (! $owner->isPrivate()) return true;

        // private → tamu dilarang
        if (! $viewer) return false;

        // pemilik → boleh
        if ($viewer->id === $owner->id) return true;

        // follower yang sudah accepted → boleh
        return DB::table('user_follows')
            ->where('follower_id', $viewer->id)
            ->where('followee_id', $owner->id)
            ->where('status', 'accepted')
            ->exists();
    }
}
