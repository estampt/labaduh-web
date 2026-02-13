<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ]);

        // Prefer: one row per (user + device_id)
        if (!empty($data['device_id'])) {
            $pushToken = PushToken::updateOrCreate(
                ['user_id' => $user->id, 'device_id' => $data['device_id']],
                [
                    'token' => $data['token'],
                    'platform' => $data['platform'] ?? null,
                    'last_seen_at' => now(),
                ]
            );
        } else {
            // Fallback: token is unique anyway
            $pushToken = PushToken::updateOrCreate(
                ['token' => $data['token']],
                [
                    'user_id' => $user->id,
                    'platform' => $data['platform'] ?? null,
                    'device_id' => null,
                    'last_seen_at' => now(),
                ]
            );
        }

        return response()->json([
            'data' => [
                'id' => $pushToken->id,
                'user_id' => $pushToken->user_id,
                'platform' => $pushToken->platform,
                'device_id' => $pushToken->device_id,
                'last_seen_at' => optional($pushToken->last_seen_at)->toISOString(),
            ]
        ], 201);
    }

}
