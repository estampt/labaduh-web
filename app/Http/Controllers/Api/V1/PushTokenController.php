<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\DB;
class PushTokenController extends Controller
{
    public function store(Request $request)
    {
        Log::info('🟡 PushToken@store — Request received');
        DB::enableQueryLog(); // start capturing SQL
        $user = $request->user();

        Log::info('🟡 Auth user', [
            'user_id' => optional($user)->id,
        ]);

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
            'device_id' => ['nullable', 'string', 'max:100'],

            // NEW: shop selection update
            'active_shop_id' => ['nullable', 'integer'],
        ]);

        Log::info('🟡 Payload validated', [
            'token' => substr($data['token'], 0, 20) . '...', // shorten token
            'platform' => $data['platform'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'active_shop_id' => $data['active_shop_id'] ?? null,
        ]);

        // Prefer: one row per (user + device_id)
        if (!empty($data['device_id'])) {

            Log::info('🟡 Using device_id branch');

            $pushToken = PushToken::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'device_id' => $data['device_id'],
                ],
                [
                    'token' => $data['token'],
                    'platform' => $data['platform'] ?? null,
                    'active_shop_id' => $data['active_shop_id'] ?? null,
                    'last_seen_at' => now(),
                ]
            );

        } else {

            Log::info('🟡 Using fallback token branch');

            $pushToken = PushToken::updateOrCreate(
                [
                    'token' => $data['token'],
                ],
                [
                    'user_id' => $user->id,
                    'platform' => $data['platform'] ?? null,
                    'device_id' => null,
                    'active_shop_id' => $data['active_shop_id'] ?? null,
                    'last_seen_at' => now(),
                ]
            );
             Log::info('🧾 SQL Queries', DB::getQueryLog());
             Log::info('🟢 Push token saved/updated', [
            'id' => $pushToken->id,
            'user_id' => $pushToken->user_id,
            'token' => substr($pushToken->token, 0, 20) . '...',
            'device_id' => $pushToken->device_id,
            'active_shop_id' => $pushToken->active_shop_id,
        ]);
        }



        return response()->json([
            'data' => [
                'id' => $pushToken->id,
                'user_id' => $pushToken->user_id,
                'platform' => $pushToken->platform,
                'device_id' => $pushToken->device_id,
                'active_shop_id' => $pushToken->active_shop_id,
                'last_seen_at' => optional($pushToken->last_seen_at)->toISOString(),
            ]
        ], 201);
    }
}
