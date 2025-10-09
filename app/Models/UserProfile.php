<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $table = 'user_profiles';

    // PRIMARY KEY tabel ini adalah user_id (bukan id)
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'full_name',
        'bio',
        'birth_date',
        'gender',
        'location',
        'website',
        'avatar_url',
        'cover_url',
        'visibility', // penting utk privacy
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
