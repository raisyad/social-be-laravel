<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    // PATCH /api/me/profile/visibility
    public function update(Request $request)
    {
        $data = $request->validate([
            'visibility' => ['required','in:public,private'],
        ]);

        $profile = $request->user()->profile()->firstOrCreate(['user_id' => $request->user()->id]);
        $profile->visibility = $data['visibility'];
        $profile->save();

        return response()->json([
            'data' => [
                'user_id'    => $request->user()->id,
                'visibility' => $profile->visibility,
            ],
            'meta' => ['message' => 'Visibility updated'],
        ]);
    }
}
