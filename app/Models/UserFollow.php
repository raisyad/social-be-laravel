<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFollow extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'follower_id',
        'followee_id',
        'status',
        'approved_at',
    ];

    public $incrementing = false;

    protected $guarded = [];
    protected $casts = [
        'created_at' => 'datetime',
        'approved_at' => 'datetime',
    ];
}
