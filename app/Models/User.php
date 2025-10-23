<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\UserProfile;
use App\Models\Post;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'is_active',
        'last_login_at',
        'login_ip',
        'user_agent',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // 1-1
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }

    // 1-many (post yang dia buat)
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    // followers & following (pivot user_follows)
    public function followers(): BelongsToMany
    {
        // orang lain yang mengikuti saya
        return $this->belongsToMany(User::class, 'user_follows', 'followee_id', 'follower_id')
            ->withPivot(['status','approved_at']);
    }

    public function followings(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'follower_id', 'followee_id')
        ->withPivot(['status','approved_at']);
    }

    // helper boolean
    public function isFollowedBy(User $viewer): bool
    {
        return $this->followers()
            ->where('users.id', $viewer->id)
            ->wherePivot('status', 'accepted')
            ->exists();
    }

    public function isPrivate(): bool
    {
        // Ambil hanya kolom visibility tanpa meng-hydrate model penuh
        $visibility = $this->profile()->value('visibility');
        return ($visibility ?? 'public') === 'private';
    }
}
