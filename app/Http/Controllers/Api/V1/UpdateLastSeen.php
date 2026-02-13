<?php

// app/Http/Middleware/UpdateLastSeen.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $user = $request->user();
        if ($user) {
            $now = now();
            if (!$user->last_seen_at || $user->last_seen_at->lt($now->copy()->subMinutes(2))) {
                $user->forceFill(['last_seen_at' => $now])->save();
            }
        }

        return $response;
    }
}
