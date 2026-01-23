<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApprovedVendor
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Admin bypass (adjust to your app)
        $role = $user->role ?? null;
        $isAdmin = ($role === 'admin') || (($user->is_admin ?? false) == true);
        if ($isAdmin) {
            return $next($request);
        }

        // Must be a vendor user
        $vendorId = $user->vendor_id ?? null;
        if (!$vendorId) {
            return response()->json(['message' => 'Forbidden. Vendor only.'], 403);
        }

        // Vendor must be approved
        // Assumes Vendor has approval_status = pending/approved/rejected
        $vendor = $user->vendor; // if relationship exists
        if (!$vendor) {
            // fallback if relation not defined
            $vendor = \App\Models\Vendor::find($vendorId);
        }

        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found.'], 404);
        }

        if (($vendor->approval_status ?? null) !== 'approved') {
            return response()->json(['message' => 'Vendor not approved.'], 403);
        }

        return $next($request);
    }
}
