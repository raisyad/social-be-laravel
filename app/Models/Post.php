<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\PostMedia;
use App\Models\PostComment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'content'
    ];

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function media()
    {
        return $this->hasMany(PostMedia::class);
    }
    public function comments()
    {
        return $this->hasMany(PostComment::class);
    }

    public function scopeTimeline($q) {
        return $q->latest('id')->with('media');
    }
    public function likers()  // orang-orang yang ngelike post ini
    {
        return $this->belongsToMany(User::class, 'post_likes', 'post_id', 'user_id')
            ->withTimestamps()
            ->withPivot('created_at'); // opsional
    }

    public function isLikedBy(User $user): bool
    {
        return $this->likers()->where('users.id', $user->id)->exists();
    }
}
