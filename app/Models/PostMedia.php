<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PostMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'media_url',
        'media_type', // e.g., 'image', 'video'
        'sort_order'
    ];

    protected $guarded = [];

    // 1-1 (inverse)
    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
