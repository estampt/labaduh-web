<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Adjust this depending on your user role field:
        // common: role, user_type, type
        $role = $user->role ?? $user->user_type ?? $user->type ?? null;

        if ($role !== 'admin') {
            return response()->json(['message' => 'Forbidden (admin only).'], 403);
        }

        return $next($request);
    }
}
