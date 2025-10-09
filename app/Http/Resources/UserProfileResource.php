<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource; // instance User

        return [
            'id'        => $user->id,
            'username'  => $user->username,
            'email'     => $user->email,
            'full_name' => optional($user->profile)->full_name,
            'bio'       => optional($user->profile)->bio,
            'avatar'    => optional($user->profile)->avatar_url,
            'cover'     => optional($user->profile)->cover_url,

            'stats' => [
                'followers' => $user->followers()->count(),
                'following' => $user->followings()->count(), // ← perbaiki
                'posts'     => $user->posts()->count(),
            ],

            // jika login, apakah saya follow dia?
            'is_following' => $request->user()
                ? $request->user()->followings()
                    ->where('users.id', $user->id)
                    ->wherePivot('status','accepted')
                    ->exists()
                : false,
        ];
    }
}
