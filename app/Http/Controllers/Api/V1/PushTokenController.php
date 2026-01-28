<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:20'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

        // If token exists but belongs to another user, re-assign it
        $pushToken = PushToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $user->id,
                'platform' => $data['platform'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'ok' => true,
            'id' => $pushToken->id,
        ]);
    }
}
