<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\PostMedia;
use App\Models\PostComment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Post extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected static $logName = 'post';
    protected static $logAttributes = ['content', 'media', 'user_id'];
    protected static $logOnlyDirty = true;     // hanya perubahan
    protected static $submitEmptyLogs = false; // jangan log jika tidak ada perubahan

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['content', 'media', 'user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'user_id',
        'content',
        'media'
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

    // custom description
    public function getDescriptionForEvent(string $eventName): string
    {
        return "post.{$eventName}"; // created/updated/deleted
    }
}
