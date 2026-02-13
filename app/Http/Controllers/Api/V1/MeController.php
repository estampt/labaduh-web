<?php

// app/Http/Controllers/Api/V1/MeController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function lastSeen(Request $request)
    {
        $user = $request->user();

        // Avoid writing too often (optional throttle)
        // Only update if older than 60 seconds
        $now = now();
        if (!$user->last_seen_at || $user->last_seen_at->lt($now->copy()->subSeconds(60))) {
            $user->forceFill(['last_seen_at' => $now])->save();
        }

        return response()->json([
            'data' => [
                'last_seen_at' => optional($user->last_seen_at)->toISOString(),
            ]
        ]);
    }
}






