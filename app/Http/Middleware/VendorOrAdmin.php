<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Vendor;

class VendorOrAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // --- Admin check ---
        $role = $user->role ?? null;
        $isAdmin = ($role === 'admin') || (($user->is_admin ?? false) == true);

        if ($isAdmin) {
            return $next($request);
        }

        // --- Vendor check ---
        if (empty($user->vendor_id)) {
            return response()->json(['message' => 'Forbidden. Vendor/Admin only.'], 403);
        }

        $vendor = Vendor::find($user->vendor_id);

        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found.'], 403);
        }

        if ($vendor->status !== Vendor::STATUS_APPROVED) {
            return response()->json([
                'message' => 'Vendor not approved.',
                'status'  => $vendor->status,
            ], 403);
        }

        return $next($request);
    }
}
