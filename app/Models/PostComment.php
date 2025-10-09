<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostComment extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['post_id','user_id','parent_comment_id','content'];

    // 1-1 (inverse)
    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function post()   {
        return $this->belongsTo(Post::class);
    }
    public function user()   {
        return $this->belongsTo(User::class);
    }
    public function parent() {
        return $this->belongsTo(PostComment::class, 'parent_comment_id');
    }
    public function replies(){
        return $this->hasMany(PostComment::class, 'parent_comment_id');
    }

    // scope top-level
    public function scopeRoots($q) { return $q->whereNull('parent_comment_id'); }
}
