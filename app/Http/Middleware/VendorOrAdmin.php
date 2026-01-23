<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VendorOrAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Adjust these checks to match your app:
        // - If you have role column: $user->role === 'admin' / 'vendor'
        // - If you have is_admin boolean: $user->is_admin
        $role = $user->role ?? null;

        $isAdmin = ($role === 'admin') || (($user->is_admin ?? false) == true);
        $isVendor = ($role === 'vendor') || !empty($user->vendor_id);

        if (!$isAdmin && !$isVendor) {
            return response()->json(['message' => 'Forbidden. Vendor/Admin only.'], 403);
        }

        return $next($request);
    }
}
